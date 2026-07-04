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
     * Validates that a colour is either null or a 6-digit hex colour (e.g. #3366cc),
     * the same convention used by mod_confscheduler's confscheduler_room.colour.
     *
     * @param string|null $colour
     * @return void
     * @throws \invalid_parameter_exception if $colour is non-null and not a valid hex colour
     */
    protected static function validate_colour(?string $colour): void {
        if ($colour === null || $colour === '') {
            return;
        }

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $colour)) {
            throw new \invalid_parameter_exception(get_string('error:invalidcolour', 'mod_confsubmissions'));
        }
    }

    /**
     * Validates that an icon is either null/empty or one of the curated icon keys
     * (confsubmissions_track_icon_keys() in lib.php). Tracks are never themed with
     * free-text or uploaded icons - see that function's docblock for why.
     *
     * @param string|null $icon
     * @return void
     * @throws \invalid_parameter_exception if $icon is non-empty and not an allowed key
     */
    protected static function validate_icon(?string $icon): void {
        if ($icon === null || $icon === '') {
            return;
        }

        if (!in_array($icon, confsubmissions_track_icon_keys(), true)) {
            throw new \invalid_parameter_exception(get_string('error:invalidicon', 'mod_confsubmissions'));
        }
    }

    /**
     * Returns the tracks configured for the confsubmissions instance identified by a cmid.
     *
     * Each track record includes 'colour' (a hex string or null) and 'icon' (a curated
     * icon key or null), in addition to the usual id/confsubmissions/name/sortorder columns.
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
     * Returns the submitter's answers to an instance's optional fields for a submission.
     *
     * @param int $submissionid The confsubmissions_submission id
     * @return array Field values keyed by fieldid
     */
    public static function get_optional_field_values(int $submissionid): array {
        global $DB;

        return $DB->get_records_menu(
            'confsubmissions_fieldval',
            ['submissionid' => $submissionid],
            '',
            'fieldid, value'
        );
    }

    /**
     * Returns the optional-field configuration rows for an instance, keyed by id, in
     * sort order.
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
     * Returns a single optional-field configuration row.
     *
     * @param int $fieldid The confsubmissions_field id
     * @return \stdClass|false The field record, or false if not found
     */
    public static function get_field(int $fieldid) {
        global $DB;

        return $DB->get_record('confsubmissions_field', ['id' => $fieldid]);
    }

    /**
     * Validates a submission type's duration: a positive whole number of minutes.
     *
     * @param int $durationminutes
     * @return void
     * @throws \invalid_parameter_exception if $durationminutes is not a positive integer
     */
    protected static function validate_duration(int $durationminutes): void {
        if ($durationminutes <= 0) {
            throw new \invalid_parameter_exception(get_string('error:invalidduration', 'mod_confsubmissions'));
        }
    }

    /**
     * Returns the submission types configured for the confsubmissions instance
     * identified by a cmid, in sort order.
     *
     * @param int $cmid The course-module id
     * @return \stdClass[] Array of submission type records, keyed by id
     */
    public static function get_submission_types(int $cmid): array {
        global $DB;

        $cm = get_coursemodule_from_id('confsubmissions', $cmid, 0, false, MUST_EXIST);

        return $DB->get_records(
            'confsubmissions_submissiontype',
            ['confsubmissions' => $cm->instance],
            'sortorder ASC'
        );
    }

    /**
     * Returns a single submission type record.
     *
     * @param int $id The confsubmissions_submissiontype id
     * @return \stdClass|false The submission type record, or false if not found
     */
    public static function get_submission_type(int $id) {
        global $DB;

        return $DB->get_record('confsubmissions_submissiontype', ['id' => $id]);
    }

    /**
     * Adds a new submission type to an instance, appended to the end of the sort order.
     *
     * @param int $confsubmissionsid The confsubmissions instance id
     * @param string $name The submission type name
     * @param int $durationminutes Default presentation duration in minutes for this type
     * @return int The id of the newly inserted submission type
     * @throws \invalid_parameter_exception if $durationminutes is not a positive integer
     */
    public static function add_submission_type(int $confsubmissionsid, string $name, int $durationminutes): int {
        global $DB;

        self::validate_duration($durationminutes);

        $maxsortorder = (int) $DB->get_field_sql(
            'SELECT MAX(sortorder) FROM {confsubmissions_submissiontype} WHERE confsubmissions = ?',
            [$confsubmissionsid]
        );

        $record = (object) [
            'confsubmissions' => $confsubmissionsid,
            'name'            => $name,
            'durationminutes' => $durationminutes,
            'sortorder'       => $maxsortorder + 1,
        ];

        return $DB->insert_record('confsubmissions_submissiontype', $record);
    }

    /**
     * Updates a submission type's name and duration in place (sortorder is untouched).
     *
     * @param int $submissiontypeid The confsubmissions_submissiontype id
     * @param string $name The submission type name
     * @param int $durationminutes Default presentation duration in minutes for this type
     * @return void
     * @throws \invalid_parameter_exception if $durationminutes is not a positive integer
     */
    public static function update_submission_type(int $submissiontypeid, string $name, int $durationminutes): void {
        global $DB;

        self::validate_duration($durationminutes);

        $DB->update_record('confsubmissions_submissiontype', (object) [
            'id'              => $submissiontypeid,
            'name'            => $name,
            'durationminutes' => $durationminutes,
        ]);
    }

    /**
     * Deletes a submission type. Submissions referencing it are left with no type
     * (submissiontypeid null) rather than being deleted, matching delete_track()'s
     * pattern.
     *
     * @param int $submissiontypeid The confsubmissions_submissiontype id
     * @return bool
     */
    public static function delete_submission_type(int $submissiontypeid): bool {
        global $DB;

        $DB->set_field('confsubmissions_submission', 'submissiontypeid', null, ['submissiontypeid' => $submissiontypeid]);

        return $DB->delete_records('confsubmissions_submissiontype', ['id' => $submissiontypeid]);
    }

    /**
     * Adds a new track to an instance, appended to the end of the sort order.
     *
     * @param int $confsubmissionsid The confsubmissions instance id
     * @param string $name The track name
     * @param string|null $colour A hex colour (e.g. #3366cc) to theme this track, or null for none
     * @param string|null $icon A curated icon key (see confsubmissions_track_icon_keys() in lib.php), or null
     * @return int The id of the newly inserted track
     * @throws \invalid_parameter_exception if $colour or $icon is set and invalid
     */
    public static function add_track(int $confsubmissionsid, string $name, ?string $colour = null, ?string $icon = null): int {
        global $DB;

        self::validate_colour($colour);
        self::validate_icon($icon);

        $maxsortorder = (int) $DB->get_field_sql(
            'SELECT MAX(sortorder) FROM {confsubmissions_track} WHERE confsubmissions = ?',
            [$confsubmissionsid]
        );

        $record = (object) [
            'confsubmissions' => $confsubmissionsid,
            'name'            => $name,
            'sortorder'       => $maxsortorder + 1,
            'colour'          => $colour ?: null,
            'icon'            => $icon ?: null,
        ];

        return $DB->insert_record('confsubmissions_track', $record);
    }

    /**
     * Updates a track's name, colour and icon in place (sortorder is untouched).
     *
     * @param int $trackid The confsubmissions_track id
     * @param string $name The track name
     * @param string|null $colour A hex colour (e.g. #3366cc) to theme this track, or null for none
     * @param string|null $icon A curated icon key (see confsubmissions_track_icon_keys() in lib.php), or null
     * @return void
     * @throws \invalid_parameter_exception if $colour or $icon is set and invalid
     */
    public static function update_track(int $trackid, string $name, ?string $colour = null, ?string $icon = null): void {
        global $DB;

        self::validate_colour($colour);
        self::validate_icon($icon);

        $DB->update_record('confsubmissions_track', (object) [
            'id'     => $trackid,
            'name'   => $name,
            'colour' => $colour ?: null,
            'icon'   => $icon ?: null,
        ]);
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
     * @param array $values Field values keyed by fieldid
     * @return void
     */
    public static function sync_optional_fields(int $submissionid, array $values): void {
        global $DB;

        $DB->delete_records('confsubmissions_fieldval', ['submissionid' => $submissionid]);

        foreach ($values as $fieldid => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            $DB->insert_record('confsubmissions_fieldval', (object) [
                'submissionid' => $submissionid,
                'fieldid'      => $fieldid,
                'value'        => $value,
            ]);
        }
    }

    /**
     * Validates a field type against the fixed allow-list (confsubmissions_field_types()
     * in lib.php).
     *
     * @param string $type
     * @return void
     * @throws \invalid_parameter_exception if $type is not a recognised field type
     */
    protected static function validate_field_type(string $type): void {
        if (!in_array($type, confsubmissions_field_types(), true)) {
            throw new \invalid_parameter_exception(get_string('error:invalidfieldtype', 'mod_confsubmissions'));
        }
    }

    /**
     * Validates a 'menu'-type field's options: at least one non-blank choice.
     * Meaningless (and not checked) for any other field type.
     *
     * @param string|null $options
     * @return void
     * @throws \invalid_parameter_exception if $options has no non-blank choice
     */
    protected static function validate_field_menu_options(?string $options): void {
        if (empty(confsubmissions_parse_field_options($options))) {
            throw new \invalid_parameter_exception(get_string('error:invalidfieldoptions', 'mod_confsubmissions'));
        }
    }

    /**
     * Adds a new optional field to an instance, appended to the end of the sort order.
     *
     * @param int $confsubmissionsid The confsubmissions instance id
     * @param string $name The field's display label
     * @param string $type One of confsubmissions_field_types()
     * @param string|null $options Newline-separated choices; only meaningful when $type is 'menu'
     * @param bool $required Whether a presenter must answer this field
     * @return int The id of the newly inserted field
     * @throws \invalid_parameter_exception if $type is invalid, or $type is 'menu' with no choices
     */
    public static function add_field(
        int $confsubmissionsid,
        string $name,
        string $type,
        ?string $options,
        bool $required
    ): int {
        global $DB;

        self::validate_field_type($type);
        if ($type === 'menu') {
            self::validate_field_menu_options($options);
        }

        $maxsortorder = (int) $DB->get_field_sql(
            'SELECT MAX(sortorder) FROM {confsubmissions_field} WHERE confsubmissions = ?',
            [$confsubmissionsid]
        );

        $record = (object) [
            'confsubmissions' => $confsubmissionsid,
            'name'            => $name,
            'type'            => $type,
            'options'         => $type === 'menu' ? $options : null,
            'required'        => $required ? 1 : 0,
            'sortorder'       => $maxsortorder + 1,
        ];

        return $DB->insert_record('confsubmissions_field', $record);
    }

    /**
     * Updates an optional field's name, type, options and required flag in place
     * (sortorder is untouched).
     *
     * @param int $fieldid The confsubmissions_field id
     * @param string $name The field's display label
     * @param string $type One of confsubmissions_field_types()
     * @param string|null $options Newline-separated choices; only meaningful when $type is 'menu'
     * @param bool $required Whether a presenter must answer this field
     * @return void
     * @throws \invalid_parameter_exception if $type is invalid, or $type is 'menu' with no choices
     */
    public static function update_field(int $fieldid, string $name, string $type, ?string $options, bool $required): void {
        global $DB;

        self::validate_field_type($type);
        if ($type === 'menu') {
            self::validate_field_menu_options($options);
        }

        $DB->update_record('confsubmissions_field', (object) [
            'id'       => $fieldid,
            'name'     => $name,
            'type'     => $type,
            'options'  => $type === 'menu' ? $options : null,
            'required' => $required ? 1 : 0,
        ]);
    }

    /**
     * Deletes an optional field, and every answer previously given to it. Unlike
     * delete_track()/delete_submission_type(), a submission's existing answers are
     * deleted outright rather than left dangling: a confsubmissions_fieldval row without
     * its field has no name, type, or meaning left to display.
     *
     * @param int $fieldid The confsubmissions_field id
     * @return bool
     */
    public static function delete_field(int $fieldid): bool {
        global $DB;

        $DB->delete_records('confsubmissions_fieldval', ['fieldid' => $fieldid]);

        return $DB->delete_records('confsubmissions_field', ['id' => $fieldid]);
    }
}
