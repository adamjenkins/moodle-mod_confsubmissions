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
 * Optional-field management screen for mod_confsubmissions.
 *
 * Optional fields are organiser-named, organiser-typed extra questions shown
 * on the submission form (e.g. "Company/affiliation" as text, "Preferred
 * session length" as a dropdown) -- managed on their own screen for the same
 * reason tracks.php's tracks are: FK'd to the instance id, so organisers need
 * to add/remove them both before and after the settings form has been saved.
 *
 * Revision round 1 follow-up (2026-07-04): replaces a fixed set of three
 * checkboxes (language/teaching context/sub-topic) that previously lived
 * directly in mod_form.php.
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/confsubmissions/lib.php');

use mod_confsubmissions\api;
use mod_confsubmissions\form\field_form;

$id = required_param('id', PARAM_INT);
$deleteid = optional_param('delete', 0, PARAM_INT);
$editid = optional_param('edit', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'confsubmissions');
$confsubmissions = $DB->get_record('confsubmissions', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/confsubmissions:managetracks', $context);

$pageurl = new moodle_url('/mod/confsubmissions/fields.php', ['id' => $cm->id]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($confsubmissions->name) . ': ' . get_string('managefields', 'mod_confsubmissions'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Handle deletion, confirmed via sesskey on every state-changing request.
if ($deleteid) {
    require_sesskey();

    $field = $DB->get_record('confsubmissions_field', ['id' => $deleteid, 'confsubmissions' => $confsubmissions->id]);
    if (!$field) {
        throw new \moodle_exception('invalidrecord', 'error', '', 'confsubmissions_field');
    }

    if (!$confirm) {
        echo $OUTPUT->header();
        echo $OUTPUT->heading(format_string($confsubmissions->name), 2);
        echo $OUTPUT->confirm(
            get_string('confirmdeletefield', 'mod_confsubmissions', format_string($field->name)),
            new moodle_url($pageurl, ['delete' => $deleteid, 'confirm' => 1, 'sesskey' => sesskey()]),
            $pageurl
        );
        echo $OUTPUT->footer();
        exit;
    }

    api::delete_field($deleteid);
    redirect($pageurl, get_string('fielddeleted', 'mod_confsubmissions'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// When editing, the field must belong to this instance - the same instance-scoping
// IDOR check already applied to deletion above.
$editfield = null;
if ($editid) {
    $editfield = $DB->get_record('confsubmissions_field', ['id' => $editid, 'confsubmissions' => $confsubmissions->id]);
    if (!$editfield) {
        throw new \moodle_exception('invalidrecord', 'error', '', 'confsubmissions_field');
    }
}

// Handle the add/edit mini-form (shared between both modes; 'fieldid' is 0 when adding).
$fieldform = new field_form($pageurl, ['editing' => (bool) $editid]);

if ($editfield) {
    $fieldform->set_data((object) [
        'fieldid'  => $editfield->id,
        'name'     => $editfield->name,
        'type'     => $editfield->type,
        'options'  => $editfield->options,
        'required' => $editfield->required,
    ]);
}

if ($fieldform->is_cancelled()) {
    redirect($pageurl);
} else if ($formdata = $fieldform->get_data()) {
    $fieldid = (int) ($formdata->fieldid ?? 0);
    $options = $formdata->type === 'menu' ? $formdata->options : null;

    if ($fieldid) {
        // Re-verify server-side that the submitted id still belongs to this instance,
        // even though the hidden field was originally populated from a checked lookup:
        // it is client-supplied and must never be trusted on its own (the same
        // instance-scoping IDOR pattern used for deletion above).
        $existing = $DB->get_record('confsubmissions_field', ['id' => $fieldid, 'confsubmissions' => $confsubmissions->id]);
        if (!$existing) {
            throw new \moodle_exception('invalidrecord', 'error', '', 'confsubmissions_field');
        }
        api::update_field($fieldid, $formdata->name, $formdata->type, $options, !empty($formdata->required));
        redirect($pageurl, get_string('fieldupdated', 'mod_confsubmissions'), null, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        api::add_field($confsubmissions->id, $formdata->name, $formdata->type, $options, !empty($formdata->required));
        redirect($pageurl, get_string('fieldadded', 'mod_confsubmissions'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($confsubmissions->name), 2);
echo $OUTPUT->heading(get_string('managefields', 'mod_confsubmissions'), 3);

$fields = api::get_fields($confsubmissions->id);

if ($fields) {
    echo $OUTPUT->heading(get_string('fieldlist', 'mod_confsubmissions'), 4);

    $table = new html_table();
    $table->head = [
        get_string('fieldlabel', 'mod_confsubmissions'),
        get_string('fieldtype', 'mod_confsubmissions'),
        get_string('fieldrequired', 'mod_confsubmissions'),
        '',
    ];
    $table->attributes['class'] = 'generaltable';

    foreach ($fields as $field) {
        $editurl = new moodle_url($pageurl, ['edit' => $field->id]);
        $editlink = $OUTPUT->action_icon(
            $editurl,
            new pix_icon('t/edit', get_string('editfield', 'mod_confsubmissions')),
            null,
            ['title' => get_string('editfield', 'mod_confsubmissions')]
        );

        $deleteurl = new moodle_url($pageurl, ['delete' => $field->id, 'sesskey' => sesskey()]);
        $deletelink = $OUTPUT->action_icon(
            $deleteurl,
            new pix_icon('t/delete', get_string('delete')),
            null,
            ['title' => get_string('delete')]
        );

        $table->data[] = [
            format_string($field->name),
            get_string('fieldtype_' . $field->type, 'mod_confsubmissions'),
            $field->required ? get_string('yes') : get_string('no'),
            $editlink . ' ' . $deletelink,
        ];
    }

    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('nofields', 'mod_confsubmissions'), 'info');
}

echo $OUTPUT->heading(
    $editid ? get_string('editfield', 'mod_confsubmissions') : get_string('addfield', 'mod_confsubmissions'),
    4
);
$fieldform->display();

echo $OUTPUT->footer();
