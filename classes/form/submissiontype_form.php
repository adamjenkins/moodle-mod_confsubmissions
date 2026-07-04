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
 * Mini "add a submission type" form used on submissiontypes.php.
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submissiontype_form extends \moodleform {
    /**
     * Defines the form fields.
     *
     * Optional custom data:
     * - editing: bool, true when this form is editing an existing submission type
     *   (changes the submit button label). Defaults to false (adding a new type).
     */
    public function definition() {
        $mform = $this->_form;
        $editing = !empty($this->_customdata['editing']);

        // Named 'submissiontypeid', not 'id': every page in this plugin treats the
        // querystring 'id' param as the course-module id, and a same-named POST field
        // would collide with it -- see track_form.php's identical 'trackid' naming for
        // the full reasoning.
        $mform->addElement('hidden', 'submissiontypeid', 0);
        $mform->setType('submissiontypeid', PARAM_INT);

        $mform->addElement('text', 'name', get_string('submissiontypename', 'mod_confsubmissions'), ['size' => 40]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement(
            'text',
            'durationminutes',
            get_string('submissiontypeduration', 'mod_confsubmissions'),
            ['size' => 5]
        );
        $mform->setType('durationminutes', PARAM_INT);
        $mform->addRule('durationminutes', null, 'required', null, 'client');
        $mform->addHelpButton('durationminutes', 'submissiontypeduration', 'mod_confsubmissions');
        $mform->setDefault('durationminutes', 30);

        $this->add_action_buttons(
            false,
            $editing ? get_string('savechanges') : get_string('addsubmissiontype', 'mod_confsubmissions')
        );
    }

    /**
     * Server-side validation.
     *
     * @param array $data Submitted form data
     * @param array $files Uploaded files
     * @return array Errors keyed by field name
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (trim((string) ($data['name'] ?? '')) === '') {
            $errors['name'] = get_string('required');
        }

        if ((int) ($data['durationminutes'] ?? 0) <= 0) {
            $errors['durationminutes'] = get_string('error:invalidduration', 'mod_confsubmissions');
        }

        return $errors;
    }
}
