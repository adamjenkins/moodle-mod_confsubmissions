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

/**
 * Library functions for mod_confsubmissions.
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Returns the features this module supports.
 *
 * FEATURE_BACKUP_MOODLE2 is deliberately not claimed yet: no backup/restore
 * steps have been written for this plugin's tables. Claiming it without the
 * corresponding backup/moodle2/*.class.php files would cause course backups
 * to fail. Add the backup/restore steplibs before flipping this to true.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function confsubmissions_supports($feature) {
    return match ($feature) {
        FEATURE_MOD_INTRO        => true,
        FEATURE_SHOW_DESCRIPTION => true,
        FEATURE_BACKUP_MOODLE2   => false, // TODO: implement backup/restore steps, then set true.
        FEATURE_GRADE_HAS_GRADE  => false,
        FEATURE_MOD_PURPOSE      => MOD_PURPOSE_ASSESSMENT,
        default                  => null,
    };
}

/**
 * The machine names of the fixed optional fields that mod_form.php exposes as
 * toggles (confsubmissions_field.fieldname). These are not free-form custom
 * fields; the set is closed and shared by mod_form.php, submission_form.php,
 * and confsubmissions_field_optional_fields_from_form_data() below.
 *
 * @return string[]
 */
function confsubmissions_optional_fieldnames(): array {
    return ['language', 'teachingcontext', 'subtopic'];
}

/**
 * The curated, fixed set of icon keys organisers may pick for a track
 * (confsubmissions_track.icon). Deliberately a closed allow-list rather than free
 * text or an uploaded asset: letting organisers upload arbitrary SVGs/images would be
 * an XSS/file-safety risk, so tracks are themed with a small set of safe, built-in
 * Font Awesome icons instead. Each key maps to a Font Awesome class via
 * str_replace('_', '-', $key) (e.g. 'chart_bar' -> 'fa-chart-bar').
 *
 * @return string[] Icon keys
 */
function confsubmissions_track_icon_keys(): array {
    return [
        'book', 'code', 'chart_bar', 'users', 'lightbulb', 'graduation_cap', 'flask',
        'laptop_code', 'comments', 'globe', 'shield_halved', 'rocket', 'puzzle_piece',
        'microphone', 'camera', 'paintbrush', 'wrench', 'leaf',
    ];
}

/**
 * Returns the track icon picker's options, keyed by machine key, for use in a select
 * element. Includes a '' => "None" option first.
 *
 * @return array<string, string> Options keyed by icon key (or '' for none)
 */
function confsubmissions_track_icon_options(): array {
    $options = ['' => get_string('trackicon_none', 'mod_confsubmissions')];
    foreach (confsubmissions_track_icon_keys() as $key) {
        $options[$key] = get_string('trackicon_' . $key, 'mod_confsubmissions');
    }
    return $options;
}

/**
 * Renders a track's icon as a Font Awesome <i> tag, or an empty string if the track
 * has no icon (or an icon value outside the allow-list, which should never happen for
 * data written through classes/api.php, but is defensively re-checked here too since
 * this renders directly into HTML).
 *
 * @param string|null $icon The confsubmissions_track.icon value
 * @return string HTML, or ''
 */
function confsubmissions_render_track_icon(?string $icon): string {
    if (empty($icon) || !in_array($icon, confsubmissions_track_icon_keys(), true)) {
        return '';
    }

    $faclass = 'fa-' . str_replace('_', '-', $icon);
    return html_writer::tag('i', '', ['class' => 'icon fa ' . $faclass, 'aria-hidden' => 'true']);
}

/**
 * Upserts the confsubmissions_field rows for an instance from the mod_form.php
 * checkbox data (field_language, field_teachingcontext, field_subtopic).
 *
 * @param int $confsubmissionsid The confsubmissions instance id
 * @param stdClass $data Data from the settings form
 * @return void
 */
