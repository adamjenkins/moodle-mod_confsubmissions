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
 * "Disable specific preferred days" form used on dates.php.
 *
 * One checkbox per conference day; checking it removes that day from the
 * preferred-date checkboxes a regular submitter sees on the submission form (a
 * user with mod/confsubmissions:manageform still sees and can select every day).
 *
 * A short optional reason text field sits beside each checkbox (user request,
 * 2026-07-09): submission_form.php shows it in parentheses next to a disabled
 * day's greyed-out checkbox, so a submitter sees WHY without needing to ask an
 * organiser. Reason text for a day whose checkbox is left unchecked is simply
 * discarded on save (dates.php only persists a reason alongside a day that is
 * actually being disabled) -- see that file's own comment.
 *
 * Required custom data:
 * - conferencedays: int[], midnight timestamps from api::get_conference_days()
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dates_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;
        $conferencedays = $this->_customdata['conferencedays'];

        foreach ($conferencedays as $day) {
            $checkboxname = 'disableddates[' . $day . ']';
            $reasonname = 'disabledreasons[' . $day . ']';
            $daylabel = userdate($day, get_string('strftimedate', 'langconfig'));

            // Both controls carry an aria-label naming the day: the group's visible
            // label is not programmatically associated with either element, and a
            // placeholder is not a label (WCAG 3.3.2), so without these a screen
            // reader announces two unnamed controls per day.
            $checkbox = $mform->createElement('advcheckbox', $checkboxname, '', '', [
                'aria-label' => get_string('disabledaycheckbox', 'mod_confsubmissions', $daylabel),
            ]);
            $reason = $mform->createElement(
                'text',
                $reasonname,
                '',
                [
                    'size' => 30,
                    'placeholder' => get_string('disableddatereason', 'mod_confsubmissions'),
                    'aria-label' => get_string('disableddatereasonfor', 'mod_confsubmissions', $daylabel),
                ]
            );

            $mform->addGroup(
                [$checkbox, $reason],
                'disabledgroup[' . $day . ']',
                $daylabel,
                ' ',
                false
            );
            $mform->setType($checkboxname, PARAM_BOOL);
            $mform->setType($reasonname, PARAM_TEXT);
            // No setDefault() here, deliberately, for either element: HTML_QuickForm_
            // element::_findValue() checks a literal bracket-key default (e.g.
            // setDefault('disableddates[123]', 0)) BEFORE it ever looks at a
            // nested-array value supplied later via set_data() (dates.php always calls
            // set_data(['disableddates' => [...], 'disabledreasons' => [...]]) to
            // restore the real saved state) -- the same quirk already documented on
            // submission_form.php's own 'speakermanual' repeat option. A setDefault()
            // call here would permanently win, so every checkbox would render
            // unchecked (and every reason blank) regardless of what was actually saved
            // (caught live, for the checkbox: the disable-dates page never showed a
            // previously-disabled day as checked after a reload). Omitting it lets
            // set_data()'s value apply, and PHP/HTML's natural "unchecked"/"empty"
            // defaults cover a brand new instance dates.php has not yet called
            // set_data() for.
        }

        $this->add_action_buttons(false, get_string('savechanges'));
    }
}
