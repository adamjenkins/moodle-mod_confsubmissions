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

namespace mod_confsubmissions\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Notification template editor form used on notifications.php, one instance per
 * notification type -- mirrors mod_confcheckin's template_form.php pattern.
 *
 * Required custom data:
 * - notiftype: string, one of \mod_confsubmissions\local\notifier::NOTIF_TYPES
 * - context: \context_module, this instance's own context (required by the 'editor'
 *   element even though maxfiles is 0 here)
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notiftemplate_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;
        $notiftype = $this->_customdata['notiftype'];
        $context = $this->_customdata['context'];

        // Named 'notiftype', not 'type': notifications.php treats the querystring
        // 'type' param as which notification is being edited, matching this
        // project's "POST field name must not collide with a GET routing param"
        // convention (see mod_confcheckin\form\template_form).
        $mform->addElement('hidden', 'notiftype', $notiftype);
        $mform->setType('notiftype', PARAM_ALPHA);

        // Instance-level master switch (user request, 2026-07-06): saved to the
        // confsubmissions table itself, not the per-type notiftemplate row, so it
        // applies regardless of which notification type's tab this form was
        // submitted from -- see notifications.php.
        $mform->addElement('advcheckbox', 'notificationsenabled', get_string('notificationsenabled', 'mod_confsubmissions'));
        $mform->addHelpButton('notificationsenabled', 'notificationsenabled', 'mod_confsubmissions');

        $mform->addElement('text', 'subject', get_string('notifsubject', 'mod_confsubmissions'), ['size' => 64]);
        $mform->setType('subject', PARAM_TEXT);
        $mform->addHelpButton('subject', 'notifsubject', 'mod_confsubmissions');

        $editoroptions = [
            'maxfiles'  => 0,
            'noclean'   => true,
            'context'   => $context,
            'subdirs'   => 0,
        ];
        $mform->addElement(
            'editor',
            'body',
            get_string('notifbody', 'mod_confsubmissions'),
            null,
            $editoroptions
        );
        $mform->setType('body', PARAM_RAW);
        $mform->addHelpButton('body', 'notifbody', 'mod_confsubmissions');

        $this->add_action_buttons();
    }
}
