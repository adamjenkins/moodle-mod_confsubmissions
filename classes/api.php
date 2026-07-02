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

namespace mod_confsubmissions;

/**
 * Public integration surface for downstream conference-tools plugins
 * (mod_confprogram, mod_confscheduler, mod_confcheckin).
 *
 * Covers read accessors (submissions, speakers, tracks, optional fields) plus
 * the write operations needed by this plugin's own screens (track CRUD,
 * speaker/optional-field syncing on submit). Caching and pagination for large
 * datasets are follow-up work.
 *
 * Capability contract: these methods do NOT check capabilities or context
 * themselves — they are a raw data-access layer only. Every submission
 * record may contain personal data (speaker names/emails, abstract text),
 * so any caller (including other conference-tools plugins) MUST verify the
 * current user's capability (e.g. mod/confsubmissions:viewall or :viewown)
 * against the relevant \context_module before calling, or before exposing
 * the returned data to a user/response.
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {
    /**
     * Returns a single submission record.
     *
     * @param int $id The confsubmissions_submission id
     * @return \stdClass|false The submission record, or false if not found
     */
    public static function get_submission(int $id) {
        global $DB;

        return $DB->get_record('confsubmissions_submission', ['id' => $id]);
    }

    /**
     * Returns submissions belonging to any confsubmissions instance in a course.
     *
     * TODO: consider pagination once submission volumes get large.
     *
     * @param int $courseid The course id
     * @param array $filters Optional filters; supports 'status', 'trackid' and 'userid'
     * @return \stdClass[] Array of submission records, keyed by id
     */
    public static function get_submissions_for_course(int $courseid, array $filters = []): array {
        global $DB;

        $params = ['courseid' => $courseid];
        $where = ['cs.course = :courseid'];

        if (isset($filters['status'])) {
            $where[] = 'sub.status = :status';
            $params['status'] = $filters['status'];
        }

        if (isset($filters['trackid'])) {
            $where[] = 'sub.trackid = :trackid';
            $params['trackid'] = $filters['trackid'];
        }

        if (isset($filters['userid'])) {
            $where[] = 'sub.userid = :userid';
            $params['userid'] = $filters['userid'];
        }

        $sql = 'SELECT sub.*
                  FROM {confsubmissions_submission} sub
                  JOIN {confsubmissions} cs ON cs.id = sub.confsubmissions
                 WHERE ' . implode(' AND ', $where) . '
              ORDER BY sub.timecreated ASC';

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Returns submissions belonging to a single confsubmissions instance.
     *
     * Unlike get_submissions_for_course(), this scopes to exactly one activity
     * instance, which is what the instance's own "all submissions" listing needs
     * (a course can contain more than one Conference Submissions activity).
     *
     * @param int $confsubmissionsid The confsubmissions instance id
     * @param array $filters Optional filters; supports 'status', 'trackid' and 'userid'
     * @return \stdClass[] Array of submission records, keyed by id
     */
    public static function get_submissions_for_instance(int $confsubmissionsid, array $filters = []): array {
        global $DB;

        $conditions = ['confsubmissions' => $confsubmissionsid];

        if (isset($filters['status']) && $filters['status'] !== '') {
            $conditions['status'] = $filters['status'];
        }

        if (isset($filters['trackid']) && $filters['trackid'] !== '') {
            $conditions['trackid'] = $filters['trackid'];
        }

        if (isset($filters['userid']) && $filters['userid'] !== '') {
            $conditions['userid'] = $filters['userid'];
        }

        return $DB->get_records('confsubmissions_submission', $conditions, 'timecreated DESC');
    }

    /**
     * Returns the speakers attached to a submission, in sort order.
     *
     * @param int $submissionid The confsubmissions_submission id
     * @return \stdClass[] Array of speaker records, keyed by id
     */
    public static function get_speakers(int $submissionid): array {
        global $DB;

        return $DB->get_records(
            'confsubmissions_speaker',
            ['submissionid' => $submissionid],
            'sortorder ASC'
        );
    }

    /**
     * Returns the tracks configured for the confsubmissions instance identified by a cmid.
     *
     * @param int $cmid The course-module id
     * @return \stdClass[] Array of track records, keyed by id
     */
    public static function get_tracks(int $cmid): array {
        global $DB;

        $cm = get_coursemodule_from_id('confsubmissions', $cmid, 0, false, MUST_EXIST);

        return $DB->get_records(
            'confsubmissions_track',
            ['confsubmissions' => $cm->instance],
            'sortorder ASC'
        );
    }

    /**
     * Returns the submitter's answers to enabled optional fields for a submission.
     *
     * @param int $submissionid The confsubmissions_submission id
     * @return array Field values keyed by fieldname
     */
    public static function get_optional_field_values(int $submissionid): array {
        global $DB;

        return $DB->get_records_menu(
            'confsubmissions_fieldval',
            ['submissionid' => $submissionid],
            '',
            'fieldname, value'
        );
    }

    /**
     * Returns the optional-field configuration rows for an instance, keyed by id.
     *
     * @param int $confsubmissionsid The confsubmissions instance id
     * @return \stdClass[] Field configuration rows keyed by id
     */
    public static function get_fields(int $confsubmissionsid): array {
        global $DB;

        return $DB->get_records(
            'confsubmissions_field',
            ['confsubmissions' => $confsubmissionsid],
            'sortorder ASC'
        );
    }

    /**
     * Returns the machine names of the optional fields enabled for an instance, in order.
     *
     * @param int $confsubmissionsid The confsubmissions instance id
     * @return string[] Enabled fieldnames
     */
    public static function get_enabled_fieldnames(int $confsubmissionsid): array {
        $fields = self::get_fields($confsubmissionsid);

        $enabled = array_filter($fields, fn($field) => (bool) $field->enabled);

        return array_values(array_map(fn($field) => $field->fieldname, $enabled));
    }

    /**
     * Adds a new track to an instance, appended to the end of the sort order.
     *
     * @param int $confsubmissionsid The confsubmissions instance id
     * @param string $name The track name
     * @return int The id of the newly inserted track
     */
    public static function add_track(int $confsubmissionsid, string $name): int {
        global $DB;

        $maxsortorder = (int) $DB->get_field_sql(
            'SELECT MAX(sortorder) FROM {confsubmissions_track} WHERE confsubmissions = ?',
            [$confsubmissionsid]
        );

        $record = (object) [
            'confsubmissions' => $confsubmissionsid,
            'name'            => $name,
            'sortorder'       => $maxsortorder + 1,
        ];

        return $DB->insert_record('confsubmissions_track', $record);
    }

    /**
     * Deletes a track. Submissions referencing it are left with no track (trackid null)
     * rather than being deleted.
     *
     * @param int $trackid The confsubmissions_track id
     * @return bool
     */
    public static function delete_track(int $trackid): bool {
        global $DB;

        $DB->set_field('confsubmissions_submission', 'trackid', null, ['trackid' => $trackid]);

        return $DB->delete_records('confsubmissions_track', ['id' => $trackid]);
    }

    /**
     * Replaces the speakers attached to a submission with a new ordered set.
     *
     * Existing speaker rows for the submission are deleted and the given speakers are
     * inserted in order, sortorder 0..n-1. The first speaker is always stored with
     * role 'primary'; every subsequent speaker is stored with role 'co-presenter',
     * regardless of any 'role' key present in the given rows, so callers do not need
     * to compute roles themselves.
     *
     * Each speaker row must be an array with either a 'userid' key (an enrolled
     * Moodle user) or 'name'/'email' keys (a manually-entered co-presenter).
     *
     * @param int $submissionid The confsubmissions_submission id
     * @param array $speakers Ordered list of speaker rows
     * @return void
     */
    public static function sync_speakers(int $submissionid, array $speakers): void {
        global $DB;

        $DB->delete_records('confsubmissions_speaker', ['submissionid' => $submissionid]);

        $sortorder = 0;
        foreach (array_values($speakers) as $index => $speaker) {
            $record = (object) [
                'submissionid' => $submissionid,
                'userid'       => $speaker['userid'] ?? null,
                'name'         => $speaker['name'] ?? null,
                'email'        => $speaker['email'] ?? null,
                'role'         => $index === 0 ? 'primary' : 'co-presenter',
                'sortorder'    => $sortorder,
            ];
            $DB->insert_record('confsubmissions_speaker', $record);
            $sortorder++;
        }
    }

    /**
     * Replaces the optional-field answers attached to a submission with a new set.
     *
     * Existing confsubmissions_fieldval rows for the submission are deleted; a row is
     * (re)inserted only for values that are not the empty string, so a submitter
     * clearing an optional field's answer removes the row rather than storing '' .
     *
     * @param int $submissionid The confsubmissions_submission id
     * @param array $values Field values keyed by fieldname
     * @return void
     */
    public static function sync_optional_fields(int $submissionid, array $values): void {
        global $DB;

        $DB->delete_records('confsubmissions_fieldval', ['submissionid' => $submissionid]);

        foreach ($values as $fieldname => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            $DB->insert_record('confsubmissions_fieldval', (object) [
                'submissionid' => $submissionid,
                'fieldname'    => $fieldname,
                'value'        => $value,
            ]);
        }
    }
}
