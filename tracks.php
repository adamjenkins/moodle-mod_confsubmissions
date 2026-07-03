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
 * Track management screen for mod_confsubmissions.
 *
 * Tracks are managed on their own screen, separate from the activity settings
 * form, because they are FK'd to the instance id (so it must already exist)
 * and organisers need to add/remove them both before and after the settings
 * form has been saved.
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/confsubmissions/lib.php');

use mod_confsubmissions\api;
use mod_confsubmissions\form\track_form;

$id = required_param('id', PARAM_INT);
$deleteid = optional_param('delete', 0, PARAM_INT);
$editid = optional_param('edit', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'confsubmissions');
$confsubmissions = $DB->get_record('confsubmissions', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/confsubmissions:managetracks', $context);

$pageurl = new moodle_url('/mod/confsubmissions/tracks.php', ['id' => $cm->id]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($confsubmissions->name) . ': ' . get_string('managetracks', 'mod_confsubmissions'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Handle deletion, confirmed via sesskey on every state-changing request.
if ($deleteid) {
    require_sesskey();

    $track = $DB->get_record('confsubmissions_track', ['id' => $deleteid, 'confsubmissions' => $confsubmissions->id]);
    if (!$track) {
        throw new \moodle_exception('invalidrecord', 'error', '', 'confsubmissions_track');
    }

    if (!$confirm) {
        echo $OUTPUT->header();
        echo $OUTPUT->heading(format_string($confsubmissions->name), 2);
        echo $OUTPUT->confirm(
            get_string('confirmdeletetrack', 'mod_confsubmissions', format_string($track->name)),
            new moodle_url($pageurl, ['delete' => $deleteid, 'confirm' => 1, 'sesskey' => sesskey()]),
            $pageurl
        );
        echo $OUTPUT->footer();
        exit;
    }

    api::delete_track($deleteid);
    redirect($pageurl, get_string('trackdeleted', 'mod_confsubmissions'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// When editing, the track must belong to this instance - the same instance-scoping
// IDOR check already applied to deletion above.
$edittrack = null;
if ($editid) {
    $edittrack = $DB->get_record('confsubmissions_track', ['id' => $editid, 'confsubmissions' => $confsubmissions->id]);
    if (!$edittrack) {
        throw new \moodle_exception('invalidrecord', 'error', '', 'confsubmissions_track');
    }
}

// Handle the add/edit-track mini-form (shared between both modes; 'trackid' is 0 when adding).
$trackform = new track_form($pageurl, ['editing' => (bool) $editid]);

if ($edittrack) {
    $trackform->set_data((object) [
        'trackid' => $edittrack->id,
        'name'    => $edittrack->name,
        'colour'  => $edittrack->colour,
        'icon'    => $edittrack->icon,
    ]);
}

if ($trackform->is_cancelled()) {
    redirect($pageurl);
} else if ($formdata = $trackform->get_data()) {
    $trackid = (int) ($formdata->trackid ?? 0);

    if ($trackid) {
        // Re-verify server-side that the submitted id still belongs to this instance,
        // even though the hidden field was originally populated from a checked lookup:
        // it is client-supplied and must never be trusted on its own (the same
        // instance-scoping IDOR pattern used for deletion above).
        $existing = $DB->get_record('confsubmissions_track', ['id' => $trackid, 'confsubmissions' => $confsubmissions->id]);
        if (!$existing) {
            throw new \moodle_exception('invalidrecord', 'error', '', 'confsubmissions_track');
        }
        api::update_track($trackid, $formdata->name, $formdata->colour ?: null, $formdata->icon ?: null);
        redirect($pageurl, get_string('trackupdated', 'mod_confsubmissions'), null, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        api::add_track($confsubmissions->id, $formdata->name, $formdata->colour ?: null, $formdata->icon ?: null);
        redirect($pageurl, get_string('trackadded', 'mod_confsubmissions'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($confsubmissions->name), 2);
echo $OUTPUT->heading(get_string('managetracks', 'mod_confsubmissions'), 3);

$tracks = api::get_tracks($cm->id);

if ($tracks) {
    echo $OUTPUT->heading(get_string('tracklist', 'mod_confsubmissions'), 4);

    $table = new html_table();
    $table->head = [
        get_string('trackname', 'mod_confsubmissions'),
        get_string('trackcolour', 'mod_confsubmissions'),
        get_string('trackicon', 'mod_confsubmissions'),
        '',
    ];
    $table->attributes['class'] = 'generaltable';

    foreach ($tracks as $track) {
        $swatch = '';
        if (!empty($track->colour)) {
            // The track's colour is validated (regex ^#[0-9a-fA-F]{6}$) by classes/api.php
            // before ever being stored, so it is safe to interpolate directly into an
            // inline style.
            $swatch = html_writer::tag('span', '', [
                'class' => 'mod_confsubmissions-track-swatch',
                'style' => 'display:inline-block;width:1em;height:1em;border-radius:2px;'
                    . 'vertical-align:middle;background-color:' . $track->colour . ';',
                'title' => $track->colour,
            ]);
        }

        $editurl = new moodle_url($pageurl, ['edit' => $track->id]);
        $editlink = $OUTPUT->action_icon(
            $editurl,
            new pix_icon('t/edit', get_string('edittrack', 'mod_confsubmissions')),
            null,
            ['title' => get_string('edittrack', 'mod_confsubmissions')]
        );

        $deleteurl = new moodle_url($pageurl, ['delete' => $track->id, 'sesskey' => sesskey()]);
        $deletelink = $OUTPUT->action_icon(
            $deleteurl,
            new pix_icon('t/delete', get_string('delete')),
            null,
            ['title' => get_string('delete')]
        );

        $table->data[] = [
            format_string($track->name),
            $swatch,
            confsubmissions_render_track_icon($track->icon ?? null),
            $editlink . ' ' . $deletelink,
        ];
    }

    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('notracks', 'mod_confsubmissions'), 'info');
}

echo $OUTPUT->heading(
    $editid ? get_string('edittrack', 'mod_confsubmissions') : get_string('addtrack', 'mod_confsubmissions'),
    4
);
$trackform->display();

echo $OUTPUT->footer();
