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
 * This is a first-pass scaffold covering the instance-level limits and
 * open/close window only. Track management and per-instance optional-field
 * toggles are a follow-up task and are not yet exposed here.
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

        // TODO: this is a placeholder pairing of limit + limit-type elements. The full
        // submission form (which will actually enforce these limits client- and
        // server-side) is a follow-up task.
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

        // TODO: track management UI (add/remove/reorder confsubmissions_track rows) and
        // optional-field toggles (confsubmissions_field rows) belong here in a follow-up.

        // Standard module elements (visibility, groups, etc.).
        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
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
