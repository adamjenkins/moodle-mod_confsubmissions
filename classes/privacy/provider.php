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

namespace mod_confsubmissions\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for mod_confsubmissions.
 *
 * Personal data is stored in four places:
 * - confsubmissions_submission: the submitter (userid), plus their title/abstract text.
 * - confsubmissions_speaker: speakers attached to a submission - either an enrolled user
 *   (userid) or a manually-typed name/email for a co-presenter without a Moodle account.
 * - confsubmissions_fieldval: the submitter's free-text answers to optional fields.
 * - confsubmissions_datepref: the submitter's preferred conference days (only meaningful
 *   when the instance has offerpreferreddates enabled), exported/deleted alongside the
 *   submission's other attached data even though it holds no free text of its own.
 *
 * A user can appear in this data either as the submission owner (userid on the
 * submission itself) or as a named speaker/co-presenter (userid on a speaker row
 * attached to someone else's submission). Both cases are handled below.
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Returns metadata describing the personal data stored by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {

        $collection->add_database_table('confsubmissions_submission', [
            'userid'       => 'privacy:metadata:confsubmissions_submission:userid',
            'title'        => 'privacy:metadata:confsubmissions_submission:title',
            'abstract'     => 'privacy:metadata:confsubmissions_submission:abstract',
            'status'       => 'privacy:metadata:confsubmissions_submission:status',
            'timecreated'  => 'privacy:metadata:confsubmissions_submission:timecreated',
            'timemodified' => 'privacy:metadata:confsubmissions_submission:timemodified',
        ], 'privacy:metadata:confsubmissions_submission');

        $collection->add_database_table('confsubmissions_speaker', [
            'userid' => 'privacy:metadata:confsubmissions_speaker:userid',
            'name'   => 'privacy:metadata:confsubmissions_speaker:name',
            'email'  => 'privacy:metadata:confsubmissions_speaker:email',
            'role'   => 'privacy:metadata:confsubmissions_speaker:role',
        ], 'privacy:metadata:confsubmissions_speaker');

        $collection->add_database_table('confsubmissions_fieldval', [
            'fieldid' => 'privacy:metadata:confsubmissions_fieldval:fieldid',
            'value'   => 'privacy:metadata:confsubmissions_fieldval:value',
        ], 'privacy:metadata:confsubmissions_fieldval');

        $collection->add_database_table('confsubmissions_datepref', [
            'prefdate' => 'privacy:metadata:confsubmissions_datepref:prefdate',
        ], 'privacy:metadata:confsubmissions_datepref');

        return $collection;
    }

    /**
     * Returns the list of contexts that contain personal data for the given user.
     *
     * A user has data in a context either as the owner of a submission, or as a
     * speaker (co-presenter) attached to someone else's submission.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT c.id
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = 'confsubmissions'
            INNER JOIN {confsubmissions} cs ON cs.id = cm.instance
            INNER JOIN {confsubmissions_submission} sub ON sub.confsubmissions = cs.id
             LEFT JOIN {confsubmissions_speaker} sp ON sp.submissionid = sub.id AND sp.userid = :userid1
                 WHERE sub.userid = :userid2
                    OR sp.userid IS NOT NULL";

        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'userid1'      => $userid,
            'userid2'      => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Gets the list of users within the specified context who have personal data.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('confsubmissions', $context->instanceid);
        if (!$cm) {
            return;
        }

        $submitters = "SELECT sub.userid
                          FROM {confsubmissions_submission} sub
                         WHERE sub.confsubmissions = :instanceid1";
        $userlist->add_from_sql('userid', $submitters, ['instanceid1' => $cm->instance]);

        $speakers = "SELECT sp.userid
                       FROM {confsubmissions_speaker} sp
                       JOIN {confsubmissions_submission} sub ON sub.id = sp.submissionid
                      WHERE sub.confsubmissions = :instanceid2 AND sp.userid IS NOT NULL";
        $userlist->add_from_sql('userid', $speakers, ['instanceid2' => $cm->instance]);
    }

    /**
     * Exports personal data for the approved contexts belonging to the user.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }

            $cm = get_coursemodule_from_id('confsubmissions', $context->instanceid);
            $confsubmissions = $DB->get_record('confsubmissions', ['id' => $cm->instance]);
            if (!$confsubmissions) {
                continue;
            }

            // Field names, by id, for a human-readable export label -- see the loop
            // below, which exports each field's current name rather than its raw id.
            $fieldnamesbyid = $DB->get_records_menu(
                'confsubmissions_field',
                ['confsubmissions' => $confsubmissions->id],
                '',
                'id, name'
            );

            // Submissions owned by this user: export in full, including speakers and
            // optional-field answers attached to each one.
            $ownsubmissions = $DB->get_records(
                'confsubmissions_submission',
                ['confsubmissions' => $confsubmissions->id, 'userid' => $userid]
            );

            foreach ($ownsubmissions as $submission) {
                $speakers = $DB->get_records(
                    'confsubmissions_speaker',
                    ['submissionid' => $submission->id],
                    'sortorder ASC'
                );
                $fieldvals = $DB->get_records(
                    'confsubmissions_fieldval',
                    ['submissionid' => $submission->id]
                );
                $prefdates = $DB->get_records(
                    'confsubmissions_datepref',
                    ['submissionid' => $submission->id],
                    'prefdate ASC'
                );

                $data = (object) [
                    'title'        => $submission->title,
                    'abstract'     => $submission->abstract,
                    'status'       => $submission->status,
                    'timecreated'  => \core_privacy\local\request\transform::datetime($submission->timecreated),
                    'timemodified' => \core_privacy\local\request\transform::datetime($submission->timemodified),
                    'speakers'     => array_map(fn($sp) => (object) [
                        'name'  => $sp->name,
                        'email' => $sp->email,
                        'role'  => $sp->role,
                    ], array_values($speakers)),
                    'fields' => array_map(fn($fv) => (object) [
                        'fieldname' => $fieldnamesbyid[$fv->fieldid] ?? (string) $fv->fieldid,
                        'value'     => $fv->value,
                    ], array_values($fieldvals)),
                    'preferreddates' => array_map(
                        fn($pd) => \core_privacy\local\request\transform::date($pd->prefdate),
                        array_values($prefdates)
                    ),
                ];

                writer::with_context($context)->export_data(['submission_' . $submission->id], $data);
            }

            // Submissions where this user is listed only as a speaker/co-presenter (not
            // the owner): export the limited speaker-role data separately.
            $speakerrows = $DB->get_records_sql(
                "SELECT sp.*
                   FROM {confsubmissions_speaker} sp
                   JOIN {confsubmissions_submission} sub ON sub.id = sp.submissionid
                  WHERE sub.confsubmissions = :instanceid AND sp.userid = :userid AND sub.userid != :userid2",
                ['instanceid' => $confsubmissions->id, 'userid' => $userid, 'userid2' => $userid]
            );

            foreach ($speakerrows as $speaker) {
                $submission = $DB->get_record('confsubmissions_submission', ['id' => $speaker->submissionid]);
                $data = (object) [
                    'submissiontitle' => $submission->title ?? '',
                    'role'            => $speaker->role,
                ];
                writer::with_context($context)->export_data(
                    ['speaker_on_submission_' . $speaker->submissionid],
                    $data
                );
            }
        }
    }

    /**
     * Deletes all personal data for all users in the specified context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('confsubmissions', $context->instanceid);
        $confsubmissions = $DB->get_record('confsubmissions', ['id' => $cm->instance]);
        if (!$confsubmissions) {
            return;
        }

        $submissionids = $DB->get_fieldset_select(
            'confsubmissions_submission',
            'id',
            'confsubmissions = ?',
            [$confsubmissions->id]
        );

        if ($submissionids) {
            [$insql, $params] = $DB->get_in_or_equal($submissionids);
            $DB->delete_records_select('confsubmissions_fieldval', "submissionid $insql", $params);
            $DB->delete_records_select('confsubmissions_datepref', "submissionid $insql", $params);
            $DB->delete_records_select('confsubmissions_speaker', "submissionid $insql", $params);
        }

        $DB->delete_records('confsubmissions_submission', ['confsubmissions' => $confsubmissions->id]);
    }

    /**
     * Deletes all personal data for the specified user in the given contexts.
     *
     * The user's own submissions (and everything attached to them) are deleted in
     * full. Where the user is only a speaker/co-presenter on someone else's
     * submission, only their speaker row is removed; the submission itself and its
     * other speakers are left intact.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }

            $cm = get_coursemodule_from_id('confsubmissions', $context->instanceid);
            $confsubmissions = $DB->get_record('confsubmissions', ['id' => $cm->instance]);
            if (!$confsubmissions) {
                continue;
            }

            $ownsubmissionids = $DB->get_fieldset_select(
                'confsubmissions_submission',
                'id',
                'confsubmissions = ? AND userid = ?',
                [$confsubmissions->id, $userid]
            );

            if ($ownsubmissionids) {
                [$insql, $params] = $DB->get_in_or_equal($ownsubmissionids);
                $DB->delete_records_select('confsubmissions_fieldval', "submissionid $insql", $params);
                $DB->delete_records_select('confsubmissions_speaker', "submissionid $insql", $params);
                $DB->delete_records('confsubmissions_submission', [
                    'confsubmissions' => $confsubmissions->id,
                    'userid'          => $userid,
                ]);
            }

            // Remove this user's own speaker row on any other submission in this instance.
            $DB->delete_records_select(
                'confsubmissions_speaker',
                'userid = :userid AND submissionid IN (
                    SELECT id FROM {confsubmissions_submission} WHERE confsubmissions = :instanceid
                )',
                ['userid' => $userid, 'instanceid' => $confsubmissions->id]
            );
        }
    }

    /**
     * Deletes personal data for the given users in the specified context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('confsubmissions', $context->instanceid);
        $confsubmissions = $DB->get_record('confsubmissions', ['id' => $cm->instance]);
        if (!$confsubmissions) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$usersql, $userparams] = $DB->get_in_or_equal($userids);

        $ownsubmissionids = $DB->get_fieldset_select(
            'confsubmissions_submission',
            'id',
            "confsubmissions = ? AND userid $usersql",
            array_merge([$confsubmissions->id], $userparams)
        );

        if ($ownsubmissionids) {
            [$insql, $params] = $DB->get_in_or_equal($ownsubmissionids);
            $DB->delete_records_select('confsubmissions_fieldval', "submissionid $insql", $params);
            $DB->delete_records_select('confsubmissions_datepref', "submissionid $insql", $params);
            $DB->delete_records_select('confsubmissions_speaker', "submissionid $insql", $params);
            $DB->delete_records_select(
                'confsubmissions_submission',
                "confsubmissions = ? AND userid $usersql",
                array_merge([$confsubmissions->id], $userparams)
            );
        }

        [$usersql2, $userparams2] = $DB->get_in_or_equal($userids);
        $DB->delete_records_select(
            'confsubmissions_speaker',
            "userid $usersql2 AND submissionid IN (
                SELECT id FROM {confsubmissions_submission} WHERE confsubmissions = ?
            )",
            array_merge($userparams2, [$confsubmissions->id])
        );
    }
}
