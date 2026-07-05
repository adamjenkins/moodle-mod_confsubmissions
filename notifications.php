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
 * Notification template management screen for mod_confsubmissions.
 *
 * One template per notification type per instance (confsubmissions_notiftemplate,
 * unique on confsubmissions+notiftype -- see db/install.xml). Mirrors
 * mod_confcheckin's templates.php pattern exactly: visiting this page for a type
 * that has no row yet pre-fills the editor with the built-in fallback content, so
 * an organiser edits from a real starting point rather than a blank box.
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/confsubmissions/lib.php');

use mod_confsubmissions\form\notiftemplate_form;
use mod_confsubmissions\local\notifier;

$id = required_param('id', PARAM_INT);
$notiftype = optional_param('type', 'created', PARAM_ALPHA);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'confsubmissions');
$confsubmissions = $DB->get_record('confsubmissions', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/confsubmissions:managenotifications', $context);

if (!in_array($notiftype, notifier::NOTIF_TYPES, true)) {
    throw new \moodle_exception('error:invalidnotiftype', 'mod_confsubmissions');
}

$pageurl = new moodle_url('/mod/confsubmissions/notifications.php', ['id' => $cm->id, 'type' => $notiftype]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($confsubmissions->name) . ': ' . get_string('managenotifications', 'mod_confsubmissions'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$existing = $DB->get_record('confsubmissions_notiftemplate', [
    'confsubmissions' => $confsubmissions->id,
    'notiftype'       => $notiftype,
]);

$form = new notiftemplate_form($pageurl, ['notiftype' => $notiftype, 'context' => $context]);

$default = notifier::default_template($notiftype);
$form->set_data((object) [
    'notiftype' => $notiftype,
    'subject'   => $existing->subject ?? $default['subject'],
    'body'      => [
        'text'   => $existing->body ?? $default['body'],
        'format' => $existing->bodyformat ?? FORMAT_HTML,
    ],
]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/confsubmissions/view.php', ['id' => $cm->id]));
} else if ($data = $form->get_data()) {
    $now = time();
    $record = (object) [
        'confsubmissions' => $confsubmissions->id,
        'notiftype'       => $notiftype,
        'subject'         => $data->subject,
        'body'            => $data->body['text'],
        'bodyformat'      => $data->body['format'],
        'timemodified'    => $now,
    ];

    if ($existing) {
        $record->id = $existing->id;
        $DB->update_record('confsubmissions_notiftemplate', $record);
    } else {
        $record->timecreated = $now;
        $DB->insert_record('confsubmissions_notiftemplate', $record);
    }

    redirect($pageurl, get_string('notiftemplatesaved', 'mod_confsubmissions'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($confsubmissions->name), 2);
echo $OUTPUT->heading(get_string('managenotifications', 'mod_confsubmissions'), 3);

$tablinks = [];
foreach (notifier::NOTIF_TYPES as $type) {
    $label = get_string('notiftype:' . $type, 'mod_confsubmissions');
    if ($type === $notiftype) {
        $tablinks[] = html_writer::tag('strong', $label);
    } else {
        $tablinks[] = html_writer::link(
            new moodle_url('/mod/confsubmissions/notifications.php', ['id' => $cm->id, 'type' => $type]),
            $label
        );
    }
}
echo html_writer::tag('p', implode(' | ', $tablinks));

$placeholdernames = $notiftype === 'withdrawn'
    ? ['submissiontitle', 'submitterfullname', 'coursename']
    : ['fullname', 'submissiontitle', 'coursename'];
$placeholderlist = implode(', ', array_map(static fn (string $name): string => "[[{$name}]]", $placeholdernames));
echo $OUTPUT->notification(
    get_string('notifplaceholders', 'mod_confsubmissions', $placeholderlist),
    'info'
);

$form->display();

echo $OUTPUT->footer();
