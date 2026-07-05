<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_confsubmissions\local;

use mod_confsubmissions\api;

/**
 * Sends this plugin's two notification events (user request, 2026-07-05: "The
 * system should generate notifications to be sent to ALL presenters on a
 * presentation upon a submission being made... If a submission is withdrawn, a
 * notification should be sent to editingteachers in the course") via Moodle's own
 * core notification system (\core\message\message + message_send()), with an
 * organiser-editable template per notification type (notifications.php,
 * confsubmissions_notiftemplate) -- the same "always usable, never blank, even
 * before an organiser configures anything" convention as
 * mod_confcheckin\local\pdf_generator::default_template().
 *
 * Placeholder syntax is a plain, fixed `[[name]]` delimiter (not a sitewide
 * configurable admin setting like mod_confcheckin's -- that plugin's own
 * templates are PDF documents authored once and reused for the life of the
 * instance, where a delimiter clash is a real, recurring risk; a notification
 * template is a short, one-off piece of text an organiser is unlikely to
 * already have "[[ ]]"-shaped content in, so the extra admin setting was not
 * judged worth its own maintenance burden here).
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notifier {
    /** @var string[] Notification types this plugin sends. */
    public const NOTIF_TYPES = ['created', 'withdrawn'];

    /**
     * The built-in fallback subject/body for a notification type, used until an
     * organiser configures their own via notifications.php.
     *
     * @param string $notiftype One of self::NOTIF_TYPES
     * @return array{subject: string, body: string}
     */
    public static function default_template(string $notiftype): array {
        $templates = [
            'created' => [
                'subject' => get_string('notifdefaultsubject:created', 'mod_confsubmissions'),
                'body'    => get_string('notifdefaultbody:created', 'mod_confsubmissions'),
            ],
            'withdrawn' => [
                'subject' => get_string('notifdefaultsubject:withdrawn', 'mod_confsubmissions'),
                'body'    => get_string('notifdefaultbody:withdrawn', 'mod_confsubmissions'),
            ],
        ];

        return $templates[$notiftype] ?? ['subject' => '', 'body' => ''];
    }

    /**
     * The configured subject/body for a (confsubmissions, notiftype) pair, or
     * default_template()'s fallback if unset/blank.
     *
     * @param int $confsubmissionsid The confsubmissions instance id
     * @param string $notiftype One of self::NOTIF_TYPES
     * @return array{subject: string, body: string, bodyformat: int}
     */
    public static function get_template(int $confsubmissionsid, string $notiftype): array {
        global $DB;

        $template = $DB->get_record('confsubmissions_notiftemplate', [
            'confsubmissions' => $confsubmissionsid,
            'notiftype'       => $notiftype,
        ]);

        $default = self::default_template($notiftype);

        $subject = ($template && trim((string) $template->subject) !== '') ? $template->subject : $default['subject'];
        $body = ($template && trim((string) $template->body) !== '') ? $template->body : $default['body'];
        $bodyformat = $template->bodyformat ?? FORMAT_HTML;

        return ['subject' => $subject, 'body' => $body, 'bodyformat' => (int) $bodyformat];
    }

    /**
     * Substitutes every `[[name]]` placeholder in $text with its value in $context,
     * or '' if $context has no entry for that name -- same "drop what's not
     * recognised" convention as mod_confcheckin\local\placeholder::render().
     *
     * @param string $text The subject or body text
     * @param array $context Placeholder name => replacement value
     * @return string
     */
    public static function render(string $text, array $context): string {
        return preg_replace_callback(
            '/\[\[(\w+)\]\]/',
            static fn (array $matches): string => $context[$matches[1]] ?? '',
            $text
        );
    }

    /**
     * Notifies every real (userid-backed) speaker on a submission that it has been
     * made -- a manually-entered co-presenter with no userid is never notified,
     * since there is no Moodle account to message.
     *
     * @param int $submissionid The confsubmissions_submission id
     * @return void
     */
    public static function notify_submission_created(int $submissionid): void {
        $submission = api::get_submission($submissionid);
        if (!$submission) {
            return;
        }

        global $DB;
        $confsubmissions = $DB->get_record('confsubmissions', ['id' => $submission->confsubmissions]);
        if (!$confsubmissions) {
            return;
        }

        $template = self::get_template((int) $confsubmissions->id, 'created');
        $course = get_course((int) $confsubmissions->course);

        foreach (api::get_speakers($submissionid) as $speaker) {
            if (empty($speaker->userid)) {
                continue;
            }
            $touser = \core_user::get_user((int) $speaker->userid);
            if (!$touser || $touser->deleted) {
                continue;
            }

            $context = [
                'fullname'        => fullname($touser),
                'submissiontitle' => format_string($submission->title),
                'coursename'      => format_string($course->fullname),
            ];

            self::send(
                $touser,
                'submissioncreated',
                self::render($template['subject'], $context),
                self::render($template['body'], $context),
                $template['bodyformat'],
                (int) $confsubmissions->course
            );
        }
    }

    /**
     * Notifies every user holding the editingteacher role in the submission's own
     * course that it has been withdrawn -- NOT the speakers themselves, per the
     * explicit request.
     *
     * @param int $submissionid The confsubmissions_submission id
     * @return void
     */
    public static function notify_submission_withdrawn(int $submissionid): void {
        $submission = api::get_submission($submissionid);
        if (!$submission) {
            return;
        }

        global $DB;
        $confsubmissions = $DB->get_record('confsubmissions', ['id' => $submission->confsubmissions]);
        if (!$confsubmissions) {
            return;
        }

        $template = self::get_template((int) $confsubmissions->id, 'withdrawn');
        $course = get_course((int) $confsubmissions->course);
        $submitter = \core_user::get_user((int) $submission->userid);

        $context = [
            'submissiontitle'   => format_string($submission->title),
            'submitterfullname' => $submitter ? fullname($submitter) : '',
            'coursename'        => format_string($course->fullname),
        ];

        $coursecontext = \context_course::instance((int) $confsubmissions->course);
        $editingteachers = get_role_users(
            (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST),
            $coursecontext
        );

        foreach ($editingteachers as $roleuser) {
            // Role users come back as trimmed-down objects (only the fields
            // get_role_users()'s own SQL selects), not a full user record --
            // message_send() needs the latter (it otherwise re-fetches the full
            // record itself, but not before
            // triggering a "Necessary properties missing" debugging() notice).
            $touser = \core_user::get_user((int) $roleuser->id);
            if (!$touser || $touser->deleted) {
                continue;
            }

            self::send(
                $touser,
                'submissionwithdrawn',
                self::render($template['subject'], $context),
                self::render($template['body'], $context),
                $template['bodyformat'],
                (int) $confsubmissions->course
            );
        }
    }

    /**
     * Builds and sends one \core\message\message via message_send() -- the popup
     * output is enabled by default for both message providers registered in
     * db/messages.php, and the email output additionally defaults ON (see that
     * file), which is what makes "sent by email as well by default" free: no
     * bespoke mailer, just message_send() plus that default-output config.
     *
     * @param \stdClass $touser The recipient user record
     * @param string $messagename One of the providers registered in db/messages.php
     * @param string $subject Already placeholder-rendered
     * @param string $body Already placeholder-rendered
     * @param int $bodyformat FORMAT_HTML or FORMAT_PLAIN
     * @param int $courseid The course id, used to build the contexturl
     * @return void
     */
    private static function send(
        \stdClass $touser,
        string $messagename,
        string $subject,
        string $body,
        int $bodyformat,
        int $courseid
    ): void {
        $bodyhtml = $bodyformat === FORMAT_HTML ? $body : nl2br(s($body));

        $message = new \core\message\message();
        $message->component = 'mod_confsubmissions';
        $message->name = $messagename;
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $touser;
        $message->subject = $subject;
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessage = html_to_text($bodyhtml);
        $message->fullmessagehtml = $bodyhtml;
        $message->smallmessage = $subject;
        $message->notification = 1;
        $message->contexturl = (new \moodle_url('/course/view.php', ['id' => $courseid]))->out(false);
        $message->contexturlname = get_string('pluginname', 'mod_confsubmissions');

        message_send($message);
    }
}
