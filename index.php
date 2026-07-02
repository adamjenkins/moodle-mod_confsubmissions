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
 * List of all confsubmissions instances in a course.
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/confsubmissions/lib.php');

$courseid = required_param('id', PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);

$coursecontext = context_course::instance($course->id);

$PAGE->set_url('/mod/confsubmissions/index.php', ['id' => $course->id]);
$PAGE->set_title(get_string('modulenameplural', 'mod_confsubmissions'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($coursecontext);
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_confsubmissions'));

$modinfo = get_fast_modinfo($course);
$instances = $modinfo->get_instances_of('confsubmissions');

if (!$instances) {
    echo $OUTPUT->notification(get_string('noinstances', 'mod_confsubmissions'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    get_string('name'),
    get_string('timeopen', 'mod_confsubmissions'),
    get_string('timeclose', 'mod_confsubmissions'),
];
$table->attributes['class'] = 'generaltable mod_index';

foreach ($instances as $cm) {
    if (!$cm->uservisible) {
        continue;
    }

    $confsubmissions = $DB->get_record('confsubmissions', ['id' => $cm->instance], '*', MUST_EXIST);
    $link = html_writer::link(
        new moodle_url('/mod/confsubmissions/view.php', ['id' => $cm->id]),
        format_string($confsubmissions->name)
    );

    $table->data[] = [
        $link,
        $confsubmissions->timeopen ? userdate($confsubmissions->timeopen) : '-',
        $confsubmissions->timeclose ? userdate($confsubmissions->timeclose) : '-',
    ];
}

echo html_writer::table($table);

echo $OUTPUT->footer();
