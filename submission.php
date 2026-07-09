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
 * Read-only detail view of a single submission for mod_confsubmissions.
 *
 * Visible to holders of mod/confsubmissions:viewall, or to the submission's
 * owner if they hold mod/confsubmissions:viewown.
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/confsubmissions/lib.php');

use mod_confsubmissions\api;
use mod_confsubmissions\output\submission_detail;

$id = required_param('id', PARAM_INT);
$submissionid = required_param('submissionid', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'confsubmissions');
$confsubmissions = $DB->get_record('confsubmissions', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

$submission = api::get_submission($submissionid);
if (!$submission || $submission->confsubmissions != $confsubmissions->id) {
    throw new \moodle_exception('invalidrecord', 'error', '', 'confsubmissions_submission');
}

$isowner = (int) $submission->userid === (int) $USER->id;
$canviewall = has_capability('mod/confsubmissions:viewall', $context);
$canviewown = $isowner && has_capability('mod/confsubmissions:viewown', $context);

if (!$canviewall && !$canviewown) {
    require_capability('mod/confsubmissions:viewall', $context);
}

$pageurl = new moodle_url('/mod/confsubmissions/submission.php', ['id' => $cm->id, 'submissionid' => $submissionid]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($confsubmissions->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$speakers = api::get_speakers($submission->id);
$fields = api::get_fields($confsubmissions->id);
$fieldvalues = api::get_optional_field_values($submission->id);

$track = null;
if (!empty($submission->trackid)) {
    $track = $DB->get_record('confsubmissions_track', [
        'id'              => $submission->trackid,
        'confsubmissions' => $confsubmissions->id,
    ]) ?: null;
}

$callisopen = ($confsubmissions->timeopen == 0 || time() >= $confsubmissions->timeopen)
    && ($confsubmissions->timeclose == 0 || time() < $confsubmissions->timeclose);
// Same rule edit.php itself enforces: an owner may edit while the call is open;
// an editany holder may edit regardless of the call window. Previously this was
// "$isowner && $callisopen", which hid the Edit link from editany holders (the
// list view offered it) and showed it to owners whose :submit had been revoked.
$iseditanyhere = !$isowner && has_capability('mod/confsubmissions:editany', $context);
$canedit = api::can_edit_submission($submission, $context) && ($callisopen || $iseditanyhere);
$editurl = $canedit
    ? new moodle_url('/mod/confsubmissions/edit.php', ['id' => $cm->id, 'submissionid' => $submission->id])
    : null;

$detail = new submission_detail($submission, $speakers, $fields, $fieldvalues, $track, $canedit, $editurl);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($confsubmissions->name), 2);
echo $OUTPUT->render($detail);
echo $OUTPUT->footer();
