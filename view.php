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
 * Main view page for mod_confsubmissions.
 *
 * Renders a "My submissions" listing (mod/confsubmissions:viewown) and/or an
 * "All submissions" listing (mod/confsubmissions:viewall), depending on the
 * current user's capabilities. A user with both (e.g. a teacher who is also
 * a presenter) sees both sections.
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/confsubmissions/lib.php');

use mod_confsubmissions\api;

$id = required_param('id', PARAM_INT);
$filtertrack = optional_param('trackid', '', PARAM_INT);
$filterstatus = optional_param('status', '', PARAM_ALPHA);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'confsubmissions');
$confsubmissions = $DB->get_record('confsubmissions', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

$canviewall = has_capability('mod/confsubmissions:viewall', $context);
$canviewown = has_capability('mod/confsubmissions:viewown', $context);
$cansubmit = has_capability('mod/confsubmissions:submit', $context);
$canmanagetracks = has_capability('mod/confsubmissions:managetracks', $context);
$canmanageform = has_capability('mod/confsubmissions:manageform', $context);

if (!$canviewall && !$canviewown) {
    require_capability('mod/confsubmissions:viewall', $context);
}

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$pageurl = new moodle_url('/mod/confsubmissions/view.php', ['id' => $cm->id]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($confsubmissions->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$callisopen = ($confsubmissions->timeopen == 0 || time() >= $confsubmissions->timeopen)
    && ($confsubmissions->timeclose == 0 || time() < $confsubmissions->timeclose);

$tracknames = [];
foreach (api::get_tracks($cm->id) as $track) {
    $tracknames[$track->id] = format_string($track->name);
}
$statuses = ['submitted', 'accepted', 'rejected'];

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($confsubmissions->name), 2);

if (!empty($confsubmissions->intro)) {
    echo $OUTPUT->box(format_module_intro('confsubmissions', $confsubmissions, $cm->id), 'generalbox', 'intro');
}

$links = [];
if ($canmanagetracks) {
    $links[] = html_writer::link(
        new moodle_url('/mod/confsubmissions/tracks.php', ['id' => $cm->id]),
        get_string('managetracks', 'mod_confsubmissions')
    );
    $links[] = html_writer::link(
        new moodle_url('/mod/confsubmissions/submissiontypes.php', ['id' => $cm->id]),
        get_string('managesubmissiontypes', 'mod_confsubmissions')
    );
}
if ($canmanageform) {
    $links[] = html_writer::link(
        new moodle_url('/course/modedit.php', ['update' => $cm->id, 'return' => 1]),
        get_string('editsettings')
    );
}
if ($links) {
    echo html_writer::tag('p', implode(' | ', $links));
}

if ($canviewown) {
    echo $OUTPUT->heading(get_string('mysubmissions', 'mod_confsubmissions'), 3);

    if ($cansubmit && $callisopen) {
        echo $OUTPUT->single_button(
            new moodle_url('/mod/confsubmissions/edit.php', ['id' => $cm->id]),
            get_string('newsubmission', 'mod_confsubmissions'),
            'get'
        );
    } else if ($cansubmit) {
        echo $OUTPUT->notification(get_string('callnotopen', 'mod_confsubmissions'), 'info');
    }

    $mine = api::get_submissions_for_instance($confsubmissions->id, ['userid' => $USER->id]);

    if ($mine) {
        $table = new html_table();
        $table->head = [
            get_string('title', 'mod_confsubmissions'),
            get_string('track', 'mod_confsubmissions'),
            get_string('status', 'mod_confsubmissions'),
            get_string('lastmodified', 'mod_confsubmissions'),
            '',
        ];
        $table->attributes['class'] = 'generaltable';

        foreach ($mine as $submission) {
            $editable = $callisopen;
            $viewurl = new moodle_url('/mod/confsubmissions/submission.php', [
                'id' => $cm->id,
                'submissionid' => $submission->id,
            ]);
            $actionurl = $editable
                ? new moodle_url('/mod/confsubmissions/edit.php', ['id' => $cm->id, 'submissionid' => $submission->id])
                : $viewurl;
            $actionlabel = $editable ? get_string('edit') : get_string('view');

            $table->data[] = [
                html_writer::link($viewurl, format_string($submission->title)),
                $submission->trackid ? ($tracknames[$submission->trackid] ?? '-') : get_string('notrack', 'mod_confsubmissions'),
                get_string('status_' . $submission->status, 'mod_confsubmissions'),
                userdate($submission->timemodified),
                html_writer::link($actionurl, $actionlabel),
            ];
        }

        echo html_writer::table($table);
    } else {
        echo $OUTPUT->notification(get_string('nosubmissionsyet', 'mod_confsubmissions'), 'info');
    }
}

if ($canviewall) {
    echo $OUTPUT->heading(get_string('allsubmissions', 'mod_confsubmissions'), 3);

    // Plain GET filter form: no JS required.
    echo html_writer::start_tag('form', [
        'method' => 'get',
        'action' => $pageurl->out_omit_querystring(),
        'class'  => 'form-inline mb-3',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);

    $trackfilteroptions = ['' => get_string('alltracks', 'mod_confsubmissions')] + $tracknames;
    echo html_writer::label(get_string('track', 'mod_confsubmissions'), 'menutrackid', false, ['class' => 'mr-1']);
    echo html_writer::select($trackfilteroptions, 'trackid', $filtertrack, null, ['class' => 'mr-3']);

    $statusfilteroptions = ['' => get_string('allstatuses', 'mod_confsubmissions')];
    foreach ($statuses as $status) {
        $statusfilteroptions[$status] = get_string('status_' . $status, 'mod_confsubmissions');
    }
    echo html_writer::label(get_string('status', 'mod_confsubmissions'), 'menustatus', false, ['class' => 'mr-1']);
    echo html_writer::select($statusfilteroptions, 'status', $filterstatus, null, ['class' => 'mr-3']);

    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'value' => get_string('filter'),
        'class' => 'btn btn-secondary',
    ]);
    echo html_writer::end_tag('form');

    $filters = [];
    if ($filtertrack !== '') {
        $filters['trackid'] = $filtertrack;
    }
    if ($filterstatus !== '') {
        $filters['status'] = $filterstatus;
    }

    $all = api::get_submissions_for_instance($confsubmissions->id, $filters);

    if ($all) {
        $table = new html_table();
        $table->head = [
            get_string('title', 'mod_confsubmissions'),
            get_string('primaryspeaker', 'mod_confsubmissions'),
            get_string('track', 'mod_confsubmissions'),
            get_string('status', 'mod_confsubmissions'),
            get_string('submitted', 'mod_confsubmissions'),
        ];
        $table->attributes['class'] = 'generaltable';

        foreach ($all as $submission) {
            $primary = $DB->get_record('confsubmissions_speaker', [
                'submissionid' => $submission->id,
                'role' => 'primary',
            ]);
            $primaryname = '-';
            if ($primary) {
                if (!empty($primary->userid)) {
                    $primaryuser = \core_user::get_user($primary->userid);
                    $primaryname = $primaryuser ? fullname($primaryuser) : '-';
                } else if (!empty($primary->name)) {
                    $primaryname = format_string($primary->name);
                }
            }

            $viewurl = new moodle_url('/mod/confsubmissions/submission.php', [
                'id' => $cm->id,
                'submissionid' => $submission->id,
            ]);

            $table->data[] = [
                html_writer::link($viewurl, format_string($submission->title)),
                $primaryname,
                $submission->trackid ? ($tracknames[$submission->trackid] ?? '-') : get_string('notrack', 'mod_confsubmissions'),
                get_string('status_' . $submission->status, 'mod_confsubmissions'),
                userdate($submission->timecreated),
            ];
        }

        echo html_writer::table($table);
    } else {
        echo $OUTPUT->notification(get_string('nosubmissionsfound', 'mod_confsubmissions'), 'info');
    }
}

echo $OUTPUT->footer();
