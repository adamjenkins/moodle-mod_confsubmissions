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
 * "Disable specific preferred days" management screen for mod_confsubmissions.
 *
 * Lets an organiser (mod/confsubmissions:manageform) remove specific conference
 * days from the set a regular submitter is offered as a preferred date, without
 * removing them from the conference date range itself. A user with manageform
 * still sees and can select every day, disabled or not, on the submission form
 * (user feedback, 2026-07-05).
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/confsubmissions/lib.php');

use mod_confsubmissions\api;
use mod_confsubmissions\form\dates_form;

$id = required_param('id', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'confsubmissions');
$confsubmissions = $DB->get_record('confsubmissions', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/confsubmissions:manageform', $context);

$pageurl = new moodle_url('/mod/confsubmissions/dates.php', ['id' => $cm->id]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($confsubmissions->name) . ': ' . get_string('managedisableddates', 'mod_confsubmissions'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$conferencedays = api::get_conference_days($confsubmissions);

// Form construction and processing run BEFORE any output so redirect() sends a
// clean 303 -- see edit.php's matching comment.
$datesform = null;
if ($conferencedays) {
    $datesform = new dates_form($pageurl, ['conferencedays' => $conferencedays]);

    $disableddates = api::get_disabled_dates($confsubmissions);
    $disabledreasons = api::get_disabled_date_reasons($confsubmissions);
    $existing = [];
    $existingreasons = [];
    foreach ($conferencedays as $day) {
        $existing[$day] = in_array($day, $disableddates, true) ? 1 : 0;
        $existingreasons[$day] = $disabledreasons[$day] ?? '';
    }
    $datesform->set_data((object) ['disableddates' => $existing, 'disabledreasons' => $existingreasons]);

    if ($datesform->is_cancelled()) {
        redirect(new moodle_url('/mod/confsubmissions/view.php', ['id' => $cm->id]));
    } else if ($formdata = $datesform->get_data()) {
        // A reason is only ever persisted alongside a day that is actually being
        // disabled -- typing a reason next to an unchecked day (or leaving a stale
        // one from before it was re-enabled) is silently discarded rather than kept
        // around unused.
        $datestoreasons = [];
        foreach ($conferencedays as $day) {
            if (!empty($formdata->disableddates[$day] ?? null)) {
                $datestoreasons[$day] = trim((string) ($formdata->disabledreasons[$day] ?? ''));
            }
        }
        api::set_disabled_dates($confsubmissions->id, $datestoreasons);
        redirect(
            $pageurl,
            get_string('disableddatessaved', 'mod_confsubmissions'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($confsubmissions->name), 2);
echo $OUTPUT->heading(get_string('managedisableddates', 'mod_confsubmissions'), 3);

if (!$datesform) {
    echo $OUTPUT->notification(get_string('nodisableddatesconferencedates', 'mod_confsubmissions'), 'info');
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::tag('p', get_string('managedisableddates_help', 'mod_confsubmissions'));

$datesform->display();

echo $OUTPUT->footer();
