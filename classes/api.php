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
    /** @var string[] Valid values for confsubmissions_submission.status. */
    public const VALID_STATUSES = ['submitted', 'accepted', 'rejected', 'withdrawn'];

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
     * Returns many submission records in one query, keyed by id.
     *
     * Bulk companion to get_submission(), added (2026-07-09) so downstream
     * consumers that decorate whole lists (mod_confprogram's Display-phase list,
     * mod_confscheduler's grid payload) don't have to issue one query per row.
     * A requested id with no matching record is simply absent from the result --
     * callers must tolerate that the same way they tolerate get_submission()
     * returning false.
     *
     * @param int[] $submissionids The confsubmissions_submission ids
     * @return \stdClass[] Submission records keyed by id (missing ids omitted)
     */
    public static function get_submissions(array $submissionids): array {
        global $DB;

        $submissionids = array_values(array_unique(array_map('intval', $submissionids)));
        if (!$submissionids) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($submissionids);
        return $DB->get_records_select('confsubmissions_submission', "id $insql", $params);
    }

    /**
     * Whether $userid may edit $submission via edit.php: either they own it and
     * hold mod/confsubmissions:submit, or they hold mod/confsubmissions:editany
     * regardless of ownership (user request, 2026-07-07 -- "editing teachers should
     * also be able to edit any submission... especially the selected track").
     *
     * @param \stdClass $submission The confsubmissions_submission record
     * @param \context $context The confsubmissions module context
     * @param int|null $userid The user to check; defaults to the current $USER
     * @return bool
     */
    public static function can_edit_submission(\stdClass $submission, \context $context, ?int $userid = null): bool {
        global $USER;

        $userid = $userid ?? (int) $USER->id;

        if ((int) $submission->userid === $userid) {
            return has_capability('mod/confsubmissions:submit', $context, $userid);
        }

        return has_capability('mod/confsubmissions:editany', $context, $userid);
    }

    /**
     * Sets a submission's workflow status. Called by mod_confprogram to keep this
     * plugin's own status column in sync with Accept/Reject decisions -- see that
     * plugin's classes/api.php::record_decision() docblock for why this is only ever
     * called once a decision is no longer Display-phase-embargoed, never at the
     * moment a decision is first recorded during Review phase (a submitter's own "my
     * submissions" view shows this status directly, so syncing it early would leak an
     * embargoed decision).
     *
     * @param int $submissionid The confsubmissions_submission id
     * @param string $status One of VALID_STATUSES
     * @return void
     * @throws \invalid_parameter_exception if $status is not one of VALID_STATUSES
     */
    public static function set_status(int $submissionid, string $status): void {
        global $DB;

        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new \invalid_parameter_exception(get_string('error:invalidstatus', 'mod_confsubmissions'));
        }

        // Capture the status a submission had immediately before being withdrawn, so
        // Unwithdraw (view.php) can restore it instead of hardcoding 'submitted'.
        // Cleared again on any transition away from 'withdrawn' -- it's only ever
        // meaningful while the row is currently withdrawn.
        $update = (object) [
            'id'           => $submissionid,
            'status'       => $status,
            'timemodified' => time(),
        ];
        if ($status === 'withdrawn') {
            $current = $DB->get_field('confsubmissions_submission', 'status', ['id' => $submissionid], MUST_EXIST);
            $update->statusbeforewithdraw = $current;
        } else {
            $update->statusbeforewithdraw = null;
        }

        // Use update_record(), not set_field(): a status change is a genuine modification
        // a submitter should see reflected in their own "my submissions" list (which
        // shows timemodified), not a silent backend touch -- caught by a
        // moodle-reviewer pass.
        $DB->update_record('confsubmissions_submission', $update);

        // A "withdrawn" status is only ever set here from view.php's own
        // user-initiated withdraw action -- mod_confprogram's decision-sync call
        // above only ever passes "accepted"/"rejected" (VALID_STATUSES has no
        // "waitlisted" entry; waitlist is a confprogram_decision-only concept), so
        // this can never double-fire the withdrawal notification for an unrelated
        // status sync.
        if ($status === 'withdrawn') {
            \mod_confsubmissions\local\notifier::notify_submission_withdrawn($submissionid);
        }
    }

    /**
     * Permanently deletes a submission and everything attached to it (speakers,
     * optional-field answers).
     *
     * This is a hard delete, unlike set_status(mode: 'withdrawn') -- restricted by
     * capability (mod/confsubmissions:deleteany, manager-only by default; see
     * db/access.php) to organisers who genuinely want the record gone, as opposed to
     * a submitter's own reversible "Withdraw" action.
     *
     * Known limitation: this does not reach into mod_confprogram (any decision or
     * review referencing this submissionid becomes orphaned -- the same
     * no-shared-library, no-cross-plugin-cascade posture already documented in
     * RELATIONS.md for every other cross-plugin id reference in this project).
     *
     * @param int $submissionid The confsubmissions_submission id
     * @return void
     */
    public static function delete_submission(int $submissionid): void {
        global $DB;

        $DB->delete_records('confsubmissions_speaker', ['submissionid' => $submissionid]);
        $DB->delete_records('confsubmissions_fieldval', ['submissionid' => $submissionid]);
        $DB->delete_records('confsubmissions_datepref', ['submissionid' => $submissionid]);
        $DB->delete_records('confsubmissions_submission', ['id' => $submissionid]);
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
     * Returns the speakers for many submissions in one query.
     *
     * Bulk companion to get_speakers(), added (2026-07-09) for the same
     * list-decoration consumers as get_submissions() above. Every requested id
     * is present in the result (as an empty array when the submission has no
     * speakers), so callers can index without isset() checks.
     *
     * @param int[] $submissionids The confsubmissions_submission ids
     * @return array Map of submissionid => speaker records in sort order
     */
    public static function get_speakers_for_submissions(array $submissionids): array {
        global $DB;

        $submissionids = array_values(array_unique(array_map('intval', $submissionids)));
        if (!$submissionids) {
            return [];
        }

        $result = array_fill_keys($submissionids, []);
        [$insql, $params] = $DB->get_in_or_equal($submissionids);
        $speakers = $DB->get_records_select(
            'confsubmissions_speaker',
            "submissionid $insql",
            $params,
            'submissionid ASC, sortorder ASC'
        );
        foreach ($speakers as $speaker) {
            $result[(int) $speaker->submissionid][] = $speaker;
        }

        return $result;
    }

    /**
     * Returns ready-to-display speaker names for many submissions in one query.
     *
     * The single shared resolver every caller across this plugin and its
     * downstream consumers (mod_confprogram, mod_confscheduler) should use
     * instead of independently re-implementing "resolve userid -> fullname(),
     * fall back to the manually-entered name, never show the raw id" — that
     * logic was previously duplicated 6+ times across the plugin suite, which
     * is exactly the shape of bug that regresses silently (a new/edited call
     * site forgetting the core_user lookup or its fallback). Every requested
     * id is present in the result (as an empty array when the submission has
     * no speakers), so callers can index without isset() checks.
     *
     * @param int[] $submissionids The confsubmissions_submission ids
     * @return array<int, string[]> Map of submissionid => ordered list of display names
     */
    public static function get_speaker_display_names(array $submissionids): array {
        global $DB;

        $submissionids = array_values(array_unique(array_map('intval', $submissionids)));
        if (!$submissionids) {
            return [];
        }

        $speakersbysubmission = self::get_speakers_for_submissions($submissionids);

        $userids = [];
        foreach ($speakersbysubmission as $speakers) {
            foreach ($speakers as $speaker) {
                if (!empty($speaker->userid)) {
                    $userids[] = (int) $speaker->userid;
                }
            }
        }

        $users = [];
        if ($userids) {
            $namefields = implode(', ', array_merge(['id'], \core_user\fields::for_name()->get_required_fields()));
            $users = $DB->get_records_list('user', 'id', array_unique($userids), '', $namefields);
        }

        $result = array_fill_keys($submissionids, []);
        foreach ($speakersbysubmission as $submissionid => $speakers) {
            $names = [];
            foreach ($speakers as $speaker) {
                if (!empty($speaker->userid)) {
                    if (isset($users[(int) $speaker->userid])) {
                        $names[] = fullname($users[(int) $speaker->userid]);
                    }
                } else if (!empty($speaker->name)) {
                    $names[] = format_string($speaker->name, true, ['escape' => false]);
                }
            }
            $result[$submissionid] = $names;
        }

        return $result;
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
     * Returns the optional-field answers for many submissions in one query.
     *
     * Bulk companion to get_optional_field_values(), added (2026-07-09) for
     * list-decoration consumers (see get_submissions() above). Every requested
     * id is present in the result (as an empty array when the submission has
     * no answers).
     *
     * @param int[] $submissionids The confsubmissions_submission ids
     * @return array Map of submissionid => (fieldid => value)
     */
    public static function get_optional_field_values_for_submissions(array $submissionids): array {
        global $DB;

        $submissionids = array_values(array_unique(array_map('intval', $submissionids)));
        if (!$submissionids) {
            return [];
        }

        $result = array_fill_keys($submissionids, []);
        [$insql, $params] = $DB->get_in_or_equal($submissionids);
        $values = $DB->get_records_select('confsubmissions_fieldval', "submissionid $insql", $params);
        foreach ($values as $value) {
            $result[(int) $value->submissionid][(int) $value->fieldid] = $value->value;
        }

        return $result;
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
     * Returns the day range a confsubmissions instance's conference dates span, as
     * local-midnight timestamps, one per calendar day (inclusive of both endpoints).
     *
     * Consumed by the submission form to generate one "preferred date" checkbox per
     * day, and by mod_confscheduler's autoscheduler to validate a submitted
     * preference still falls within the current range.
     *
     * @param \stdClass $confsubmissions The confsubmissions instance record
     * @return int[] Midnight timestamps, oldest first; empty if either date is unset
     */
    public static function get_conference_days(\stdClass $confsubmissions): array {
        if (empty($confsubmissions->conferencestart) || empty($confsubmissions->conferenceend)) {
            return [];
        }

        $days = [];
        $current = usergetmidnight((int) $confsubmissions->conferencestart);
        $end = (int) $confsubmissions->conferenceend;
        // Cap iterations defensively: a organiser typo (e.g. a start/end date decades
        // apart) should not be able to hang this in an unbounded loop.
        for ($i = 0; $i < 366 && $current <= $end; $i++) {
            $days[] = $current;
            $current = usergetmidnight(strtotime('+1 day', $current));
        }

        return $days;
    }

    /**
     * Returns a submission's preferred conference days, as local-midnight timestamps.
     *
     * An empty array means no preference was ever recorded for this submission --
     * callers (e.g. mod_confscheduler's autoscheduler and unscheduled-panel filter)
     * MUST treat that as "any day is acceptable", not "no day is acceptable" -- most
     * submissions predate this feature, or belong to an instance that never enabled
     * offerpreferreddates, and should not be penalised or hidden as a result.
     *
     * @param int $submissionid The confsubmissions_submission id
     * @return int[] Preferred-day midnight timestamps
     */
    public static function get_date_preferences(int $submissionid): array {
        global $DB;

        // Get_records_menu() returns raw DB column values, which are strings even for
        // an int column -- cast explicitly so a strict in_array()/comparison against
        // api::get_conference_days()'s genuinely-int timestamps (e.g. in the
        // submission form and mod_confscheduler's autoscheduler) does not silently
        // fail (caught live: every "preferred date" checkbox rendered unchecked on
        // reload despite having been saved correctly, because '1788390000' !==
        // 1788390000 under strict comparison).
        return array_map('intval', array_values($DB->get_records_menu(
            'confsubmissions_datepref',
            ['submissionid' => $submissionid],
            'prefdate ASC',
            'id, prefdate'
        )));
    }

    /**
     * Returns the preferred-day timestamps for many submissions in one query.
     *
     * Bulk companion to get_date_preferences(), added (2026-07-09) for
     * mod_confscheduler's grid payload (see get_submissions() above). Every
     * requested id is present in the result; an empty array carries the same
     * "any day is acceptable" meaning documented on get_date_preferences().
     *
     * @param int[] $submissionids The confsubmissions_submission ids
     * @return array Map of submissionid => int[] preferred-day midnight timestamps
     */
    public static function get_date_preferences_for_submissions(array $submissionids): array {
        global $DB;

        $submissionids = array_values(array_unique(array_map('intval', $submissionids)));
        if (!$submissionids) {
            return [];
        }

        $result = array_fill_keys($submissionids, []);
        [$insql, $params] = $DB->get_in_or_equal($submissionids);
        $prefs = $DB->get_records_select(
            'confsubmissions_datepref',
            "submissionid $insql",
            $params,
            'submissionid ASC, prefdate ASC'
        );
        foreach ($prefs as $pref) {
            $result[(int) $pref->submissionid][] = (int) $pref->prefdate;
        }

        return $result;
    }

    /**
     * Replaces a submission's preferred conference days with a new set.
     *
     * @param int $submissionid The confsubmissions_submission id
     * @param int[] $prefdates Midnight timestamps of the days to prefer
     * @return void
     */
    public static function sync_date_preferences(int $submissionid, array $prefdates): void {
        global $DB;

        $DB->delete_records('confsubmissions_datepref', ['submissionid' => $submissionid]);

        foreach (array_unique(array_map('intval', $prefdates)) as $prefdate) {
            $DB->insert_record('confsubmissions_datepref', (object) [
                'submissionid' => $submissionid,
                'prefdate'     => $prefdate,
            ]);
        }
    }

    /**
     * Returns the conference days an instance has org-wide disabled for a regular
     * submitter's preferred-date checkboxes (user feedback, 2026-07-05). A user with
     * mod/confsubmissions:manageform (editingteacher+) is not subject to this list --
     * see submission_form.php's definition(), which renders a disabled day's checkbox
     * greyed out and forced unchecked (not removed from the list) for any caller that
     * did not pass its 'showalldays' customdata flag.
     *
     * confsubmissions.disableddates stores a JSON array of {date, reason} objects
     * (changed from a plain comma-separated timestamp list, user request,
     * 2026-07-09 -- see db/upgrade.php's 2026070800 step for the one-time migration
     * of existing data into this shape, and get_disabled_date_reasons() below for
     * the reason half of each entry). This method only ever returns the plain
     * timestamp list, unchanged in shape from before that change, so every existing
     * caller that only cares "is this day disabled" needed no changes.
     *
     * @param \stdClass $confsubmissions The confsubmissions instance record
     * @return int[] Midnight timestamps of the disabled days
     */
    public static function get_disabled_dates(\stdClass $confsubmissions): array {
        return array_keys(self::get_disabled_date_entries($confsubmissions));
    }

    /**
     * Returns the reason text attached to each org-wide disabled preferred-date day
     * (user request, 2026-07-09), for the days that actually have one -- a day
     * disabled with no reason given (the optional field left blank) simply has no
     * entry here, so callers can use `$reasons[$day] ?? null`. See
     * get_disabled_dates()'s own docblock for the underlying storage format.
     *
     * @param \stdClass $confsubmissions The confsubmissions instance record
     * @return array<int, string> Non-empty reason text keyed by midnight timestamp
     */
    public static function get_disabled_date_reasons(\stdClass $confsubmissions): array {
        return array_filter(self::get_disabled_date_entries($confsubmissions), fn($reason) => $reason !== '');
    }

    /**
     * Decodes confsubmissions.disableddates' JSON-array-of-{date,reason} storage
     * into a flat date => reason map, shared by get_disabled_dates() and
     * get_disabled_date_reasons() above. A malformed/unparseable value (should not
     * happen post-migration, but a corrupt or hand-edited row must never fatal a
     * page load) degrades to "no disabled dates" rather than throwing.
     *
     * @param \stdClass $confsubmissions The confsubmissions instance record
     * @return array<int, string> Reason text (possibly '') keyed by midnight timestamp
     */
    private static function get_disabled_date_entries(\stdClass $confsubmissions): array {
        if (empty($confsubmissions->disableddates)) {
            return [];
        }

        $decoded = json_decode($confsubmissions->disableddates, true);
        if (!is_array($decoded)) {
            return [];
        }

        $entries = [];
        foreach ($decoded as $entry) {
            if (!isset($entry['date'])) {
                continue;
            }
            $entries[(int) $entry['date']] = (string) ($entry['reason'] ?? '');
        }

        ksort($entries);

        return $entries;
    }

    /**
     * Replaces an instance's org-wide disabled preferred-date days (and their
     * optional reasons, user request, 2026-07-09) with a new set.
     *
     * @param int $confsubmissionsid The confsubmissions instance id
     * @param array<int, string> $datestoreasons Reason text (may be '' for "no
     *     reason given"), keyed by midnight timestamp, for every day to disable
     * @return void
     */
    public static function set_disabled_dates(int $confsubmissionsid, array $datestoreasons): void {
        global $DB;

        $normalised = [];
        foreach ($datestoreasons as $date => $reason) {
            $normalised[(int) $date] = trim((string) $reason);
        }
        ksort($normalised);

        $encoded = null;
        if ($normalised) {
            $entries = [];
            foreach ($normalised as $date => $reason) {
                $entries[] = ['date' => $date, 'reason' => $reason];
            }
            $encoded = json_encode($entries);
        }

        $DB->set_field('confsubmissions', 'disableddates', $encoded, ['id' => $confsubmissionsid]);
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
     * A field's TYPE can only change while it has no stored answers: an answer is
     * stored as an opaque string whose meaning depends on the type it was captured
     * under (a 'date' answer is a unix timestamp, a 'checkbox' answer is '0'/'1'),
     * so re-typing a field with live data silently reinterprets every existing
     * answer -- e.g. text answers rendered through userdate() as the epoch.
     *
     * @param int $fieldid The confsubmissions_field id
     * @param string $name The field's display label
     * @param string $type One of confsubmissions_field_types()
     * @param string|null $options Newline-separated choices; only meaningful when $type is 'menu'
     * @param bool $required Whether a presenter must answer this field
     * @return void
     * @throws \invalid_parameter_exception if $type is invalid, $type is 'menu' with no choices,
     *         or $type differs from the stored type while answers exist for the field
     */
    public static function update_field(int $fieldid, string $name, string $type, ?string $options, bool $required): void {
        global $DB;

        self::validate_field_type($type);
        if ($type === 'menu') {
            self::validate_field_menu_options($options);
        }

        $existing = $DB->get_record('confsubmissions_field', ['id' => $fieldid], '*', MUST_EXIST);
        if (
            $existing->type !== $type
                && $DB->record_exists('confsubmissions_fieldval', ['fieldid' => $fieldid])
        ) {
            throw new \invalid_parameter_exception(
                get_string('error:fieldtypechangehasvalues', 'mod_confsubmissions')
            );
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
