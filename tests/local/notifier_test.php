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

declare(strict_types=1);

namespace mod_confsubmissions\local;

use advanced_testcase;
use mod_confsubmissions\api;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for \mod_confsubmissions\local\notifier.
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(notifier::class)]
final class notifier_test extends advanced_testcase {
    /**
     * render() substitutes every recognised placeholder and drops (replaces with '')
     * any placeholder not present in the context.
     */
    public function test_render_substitutes_known_and_drops_unknown_placeholders(): void {
        $this->assertSame(
            'Hello Ada, note: .',
            notifier::render('Hello [[fullname]], note: [[doesnotexist]].', ['fullname' => 'Ada'])
        );
    }

    /**
     * A submission's real (userid-backed) speakers are each sent a
     * 'submissioncreated' notification; a manually-entered co-presenter with no
     * userid is skipped, since there is no Moodle account to message.
     */
    public function test_notify_submission_created_notifies_real_speakers_only(): void {
        $this->resetAfterTest();
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);
        // notificationsenabled defaults to 0 (2026-07-09) -- explicitly enable it since
        // this test exercises actual sending.
        $DB->set_field('confsubmissions', 'notificationsenabled', 1, ['id' => $confsubmissions->id]);
        $speaker = $this->getDataGenerator()->create_user();

        $submissionid = (int) $DB->insert_record('confsubmissions_submission', (object) [
            'confsubmissions' => $confsubmissions->id,
            'userid'          => $speaker->id,
            'title'           => 'My Great Talk',
            'abstract'        => 'An abstract.',
            'status'          => 'submitted',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
        api::sync_speakers($submissionid, [
            ['userid' => $speaker->id],
            ['name' => 'Manual Co-Presenter', 'email' => 'manual@example.com'],
        ]);

        $sink = $this->redirectMessages();
        notifier::notify_submission_created($submissionid);
        $messages = $sink->get_messages_by_component_and_type('mod_confsubmissions', 'submissioncreated');

        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertSame((int) $speaker->id, (int) $message->useridto);
        // fullmessage is html_to_text()'s word-wrapped plaintext fallback -- its wrap
        // column depends on the length of the preceding "Hello <fullname>," text, which
        // varies with the generator's randomly-assigned user name, so a wrap can land
        // inside the asserted phrase. fullmessagehtml is the actual unwrapped body.
        $this->assertStringContainsString('My Great Talk', $message->fullmessagehtml);
    }

    /**
     * Withdrawing a submission notifies every editingteacher in the course, not the
     * speakers.
     */
    public function test_notify_submission_withdrawn_notifies_editingteachers_not_speakers(): void {
        $this->resetAfterTest();
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);
        // notificationsenabled defaults to 0 (2026-07-09) -- explicitly enable it since
        // this test exercises actual sending.
        $DB->set_field('confsubmissions', 'notificationsenabled', 1, ['id' => $confsubmissions->id]);
        $speaker = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $submissionid = (int) $DB->insert_record('confsubmissions_submission', (object) [
            'confsubmissions' => $confsubmissions->id,
            'userid'          => $speaker->id,
            'title'           => 'Withdrawn Talk',
            'abstract'        => 'An abstract.',
            'status'          => 'submitted',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
        api::sync_speakers($submissionid, [['userid' => $speaker->id]]);

        $sink = $this->redirectMessages();
        api::set_status($submissionid, 'withdrawn');
        $messages = $sink->get_messages_by_component_and_type('mod_confsubmissions', 'submissionwithdrawn');

        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertSame((int) $teacher->id, (int) $message->useridto);
        // See the comment in the previous test: fullmessage is word-wrapped plaintext
        // whose wrap column shifts with the generator's random user names, so assert
        // against the unwrapped fullmessagehtml instead.
        $this->assertStringContainsString('Withdrawn Talk', $message->fullmessagehtml);
    }

    /**
     * set_status() to a non-'withdrawn' status (e.g. the accept/reject sync
     * mod_confprogram performs) never sends the withdrawal notification.
     */
    public function test_set_status_to_other_statuses_does_not_notify(): void {
        $this->resetAfterTest();
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);

        $submissionid = (int) $DB->insert_record('confsubmissions_submission', (object) [
            'confsubmissions' => $confsubmissions->id,
            'userid'          => $this->getDataGenerator()->create_user()->id,
            'title'           => 'Accepted Talk',
            'abstract'        => 'An abstract.',
            'status'          => 'submitted',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        $sink = $this->redirectMessages();
        api::set_status($submissionid, 'accepted');
        $messages = $sink->get_messages_by_component_and_type('mod_confsubmissions', 'submissionwithdrawn');

        $this->assertCount(0, $messages);
    }

    /**
     * When an instance's notificationsenabled master switch (user request,
     * 2026-07-06) is off, neither the submission-created nor the
     * submission-withdrawn notification is ever sent, regardless of per-type
     * template configuration.
     */
    public function test_master_switch_disables_all_notifications(): void {
        $this->resetAfterTest();
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);
        $DB->set_field('confsubmissions', 'notificationsenabled', 0, ['id' => $confsubmissions->id]);

        $speaker = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $submissionid = (int) $DB->insert_record('confsubmissions_submission', (object) [
            'confsubmissions' => $confsubmissions->id,
            'userid'          => $speaker->id,
            'title'           => 'Silenced Talk',
            'abstract'        => 'An abstract.',
            'status'          => 'submitted',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
        api::sync_speakers($submissionid, [['userid' => $speaker->id]]);

        $sink = $this->redirectMessages();
        notifier::notify_submission_created($submissionid);
        api::set_status($submissionid, 'withdrawn');

        $this->assertCount(0, $sink->get_messages_by_component_and_type('mod_confsubmissions', 'submissioncreated'));
        $this->assertCount(0, $sink->get_messages_by_component_and_type('mod_confsubmissions', 'submissionwithdrawn'));
    }

    /**
     * get_template() falls back to default_template() when no
     * confsubmissions_notiftemplate row exists, and uses the configured row once one
     * does.
     */
    public function test_get_template_falls_back_to_default(): void {
        $this->resetAfterTest();
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);

        $default = notifier::default_template('created');
        $template = notifier::get_template((int) $confsubmissions->id, 'created');
        $this->assertSame($default['subject'], $template['subject']);

        $DB->insert_record('confsubmissions_notiftemplate', (object) [
            'confsubmissions' => $confsubmissions->id,
            'notiftype'       => 'created',
            'subject'         => 'Custom subject [[submissiontitle]]',
            'body'            => 'Custom body',
            'bodyformat'      => FORMAT_HTML,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        $template = notifier::get_template((int) $confsubmissions->id, 'created');
        $this->assertSame('Custom subject [[submissiontitle]]', $template['subject']);
    }
}
