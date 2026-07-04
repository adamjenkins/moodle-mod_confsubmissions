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
 * Submission type management screen for mod_confsubmissions.
 *
 * Submission types (e.g. Lightning Talk, Workshop), each with a default
 * presentation duration in minutes, are managed on their own screen for the
 * same reason tracks.php's tracks are: FK'd to the instance id, so organisers
 * need to add/remove them both before and after the settings form has been
 * saved.
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/confsubmissions/lib.php');

use mod_confsubmissions\api;
use mod_confsubmissions\form\submissiontype_form;

$id = required_param('id', PARAM_INT);
$deleteid = optional_param('delete', 0, PARAM_INT);
$editid = optional_param('edit', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'confsubmissions');
$confsubmissions = $DB->get_record('confsubmissions', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/confsubmissions:managetracks', $context);

$pageurl = new moodle_url('/mod/confsubmissions/submissiontypes.php', ['id' => $cm->id]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($confsubmissions->name) . ': ' . get_string('managesubmissiontypes', 'mod_confsubmissions'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Handle deletion, confirmed via sesskey on every state-changing request.
if ($deleteid) {
    require_sesskey();

    $submissiontype = $DB->get_record(
        'confsubmissions_submissiontype',
        ['id' => $deleteid, 'confsubmissions' => $confsubmissions->id]
    );
    if (!$submissiontype) {
        throw new \moodle_exception('invalidrecord', 'error', '', 'confsubmissions_submissiontype');
    }

    if (!$confirm) {
        echo $OUTPUT->header();
        echo $OUTPUT->heading(format_string($confsubmissions->name), 2);
        echo $OUTPUT->confirm(
            get_string('confirmdeletesubmissiontype', 'mod_confsubmissions', format_string($submissiontype->name)),
            new moodle_url($pageurl, ['delete' => $deleteid, 'confirm' => 1, 'sesskey' => sesskey()]),
            $pageurl
        );
        echo $OUTPUT->footer();
        exit;
    }

    api::delete_submission_type($deleteid);
    redirect(
        $pageurl,
        get_string('submissiontypedeleted', 'mod_confsubmissions'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// When editing, the submission type must belong to this instance - the same
// instance-scoping IDOR check already applied to deletion above.
$edittype = null;
if ($editid) {
    $edittype = $DB->get_record(
        'confsubmissions_submissiontype',
        ['id' => $editid, 'confsubmissions' => $confsubmissions->id]
    );
    if (!$edittype) {
        throw new \moodle_exception('invalidrecord', 'error', '', 'confsubmissions_submissiontype');
    }
}

// Handle the add/edit mini-form (shared between both modes; 'submissiontypeid' is 0 when adding).
$typeform = new submissiontype_form($pageurl, ['editing' => (bool) $editid]);

if ($edittype) {
    $typeform->set_data((object) [
        'submissiontypeid' => $edittype->id,
        'name'             => $edittype->name,
        'durationminutes'  => $edittype->durationminutes,
    ]);
}

if ($typeform->is_cancelled()) {
    redirect($pageurl);
} else if ($formdata = $typeform->get_data()) {
    $submissiontypeid = (int) ($formdata->submissiontypeid ?? 0);

    if ($submissiontypeid) {
        // Re-verify server-side that the submitted id still belongs to this instance,
        // even though the hidden field was originally populated from a checked lookup:
        // it is client-supplied and must never be trusted on its own (the same
        // instance-scoping IDOR pattern used for deletion above).
        $existing = $DB->get_record(
            'confsubmissions_submissiontype',
            ['id' => $submissiontypeid, 'confsubmissions' => $confsubmissions->id]
        );
        if (!$existing) {
            throw new \moodle_exception('invalidrecord', 'error', '', 'confsubmissions_submissiontype');
        }
        api::update_submission_type($submissiontypeid, $formdata->name, (int) $formdata->durationminutes);
        redirect(
            $pageurl,
            get_string('submissiontypeupdated', 'mod_confsubmissions'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } else {
        api::add_submission_type($confsubmissions->id, $formdata->name, (int) $formdata->durationminutes);
        redirect(
            $pageurl,
            get_string('submissiontypeadded', 'mod_confsubmissions'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($confsubmissions->name), 2);
echo $OUTPUT->heading(get_string('managesubmissiontypes', 'mod_confsubmissions'), 3);

$submissiontypes = api::get_submission_types($cm->id);

if ($submissiontypes) {
    echo $OUTPUT->heading(get_string('submissiontypelist', 'mod_confsubmissions'), 4);

    $table = new html_table();
    $table->head = [
        get_string('submissiontypename', 'mod_confsubmissions'),
        get_string('submissiontypeduration', 'mod_confsubmissions'),
        '',
    ];
    $table->attributes['class'] = 'generaltable';

    foreach ($submissiontypes as $submissiontype) {
        $editurl = new moodle_url($pageurl, ['edit' => $submissiontype->id]);
        $editlink = $OUTPUT->action_icon(
            $editurl,
            new pix_icon('t/edit', get_string('editsubmissiontype', 'mod_confsubmissions')),
            null,
            ['title' => get_string('editsubmissiontype', 'mod_confsubmissions')]
        );

        $deleteurl = new moodle_url($pageurl, ['delete' => $submissiontype->id, 'sesskey' => sesskey()]);
        $deletelink = $OUTPUT->action_icon(
            $deleteurl,
            new pix_icon('t/delete', get_string('delete')),
            null,
            ['title' => get_string('delete')]
        );

        $table->data[] = [
            format_string($submissiontype->name),
            get_string('numminutes', 'moodle', (int) $submissiontype->durationminutes),
            $editlink . ' ' . $deletelink,
        ];
    }

    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('nosubmissiontypes', 'mod_confsubmissions'), 'info');
}

echo $OUTPUT->heading(
    $editid ? get_string('editsubmissiontype', 'mod_confsubmissions') : get_string('addsubmissiontype', 'mod_confsubmissions'),
    4
);
$typeform->display();

echo $OUTPUT->footer();
