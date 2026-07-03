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
 * Mini "add a track" form used on tracks.php.
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class track_form extends \moodleform {
    /**
     * Defines the form fields.
     *
     * Optional custom data:
     * - editing: bool, true when this form is editing an existing track (changes the
     *   submit button label). Defaults to false (adding a new track).
     */
    public function definition() {
        $mform = $this->_form;
        $editing = !empty($this->_customdata['editing']);

        // Named 'trackid', not 'id': every page in this plugin (tracks.php included) treats
        // the querystring 'id' param as the course-module id, and a same-named POST field
        // would collide with it (POST is read ahead of GET by Moodle's required_param()),
        // corrupting the cmid the whole page is scoped to.
        $mform->addElement('hidden', 'trackid', 0);
        $mform->setType('trackid', PARAM_INT);

        $mform->addElement('text', 'name', get_string('trackname', 'mod_confsubmissions'), ['size' => 40]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // A plain hex-colour text field, validated both client-side (the pattern/
        // maxlength attributes below) and, authoritatively, server-side in validation()
        // and again in classes/api.php. Note: core's 'text' form element is rendered via
        // a fixed mustache template (lib/form/templates/element-text.mustache) that
        // hardcodes type="text" and its own class list - unlike mod_confscheduler's room
        // colour picker (a hand-rolled AJAX mustache form, not a moodleform), a moodleform
        // 'text' element cannot be turned into a real <input type="color"> by passing
        // attributes; only attributes outside the template's fixed list (e.g. 'pattern',
        // 'placeholder') actually reach the rendered input.
        $mform->addElement('text', 'colour', get_string('trackcolour', 'mod_confsubmissions'), [
            'size'        => 8,
            'maxlength'   => 7,
            'placeholder' => '#3366cc',
            'pattern'     => '^#[0-9a-fA-F]{6}$',
        ]);
        $mform->setType('colour', PARAM_TEXT);
        $mform->addHelpButton('colour', 'trackcolour', 'mod_confsubmissions');

        $mform->addElement('select', 'icon', get_string('trackicon', 'mod_confsubmissions'), confsubmissions_track_icon_options());
        $mform->setType('icon', PARAM_ALPHANUMEXT);

        $this->add_action_buttons(
            false,
            $editing ? get_string('savechanges') : get_string('addtrack', 'mod_confsubmissions')
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

        $colour = trim((string) ($data['colour'] ?? ''));
        if ($colour !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $colour)) {
            $errors['colour'] = get_string('error:invalidcolour', 'mod_confsubmissions');
        }

        $icon = (string) ($data['icon'] ?? '');
        if ($icon !== '' && !in_array($icon, confsubmissions_track_icon_keys(), true)) {
            $errors['icon'] = get_string('error:invalidicon', 'mod_confsubmissions');
        }

        return $errors;
    }
}
