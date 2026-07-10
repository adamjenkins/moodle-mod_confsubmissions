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
// 0 (not '') is the "no filter" sentinel: optional_param() applies PARAM_INT
// cleaning to any value present in the request, including an empty string
// (the "All tracks" option's value), and (int) '' is 0, not '' -- so a ''
// default here would only ever match on the very first, query-string-less
// page load. trackid is an auto-increment id (never legitimately 0), so 0
// is safe to use as the sentinel, matching this project's established
// "0 = unset" convention for PARAM_INT select fields (see e.g.
// mod_confcheckin's confprogramcmid/paymentaccountid handling).
$filtertrack = optional_param('trackid', 0, PARAM_INT);
$filterstatus = optional_param('status', '', PARAM_ALPHA);
$withdrawid = optional_param('withdraw', 0, PARAM_INT);
$unwithdrawid = optional_param('unwithdraw', 0, PARAM_INT);
$deleteid = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'confsubmissions');
$confsubmissions = $DB->get_record('confsubmissions', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

$canviewall = has_capability('mod/confsubmissions:viewall', $context);
$canviewown = has_capability('mod/confsubmissions:viewown', $context);
$cansubmit = has_capability('mod/confsubmissions:submit', $context);
$canmanagetracks = has_capability('mod/confsubmissions:managetracks', $context);
$canmanageform = has_capability('mod/confsubmissions:manageform', $context);
$candeleteany = has_capability('mod/confsubmissions:deleteany', $context);
$caneditany = has_capability('mod/confsubmissions:editany', $context);
$canmanagenotifications = has_capability('mod/confsubmissions:managenotifications', $context);

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

// The activity header would otherwise auto-render the intro a second time
// (when "Display description on course page" is enabled) on top of the
// manual format_module_intro() box this page renders itself below.
$PAGE->activityheader->set_attrs(['description' => '']);

$callisopen = ($confsubmissions->timeopen == 0 || time() >= $confsubmissions->timeopen)
    && ($confsubmissions->timeclose == 0 || time() < $confsubmissions->timeclose);

$tracknames = [];
foreach (api::get_tracks($cm->id) as $track) {
    $tracknames[$track->id] = format_string($track->name);
}
$statuses = ['submitted', 'accepted', 'rejected', 'withdrawn'];

// Withdraw: reversible, submitter-owned (their own "my submissions" row only), a
// status change rather than a deletion -- see api::set_status()'s docblock for why
// this and Delete are kept as two separate, differently-gated actions.
if ($withdrawid) {
    require_sesskey();

    $submission = api::get_submission($withdrawid);
    if (!$submission || $submission->confsubmissions != $confsubmissions->id) {
        throw new \moodle_exception('invalidrecord', 'error', '', 'confsubmissions_submission');
    }
    if ((int) $submission->userid !== (int) $USER->id) {
        throw new \moodle_exception('error:notowner', 'mod_confsubmissions');
    }
    require_capability('mod/confsubmissions:submit', $context);

    if (!$confirm) {
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(
            get_string('confirmwithdraw', 'mod_confsubmissions', format_string($submission->title)),
            new moodle_url($pageurl, ['withdraw' => $withdrawid, 'confirm' => 1, 'sesskey' => sesskey()]),
            $pageurl
        );
        echo $OUTPUT->footer();
        exit;
    }

    api::set_status($withdrawid, 'withdrawn');
    redirect($pageurl, get_string('submissionwithdrawn', 'mod_confsubmissions'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Unwithdraw: the organiser-side reverse of the submitter-owned Withdraw above (user
// request, 2026-07-07: a withdrawn submission should only ever come back via this
// explicit action or mod_confsubmissions:deleteany -- see mod_confprogram's own fix,
// same request, for why phase-cycling must NOT do this implicitly). Gated on editany
// rather than a new capability: reversing someone else's status change is the same
// risk tier as editing their submission outright.
if ($unwithdrawid) {
    require_sesskey();
    require_capability('mod/confsubmissions:editany', $context);

    $submission = api::get_submission($unwithdrawid);
    if (!$submission || $submission->confsubmissions != $confsubmissions->id) {
        throw new \moodle_exception('invalidrecord', 'error', '', 'confsubmissions_submission');
    }
    if ($submission->status !== 'withdrawn') {
        throw new \moodle_exception('error:notwithdrawn', 'mod_confsubmissions');
    }

    if (!$confirm) {
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(
            get_string('confirmunwithdraw', 'mod_confsubmissions', format_string($submission->title)),
            new moodle_url($pageurl, ['unwithdraw' => $unwithdrawid, 'confirm' => 1, 'sesskey' => sesskey()]),
            $pageurl
        );
        echo $OUTPUT->footer();
        exit;
    }

    // Restore whatever status the submission had immediately before it was
    // withdrawn (accepted/rejected/submitted), falling back to 'submitted' for
    // rows withdrawn before statusbeforewithdraw existed (pre-upgrade, null).
    $restorestatus = $submission->statusbeforewithdraw ?? 'submitted';
    api::set_status($unwithdrawid, $restorestatus);
    redirect($pageurl, get_string('submissionunwithdrawn', 'mod_confsubmissions'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Delete: permanent, manager/admin-only (mod/confsubmissions:deleteany is a
// manager-only archetype -- see db/access.php -- editingteacher deliberately
// excluded per explicit user feedback, 2026-07-05).
if ($deleteid) {
    require_sesskey();
    require_capability('mod/confsubmissions:deleteany', $context);

    $submission = api::get_submission($deleteid);
    if (!$submission || $submission->confsubmissions != $confsubmissions->id) {
        throw new \moodle_exception('invalidrecord', 'error', '', 'confsubmissions_submission');
    }

    if (!$confirm) {
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(
            get_string('confirmdeletesubmission', 'mod_confsubmissions', format_string($submission->title)),
            new moodle_url($pageurl, ['delete' => $deleteid, 'confirm' => 1, 'sesskey' => sesskey()]),
            $pageurl
        );
        echo $OUTPUT->footer();
        exit;
    }

    api::delete_submission($deleteid);
    redirect($pageurl, get_string('submissiondeleted', 'mod_confsubmissions'), null, \core\output\notification::NOTIFY_SUCCESS);
}

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
    $links[] = html_writer::link(
        new moodle_url('/mod/confsubmissions/fields.php', ['id' => $cm->id]),
        get_string('managefields', 'mod_confsubmissions')
    );
}
if ($canmanagenotifications) {
    $links[] = html_writer::link(
        new moodle_url('/mod/confsubmissions/notifications.php', ['id' => $cm->id]),
        get_string('managenotifications', 'mod_confsubmissions')
    );
}
if ($canmanageform) {
    $links[] = html_writer::link(
        new moodle_url('/course/modedit.php', ['update' => $cm->id, 'return' => 1]),
        get_string('editsettings')
    );
    $links[] = html_writer::link(
        new moodle_url('/mod/confsubmissions/dates.php', ['id' => $cm->id]),
        get_string('managedisableddates', 'mod_confsubmissions')
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
            html_writer::span(get_string('actions'), 'sr-only'),
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

            $actions = [html_writer::link($actionurl, $actionlabel)];
            if ($submission->status !== 'withdrawn') {
                $withdrawurl = new moodle_url($pageurl, ['withdraw' => $submission->id, 'sesskey' => sesskey()]);
                $actions[] = html_writer::link($withdrawurl, get_string('withdraw', 'mod_confsubmissions'));
            }

            $table->data[] = [
                html_writer::link($viewurl, format_string($submission->title)),
                $submission->trackid ? ($tracknames[$submission->trackid] ?? '-') : get_string('notrack', 'mod_confsubmissions'),
                get_string('status_' . $submission->status, 'mod_confsubmissions'),
                userdate($submission->timemodified),
                implode(' | ', $actions),
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

    $trackfilteroptions = [0 => get_string('alltracks', 'mod_confsubmissions')] + $tracknames;
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
    if ($filtertrack !== 0) {
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
        if ($caneditany) {
            $table->head[] = html_writer::span(get_string('actions'), 'sr-only');
        }
        if ($candeleteany) {
            $table->head[] = html_writer::span(get_string('actions'), 'sr-only');
        }
        $table->attributes['class'] = 'generaltable';

        // Primary speakers and their user records are fetched in two bulk queries
        // rather than two queries per row -- this listing has no pagination, so a
        // large conference would otherwise pay ~2N queries per page view.
        [$insql, $inparams] = $DB->get_in_or_equal(array_keys($all));
        $primaries = [];
        foreach (
            $DB->get_records_select(
                'confsubmissions_speaker',
                "role = 'primary' AND submissionid $insql",
                $inparams
            ) as $speaker
        ) {
            $primaries[(int) $speaker->submissionid] = $speaker;
        }
        $primaryuserids = array_filter(array_map(
            static fn($speaker) => (int) $speaker->userid,
            $primaries
        ));
        $namefields = implode(', ', array_merge(['id'], \core_user\fields::for_name()->get_required_fields()));
        $primaryusers = $primaryuserids
            ? $DB->get_records_list('user', 'id', $primaryuserids, '', $namefields)
            : [];

        foreach ($all as $submission) {
            $primary = $primaries[(int) $submission->id] ?? null;
            $primaryname = '-';
            if ($primary) {
                if (!empty($primary->userid)) {
                    $primaryuser = $primaryusers[(int) $primary->userid] ?? null;
                    $primaryname = $primaryuser ? s(fullname($primaryuser)) : '-';
                } else if (!empty($primary->name)) {
                    $primaryname = format_string($primary->name);
                }
            }

            $viewurl = new moodle_url('/mod/confsubmissions/submission.php', [
                'id' => $cm->id,
                'submissionid' => $submission->id,
            ]);

            $row = [
                html_writer::link($viewurl, format_string($submission->title)),
                $primaryname,
                $submission->trackid ? ($tracknames[$submission->trackid] ?? '-') : get_string('notrack', 'mod_confsubmissions'),
                get_string('status_' . $submission->status, 'mod_confsubmissions'),
                userdate($submission->timecreated),
            ];
            if ($caneditany) {
                $editurl = new moodle_url('/mod/confsubmissions/edit.php', [
                    'id'           => $cm->id,
                    'submissionid' => $submission->id,
                ]);
                $editanyactions = [html_writer::link($editurl, get_string('edit'))];
                if ($submission->status === 'withdrawn') {
                    $unwithdrawurl = new moodle_url($pageurl, ['unwithdraw' => $submission->id, 'sesskey' => sesskey()]);
                    $editanyactions[] = html_writer::link($unwithdrawurl, get_string('unwithdraw', 'mod_confsubmissions'));
                }
                $row[] = implode(' | ', $editanyactions);
            }
            if ($candeleteany) {
                $deleteurl = new moodle_url($pageurl, ['delete' => $submission->id, 'sesskey' => sesskey()]);
                $row[] = html_writer::link($deleteurl, get_string('delete'));
            }

            $table->data[] = $row;
        }

        echo html_writer::table($table);
    } else {
        echo $OUTPUT->notification(get_string('nosubmissionsfound', 'mod_confsubmissions'), 'info');
    }
}

echo $OUTPUT->footer();
