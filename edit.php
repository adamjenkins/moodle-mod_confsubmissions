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
 * Create or edit a submission for mod_confsubmissions.
 *
 * With no "submissionid" param, this creates a new submission (requires
 * mod/confsubmissions:submit). With a "submissionid" param, this edits an
 * existing submission, but only for its owner (the original submitter);
 * editing is only allowed while the call is open.
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/confsubmissions/lib.php');

use mod_confsubmissions\api;
use mod_confsubmissions\form\submission_form;
use mod_confsubmissions\local\limits;
use mod_confsubmissions\local\notifier;

$id = required_param('id', PARAM_INT);
$submissionid = optional_param('submissionid', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'confsubmissions');
$confsubmissions = $DB->get_record('confsubmissions', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

$submission = null;
$speakers = [];

if ($submissionid) {
    $submission = api::get_submission($submissionid);
    if (!$submission || $submission->confsubmissions != $confsubmissions->id) {
        throw new \moodle_exception('invalidrecord', 'error', '', 'confsubmissions_submission');
    }
    if ((int) $submission->userid !== (int) $USER->id) {
        throw new \moodle_exception('error:notowner', 'mod_confsubmissions');
    }
    // Ownership alone isn't enough: if the submit capability has since been revoked
    // (e.g. role change), the owner should no longer be able to edit either.
    require_capability('mod/confsubmissions:submit', $context);
    $speakers = api::get_speakers($submission->id);
} else {
    require_capability('mod/confsubmissions:submit', $context);
}

$callisopen = ($confsubmissions->timeopen == 0 || time() >= $confsubmissions->timeopen)
    && ($confsubmissions->timeclose == 0 || time() < $confsubmissions->timeclose);

$pageurl = new moodle_url('/mod/confsubmissions/edit.php', ['id' => $cm->id]);
if ($submissionid) {
    $pageurl->param('submissionid', $submissionid);
}
$viewurl = new moodle_url('/mod/confsubmissions/view.php', ['id' => $cm->id]);

$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($confsubmissions->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($confsubmissions->name), 2);
echo $OUTPUT->heading(
    $submission ? get_string('editsubmission', 'mod_confsubmissions') : get_string('newsubmission', 'mod_confsubmissions'),
    3
);

if (!$callisopen) {
    echo $OUTPUT->notification(get_string('callnotopen', 'mod_confsubmissions'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$customdata = [
    'cmid'            => $cm->id,
    'confsubmissions' => $confsubmissions,
    'speakers'        => $speakers,
    'showalldays'     => has_capability('mod/confsubmissions:manageform', $context),
];
if ($submission) {
    $customdata['preferreddates'] = api::get_date_preferences($submission->id);
}
$mform = new submission_form($pageurl, $customdata);

if ($submission) {
    $fields = api::get_fields($confsubmissions->id);
    $fieldvalues = api::get_optional_field_values($submission->id);

    $formdata = clone $submission;
    $formdata->trackid = $submission->trackid ?? 0;
    $formdata->submissiontypeid = $submission->submissiontypeid ?? 0;

    foreach ($fields as $field) {
        $raw = $fieldvalues[$field->id] ?? '';
        // A date_selector element expects a unix timestamp (0 renders as its "enable"
        // checkbox unchecked), not the empty string sync_optional_fields() stores for
        // "no answer".
        $formdata->{'field_' . $field->id} = $field->type === 'date' ? (int) $raw : $raw;
    }

    $speakerdata = [
        'speakeruserid'   => [],
        'speakername'     => [],
        'speakeremail'    => [],
        'speakermanual'   => [],
        'speakerposition' => [],
    ];
    foreach (array_values($speakers) as $index => $speaker) {
        $speakerdata['speakeruserid'][$index] = $speaker->userid ?? 0;
        $speakerdata['speakername'][$index] = $speaker->name ?? '';
        $speakerdata['speakeremail'][$index] = $speaker->email ?? '';
        $speakerdata['speakermanual'][$index] = empty($speaker->userid) ? 1 : 0;
        // Row 0 (primary) has no position selector; existing sortorder (already the
        // stored display order) maps directly onto the 1-based position options.
        $speakerdata['speakerposition'][$index] = $index + 1;
    }
    foreach ($speakerdata as $key => $value) {
        $formdata->$key = $value;
    }

    // Mirrors the speaker data above: submission_form.php's own setDefault() calls in
    // definition() are not enough on their own for an existing submission -- this
    // set_data() call needs an explicit value for every rendered checkbox, or they all
    // render unchecked regardless of what was actually saved.
    $conferencedaysforedit = api::get_conference_days($confsubmissions);
    if (!empty($confsubmissions->offerpreferreddates) && $conferencedaysforedit) {
        $existingprefs = $customdata['preferreddates'];
        $preferreddata = [];
        foreach ($conferencedaysforedit as $day) {
            $preferreddata[$day] = in_array($day, $existingprefs, true) ? 1 : 0;
        }
        $formdata->preferreddates = $preferreddata;
    }

    $mform->set_data($formdata);
}

if ($mform->is_cancelled()) {
    redirect($viewurl);
} else if ($data = $mform->get_data()) {
    $now = time();

    $record = (object) [
        'confsubmissions' => $confsubmissions->id,
        'userid'          => $submission ? $submission->userid : $USER->id,
        'title'           => $data->title,
        'abstract'        => $data->abstract,
        'trackid'         => !empty($data->trackid) ? $data->trackid : null,
        'submissiontypeid' => !empty($data->submissiontypeid) ? $data->submissiontypeid : null,
        'status'          => $submission->status ?? 'submitted',
        'timemodified'    => $now,
    ];

    $isnewsubmission = !$submission;

    if ($submission) {
        $record->id = $submission->id;
        $DB->update_record('confsubmissions_submission', $record);
        $newsubmissionid = $submission->id;
    } else {
        $record->timecreated = $now;
        $newsubmissionid = $DB->insert_record('confsubmissions_submission', $record);
    }

    api::sync_speakers($newsubmissionid, submission_form::extract_speakers($data));

    $fields = api::get_fields($confsubmissions->id);
    api::sync_optional_fields($newsubmissionid, submission_form::extract_optional_fields($data, $fields));

    // Only sync when the checkboxes were actually rendered (offerpreferreddates on and
    // a conference day range configured): otherwise this save's $data simply has no
    // 'preferreddates' to extract, and syncing an empty set here would wipe out any
    // preference recorded before the setting was turned off -- see
    // submission_form::extract_preferred_dates()'s docblock.
    $conferencedays = api::get_conference_days($confsubmissions);
    if (!empty($confsubmissions->offerpreferreddates) && $conferencedays) {
        api::sync_date_preferences(
            $newsubmissionid,
            submission_form::extract_preferred_dates($data, $conferencedays)
        );
    }

    // Only on genuine creation, never on an edit of an existing submission -- an
    // edit does not re-trigger the "submission made" notification.
    if ($isnewsubmission) {
        notifier::notify_submission_created($newsubmissionid);
    }

    redirect($viewurl, get_string('submissionsaved', 'mod_confsubmissions'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Formslib auto-generates an id="id_<name>" attribute for every element, so
// 'id_title' and 'id_abstract' are the title/abstract fields' DOM ids.
$countedfields = [
    'title'    => ['limit' => $confsubmissions->titlelimit, 'type' => $confsubmissions->titlelimittype],
    'abstract' => ['limit' => $confsubmissions->abstractlimit, 'type' => $confsubmissions->abstractlimittype],
];
foreach ($countedfields as $fieldname => $conf) {
    if ((int) $conf['limit'] > 0) {
        $PAGE->requires->js_call_amd('mod_confsubmissions/limitcounter', 'init', [
            'id_' . $fieldname,
            (int) $conf['limit'],
            $conf['type'],
        ]);
    }
}

$PAGE->requires->js_call_amd('mod_confsubmissions/speaker_order', 'init', [
    get_string('speakerno', 'mod_confsubmissions', '{no}'),
    get_string('primaryspeaker', 'mod_confsubmissions'),
    get_string('speakerposition', 'mod_confsubmissions'),
    get_string('removespeaker', 'mod_confsubmissions', '{no}'),
]);

$mform->display();

echo $OUTPUT->footer();
