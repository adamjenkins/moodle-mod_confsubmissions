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
 * This is a first-pass scaffold: the read-only accessors below have minimal
 * working implementations sufficient for downstream plugins to start
 * integrating against a stable signature; richer filtering, caching, and
 * write operations are follow-up work.
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
     * TODO: expand $filters support (e.g. cmid, trackid, status, userid) and
     * consider pagination once the full listing screens are built.
     *
     * @param int $courseid The course id
     * @param array $filters Optional filters; currently supports 'status' and 'trackid'
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

        $sql = 'SELECT sub.*
                  FROM {confsubmissions_submission} sub
                  JOIN {confsubmissions} cs ON cs.id = sub.confsubmissions
                 WHERE ' . implode(' AND ', $where) . '
              ORDER BY sub.timecreated ASC';

        return $DB->get_records_sql($sql, $params);
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
}
