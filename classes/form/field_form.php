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
require_once($CFG->dirroot . '/mod/confsubmissions/lib.php');

/**
 * Mini "add an optional field" form used on fields.php.
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_form extends \moodleform {
    /**
     * Defines the form fields.
     *
     * Optional custom data:
     * - editing: bool, true when this form is editing an existing field (changes the
     *   submit button label). Defaults to false (adding a new field).
     */
    public function definition() {
        $mform = $this->_form;
        $editing = !empty($this->_customdata['editing']);

        // Named 'fieldid', not 'id': every page in this plugin treats the querystring
        // 'id' param as the course-module id -- see track_form.php's identical 'trackid'
        // naming for the full reasoning.
        $mform->addElement('hidden', 'fieldid', 0);
        $mform->setType('fieldid', PARAM_INT);

        $mform->addElement('text', 'name', get_string('fieldlabel', 'mod_confsubmissions'), ['size' => 40]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement(
            'select',
            'type',
            get_string('fieldtype', 'mod_confsubmissions'),
            confsubmissions_field_type_options()
        );
        $mform->setType('type', PARAM_ALPHA);
        $mform->setDefault('type', 'text');

        $mform->addElement(
            'textarea',
            'options',
            get_string('fieldoptions', 'mod_confsubmissions'),
            ['rows' => 5, 'cols' => 40]
        );
        $mform->setType('options', PARAM_TEXT);
        $mform->addHelpButton('options', 'fieldoptions', 'mod_confsubmissions');
        // Only meaningful (and only shown) for a 'menu' field -- every other type
        // ignores this value entirely, both client-side here and server-side in
        // classes/api.php.
        $mform->hideIf('options', 'type', 'neq', 'menu');

        $mform->addElement('advcheckbox', 'required', get_string('fieldrequired', 'mod_confsubmissions'));

        $this->add_action_buttons(
            false,
            $editing ? get_string('savechanges') : get_string('addfield', 'mod_confsubmissions')
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

        $type = (string) ($data['type'] ?? '');
        if (!in_array($type, confsubmissions_field_types(), true)) {
            $errors['type'] = get_string('error:invalidfieldtype', 'mod_confsubmissions');
        } else if ($type === 'menu' && empty(confsubmissions_parse_field_options($data['options'] ?? ''))) {
            $errors['options'] = get_string('error:invalidfieldoptions', 'mod_confsubmissions');
        }

        // Friendly form-level copy of api::update_field()'s own guard: a field's type
        // cannot change once answers exist, since stored answers are opaque strings
        // whose meaning depends on the type they were captured under.
        $fieldid = (int) ($data['fieldid'] ?? 0);
        if ($fieldid && !isset($errors['type'])) {
            global $DB;
            $existingtype = $DB->get_field('confsubmissions_field', 'type', ['id' => $fieldid]);
            if (
                $existingtype !== false && $existingtype !== $type
                    && $DB->record_exists('confsubmissions_fieldval', ['fieldid' => $fieldid])
            ) {
                $errors['type'] = get_string('error:fieldtypechangehasvalues', 'mod_confsubmissions');
            }
        }

        return $errors;
    }
}
