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
 * Activity settings form for mod_confsubmissions.
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Settings form for the Conference Submissions activity.
 *
 * Covers the instance-level limits, open/close window, and the fixed
 * optional-field toggles (language, teaching context, sub-topic area). Track
 * management lives on its own screen (tracks.php) rather than in this form;
 * see the note above the coursemodule elements below for why.
 */
class mod_confsubmissions_mod_form extends moodleform_mod {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        // General section.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        // Submission window and limits section.
        $mform->addElement(
            'header',
            'submissionsettings',
            get_string('submissionsettings', 'mod_confsubmissions')
        );
        $mform->setExpanded('submissionsettings');

        $mform->addElement(
            'date_time_selector',
            'timeopen',
            get_string('timeopen', 'mod_confsubmissions'),
            ['optional' => true]
        );
        $mform->setDefault('timeopen', 0);
        $mform->addHelpButton('timeopen', 'timeopen', 'mod_confsubmissions');

        $mform->addElement(
            'date_time_selector',
            'timeclose',
            get_string('timeclose', 'mod_confsubmissions'),
            ['optional' => true]
        );
        $mform->setDefault('timeclose', 0);
        $mform->addHelpButton('timeclose', 'timeclose', 'mod_confsubmissions');

        $limittypes = [
            'chars' => get_string('limittype_chars', 'mod_confsubmissions'),
            'words' => get_string('limittype_words', 'mod_confsubmissions'),
        ];

        $titlegroup = [
            $mform->createElement('text', 'titlelimit', '', ['size' => 6]),
            $mform->createElement('select', 'titlelimittype', '', $limittypes),
        ];
        $mform->addGroup(
            $titlegroup,
            'titlelimitgroup',
            get_string('titlelimit', 'mod_confsubmissions'),
            [' '],
            false
        );
        $mform->setType('titlelimit', PARAM_INT);
        $mform->setDefault('titlelimit', 0);
        $mform->setDefault('titlelimittype', 'chars');
        $mform->addHelpButton('titlelimitgroup', 'titlelimit', 'mod_confsubmissions');

        $abstractgroup = [
            $mform->createElement('text', 'abstractlimit', '', ['size' => 6]),
            $mform->createElement('select', 'abstractlimittype', '', $limittypes),
        ];
        $mform->addGroup(
            $abstractgroup,
            'abstractlimitgroup',
            get_string('abstractlimit', 'mod_confsubmissions'),
            [' '],
            false
        );
        $mform->setType('abstractlimit', PARAM_INT);
        $mform->setDefault('abstractlimit', 0);
        $mform->setDefault('abstractlimittype', 'chars');
        $mform->addHelpButton('abstractlimitgroup', 'abstractlimit', 'mod_confsubmissions');

        // Optional fields section: fixed on/off toggles, not free-form custom fields.
        // Each checkbox corresponds to a confsubmissions_field row (fieldname must match
        // confsubmissions_optional_fieldnames() in lib.php exactly).
        $mform->addElement('header', 'optionalfields', get_string('optionalfields', 'mod_confsubmissions'));
        $mform->setExpanded('optionalfields');

        $mform->addElement('advcheckbox', 'field_language', get_string('field_language', 'mod_confsubmissions'));
        $mform->addElement(
            'advcheckbox',
            'field_teachingcontext',
            get_string('field_teachingcontext', 'mod_confsubmissions')
        );
        $mform->addElement('advcheckbox', 'field_subtopic', get_string('field_subtopic', 'mod_confsubmissions'));

        // Track management (add/remove/reorder confsubmissions_track rows) is deliberately
        // not part of this form: tracks are FK'd to the instance id, so they need an
        // instance to already exist, and organisers need to manage them both before and
        // after this settings form has been saved. See tracks.php, linked from this
        // activity's navigation (confsubmissions_extend_navigation() in lib.php) for users
        // with mod/confsubmissions:managetracks.

        // Standard module elements (visibility, groups, etc.).
        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    /**
     * Pre-processes data passed to set_data() before display, reading the existing
     * confsubmissions_field rows back into field_* checkbox state when editing an
     * existing instance.
     *
     * @param array $defaultvalues Reference to default values array
     */
    public function data_preprocessing(&$defaultvalues) {
        global $DB;

        if (empty($this->current->instance)) {
            return;
        }

        $fields = $DB->get_records('confsubmissions_field', ['confsubmissions' => $this->current->instance]);

        foreach ($fields as $field) {
            $defaultvalues['field_' . $field->fieldname] = (int) $field->enabled;
        }
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

        if (!empty($data['timeopen']) && !empty($data['timeclose']) && $data['timeclose'] < $data['timeopen']) {
            $errors['timeclose'] = get_string('error:closebeforeopen', 'mod_confsubmissions');
        }

        if ((int) ($data['titlelimit'] ?? 0) < 0) {
            $errors['titlelimitgroup'] = get_string('error:limitnegative', 'mod_confsubmissions');
        }

        if ((int) ($data['abstractlimit'] ?? 0) < 0) {
            $errors['abstractlimitgroup'] = get_string('error:limitnegative', 'mod_confsubmissions');
        }

        return $errors;
    }
}