function confsubmissions_sync_optional_fields(int $confsubmissionsid, stdClass $data): void {
    global $DB;

    $sortorder = 0;
    foreach (confsubmissions_optional_fieldnames() as $fieldname) {
        $enabled = !empty($data->{'field_' . $fieldname}) ? 1 : 0;

        $existing = $DB->get_record('confsubmissions_field', [
            'confsubmissions' => $confsubmissionsid,
            'fieldname'       => $fieldname,
        ]);

        if ($existing) {
            $existing->enabled   = $enabled;
            $existing->sortorder = $sortorder;
            $DB->update_record('confsubmissions_field', $existing);
        } else {
            $DB->insert_record('confsubmissions_field', (object) [
                'confsubmissions' => $confsubmissionsid,
                'fieldname'       => $fieldname,
                'enabled'         => $enabled,
                'sortorder'       => $sortorder,
            ]);
        }

        $sortorder++;
    }
}

/**
 * Adds a new instance of the confsubmissions activity.
 *
 * @param stdClass $data Data from the settings form
 * @param mod_confsubmissions_mod_form|null $form The form instance
 * @return int The id of the newly inserted record
 */
function confsubmissions_add_instance(stdClass $data, ?mod_confsubmissions_mod_form $form = null) {
    global $DB;

    $data->timecreated  = time();
    $data->timemodified = $data->timecreated;

    if (!isset($data->intro)) {
        $data->intro = '';
    }
    if (!isset($data->introformat)) {
        $data->introformat = FORMAT_HTML;
    }

    $id = $DB->insert_record('confsubmissions', $data);

    confsubmissions_sync_optional_fields($id, $data);

    return $id;
}

/**
 * Updates an existing instance of the confsubmissions activity.
 *
 * @param stdClass $data Data from the settings form
 * @param mod_confsubmissions_mod_form|null $form The form instance
 * @return bool
 */
function confsubmissions_update_instance(stdClass $data, ?mod_confsubmissions_mod_form $form = null) {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;

    $result = $DB->update_record('confsubmissions', $data);

    confsubmissions_sync_optional_fields($data->id, $data);

    return $result;
}

/**
 * Deletes an instance of the confsubmissions activity and all associated data.
 *
 * @param int $id The instance id
 * @return bool
 */
function confsubmissions_delete_instance($id) {
    global $DB;

    if (!$confsubmissions = $DB->get_record('confsubmissions', ['id' => $id])) {
        return false;
    }

    $submissionids = $DB->get_fieldset_select(
        'confsubmissions_submission',
        'id',
        'confsubmissions = ?',
        [$id]
    );

    if ($submissionids) {
        [$insql, $params] = $DB->get_in_or_equal($submissionids);
        $DB->delete_records_select('confsubmissions_fieldval', "submissionid $insql", $params);
        $DB->delete_records_select('confsubmissions_speaker', "submissionid $insql", $params);
    }

    $DB->delete_records('confsubmissions_submission', ['confsubmissions' => $id]);
    $DB->delete_records('confsubmissions_field', ['confsubmissions' => $id]);
    $DB->delete_records('confsubmissions_track', ['confsubmissions' => $id]);
    $DB->delete_records('confsubmissions_submissiontype', ['confsubmissions' => $id]);

    $DB->delete_records('confsubmissions', ['id' => $id]);

    return true;
}

/**
 * Adds navigation nodes for this activity to the course navigation tree.
 *
 * A "My submissions" / "All submissions" split is rendered directly in
 * view.php. The only extra node added here is a link to track management,
 * for users who hold mod/confsubmissions:managetracks.
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course object
 * @param stdClass $module The module instance record
 * @param cm_info $cm The course-module object
 */
function confsubmissions_extend_navigation(navigation_node $navigation, stdClass $course, stdClass $module, cm_info $cm) {
    $context = context_module::instance($cm->id);

    if (has_capability('mod/confsubmissions:managetracks', $context)) {
        $navigation->add(
            get_string('managetracks', 'mod_confsubmissions'),
            new moodle_url('/mod/confsubmissions/tracks.php', ['id' => $cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'confsubmissionstracks'
        );
    }
}
