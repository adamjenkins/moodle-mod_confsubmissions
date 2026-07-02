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
 * This is a stub: it performs the standard login/capability checks and
 * renders a placeholder heading depending on whether the current user can
 * see all submissions or only their own. The actual submission listing,
 * "new submission" entry point, and per-submission detail views are a
 * follow-up task.
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/confsubmissions/lib.php');

$id = required_param('id', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'confsubmissions');
$confsubmissions = $DB->get_record('confsubmissions', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

$canviewall = has_capability('mod/confsubmissions:viewall', $context);
$canviewown = has_capability('mod/confsubmissions:viewown', $context);

if (!$canviewall && !$canviewown) {
    require_capability('mod/confsubmissions:viewall', $context);
}

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$PAGE->set_url('/mod/confsubmissions/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($confsubmissions->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($confsubmissions->name), 2);

if ($canviewall) {
    // TODO: replace with the full "all submissions" listing (filterable by track/status).
    echo $OUTPUT->heading(get_string('allsubmissions', 'mod_confsubmissions'), 3);
} else if ($canviewown) {
    // TODO: replace with the full "my submissions" listing plus a "new submission" link.
    echo $OUTPUT->heading(get_string('mysubmissions', 'mod_confsubmissions'), 3);
}

echo $OUTPUT->notification(get_string('viewnotimplemented', 'mod_confsubmissions'), 'info');

echo $OUTPUT->footer();
