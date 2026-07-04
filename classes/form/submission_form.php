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

use mod_confsubmissions\api;
use mod_confsubmissions\local\limits;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * The presenter-facing submission form: title, abstract, track, any enabled
 * optional fields, and a repeating group of speakers (primary presenter plus
 * co-presenters).
 *
 * Required custom data:
 * - cmid: int, the course-module id
 * - confsubmissions: stdClass, the confsubmissions instance record
 * - speakers: stdClass[], existing speaker rows when editing (empty array for new)
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission_form extends \moodleform {
    /** @var int Maximum number of speaker rows (primary + co-presenters) a submission may have. */
    const MAX_SPEAKERS = 10;

    /** @var int[] Valid trackid option keys for this instance, including 0 ("no track"); set by definition(). */
    protected $trackoptionids = [];

    /**
     * @var int[] Valid submissiontypeid option keys for this instance (excludes the
     *      sentinel 0, unlike $trackoptionids -- see definition()'s docblock for why
     *      this field's presence/requiredness differs from trackid's). Set by definition().
     */
    protected $submissiontypeoptionids = [];

    /**
     * Defines the form fields.
     */
    public function definition() {
        global $USER;

        $mform = $this->_form;
        $cmid = $this->_customdata['cmid'];
        $cs = $this->_customdata['confsubmissions'];
        $speakers = $this->_customdata['speakers'] ?? [];

        $mform->addElement('header', 'submissiondetails', get_string('submissiondetails', 'mod_confsubmissions'));

        $mform->addElement('text', 'title', get_string('title', 'mod_confsubmissions'), ['size' => 64]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', get_string('required'), 'required', null, 'client');

        $mform->addElement(
            'textarea',
            'abstract',
            get_string('abstract', 'mod_confsubmissions'),
            ['rows' => 10, 'cols' => 60]
        );
        $mform->setType('abstract', PARAM_TEXT);
        $mform->addRule('abstract', get_string('required'), 'required', null, 'client');

        $trackoptions = [0 => get_string('notrack', 'mod_confsubmissions')];
        foreach (api::get_tracks($cmid) as $track) {
            $trackoptions[$track->id] = format_string($track->name);
        }
        $this->trackoptionids = array_keys($trackoptions);
        $mform->addElement('select', 'trackid', get_string('track', 'mod_confsubmissions'), $trackoptions);
        $mform->setType('trackid', PARAM_INT);

        // Unlike trackid (optional, with an explicit "no track" sentinel), a submission
        // type is required -- but only once the organiser has actually configured at
        // least one; an instance with none configured yet must not block submissions on
        // a choice that does not exist (matches how an instance with zero tracks simply
        // never shows the trackid field as mandatory either).
        $submissiontypes = api::get_submission_types($cmid);
        if ($submissiontypes) {
            $submissiontypeoptions = ['' => get_string('choosedots')];
            foreach ($submissiontypes as $submissiontype) {
                $submissiontypeoptions[$submissiontype->id] = format_string($submissiontype->name);
            }
            $this->submissiontypeoptionids = array_map('intval', array_keys($submissiontypes));
            $mform->addElement(
                'select',
                'submissiontypeid',
                get_string('submissiontype', 'mod_confsubmissions'),
                $submissiontypeoptions
            );
            $mform->setType('submissiontypeid', PARAM_INT);
            $mform->addRule('submissiontypeid', get_string('required'), 'required', null, 'client');
        }

        // Fixed optional fields, only rendered when enabled for this instance.
        foreach (api::get_enabled_fieldnames($cs->id) as $fieldname) {
            $mform->addElement('text', 'field_' . $fieldname, get_string('field_' . $fieldname, 'mod_confsubmissions'));
            $mform->setType('field_' . $fieldname, PARAM_TEXT);
        }

        // Speakers: a repeating group. Row 0 is the primary presenter (defaults to the
        // current user) and is not removable; subsequent rows are co-presenters and can
        // be added/removed freely.
        $mform->addElement('header', 'speakersheader', get_string('speakers', 'mod_confsubmissions'));
        $mform->setExpanded('speakersheader');

        $repeatcount = max(count($speakers), 1);

        $autocompleteoptions = [
            'ajax'        => 'mod_confsubmissions/speaker_selector',
            'multiple'    => false,
            'placeholder' => get_string('selectuser', 'mod_confsubmissions'),
            'data-cmid'   => $cmid,
        ];

        // The autocomplete element only shows a real name for a pre-selected value if
        // that value has a matching choice in its options array; with an empty array it
        // falls back to rendering the raw stored value (e.g. a bare userid like "5")
        // instead of a name. Seed it with every userid this row-set could pre-select:
        // the current user (the row 0 default on a new submission) and every existing
        // speaker's userid (when editing).
        $useroptions = [(int) $USER->id => fullname($USER)];
        foreach ($speakers as $speaker) {
            if (!empty($speaker->userid) && !isset($useroptions[(int) $speaker->userid])) {
                $speakeruser = \core_user::get_user($speaker->userid);
                if ($speakeruser) {
                    $useroptions[(int) $speaker->userid] = fullname($speakeruser);
                }
            }
        }

        $positionoptions = [];
        for ($n = 1; $n <= self::MAX_SPEAKERS; $n++) {
            $positionoptions[$n] = $n;
        }

        $repeatarray = [
            $mform->createElement('header', 'speakerno', get_string('speakerno', 'mod_confsubmissions', '{no}')),
            $mform->createElement(
                'autocomplete',
                'speakeruserid',
                get_string('selectuser', 'mod_confsubmissions'),
                $useroptions,
                $autocompleteoptions
            ),
            $mform->createElement(
                'advcheckbox',
                'speakermanual',
                '',
                get_string('entermanually', 'mod_confsubmissions')
            ),
            $mform->createElement('text', 'speakername', get_string('speakername', 'mod_confsubmissions')),
            $mform->createElement('text', 'speakeremail', get_string('speakeremail', 'mod_confsubmissions')),
            $mform->createElement(
                'select',
                'speakerposition',
                get_string('speakerposition', 'mod_confsubmissions'),
                $positionoptions
            ),
            $mform->createElement(
                'submit',
                'speakerdelete',
                get_string('removespeaker', 'mod_confsubmissions', '{no}'),
                [],
                false
            ),
        ];

        $repeatoptions = [
            'speakeruserid'   => ['type' => PARAM_INT],
            // No 'default' key here: repeat_elements() applies a 'default' via
            // setDefault('speakermanual[N]', ...), which HTML_QuickForm_element::_findValue()
            // looks up *before* the nested array set_data() later supplies (it checks the
            // literal 'speakermanual[N]' key first, only falling back to the nested
            // 'speakermanual' => [N => ...] array if that literal key was never set). With a
            // 'default' set here, that stale repeat-time default always wins over whatever
            // set_data() tries to restore, so every row's checkbox forgets a saved manual
            // entry and resets to search mode on every reload. Omitting it lets set_data()'s
            // value (edit.php) or PHP/HTML's natural "unchecked" (new submission) apply.
            'speakermanual'   => ['type' => PARAM_BOOL],
            'speakername'     => ['type' => PARAM_TEXT],
            'speakeremail'    => ['type' => PARAM_RAW_TRIMMED],
            'speakerposition' => ['type' => PARAM_INT],
        ];

        $nextel = $this->repeat_elements(
            $repeatarray,
            $repeatcount,
            $repeatoptions,
            'speakerrepeats',
            'speakeraddfields',
            1,
            get_string('addspeaker', 'mod_confsubmissions'),
            true,
            'speakerdelete'
        );

        // Row 0 is the primary presenter: it cannot be removed or reordered.
        $mform->removeElement('speakerdelete[0]');
        $mform->removeElement('speakerposition[0]');

        for ($i = 0; $i < $nextel; $i++) {
            $mform->hideIf('speakername[' . $i . ']', 'speakermanual[' . $i . ']', 'notchecked');
            $mform->hideIf('speakeremail[' . $i . ']', 'speakermanual[' . $i . ']', 'notchecked');
            $mform->hideIf('speakeruserid[' . $i . ']', 'speakermanual[' . $i . ']', 'checked');

            // Each repeated "Speaker N" header is its own collapsible section. formslib
            // collapses every header past the first two by default (see formslib.php's
            // accept_set_nonvisible_elements()), which otherwise leaves every speaker row's
            // actual fields (the autocomplete/checkbox/name/email inputs) hidden behind a
            // collapsed toggle with nothing visibly rendered beneath the "Speakers" heading.
            $mform->setExpanded('speakerno[' . $i . ']', true);
        }

        if (empty($speakers)) {
            // New submission: default row 0 to the current user.
            $this->set_data((object) [
                'speakeruserid' => [0 => $USER->id],
            ]);
        }

        $this->add_action_buttons();
    }

    /**
     * Server-side validation: title/abstract limits (mirrors the JS live counter),
     * and speaker-row sanity checks.
     *
     * @param array $data Submitted form data
     * @param array $files Uploaded files
     * @return array Errors keyed by field name
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $cs = $this->_customdata['confsubmissions'];
        $context = \context_module::instance($this->_customdata['cmid']);

        if (isset($data['trackid']) && !in_array((int) $data['trackid'], $this->trackoptionids, true)) {
            $errors['trackid'] = get_string('error:invalidtrack', 'mod_confsubmissions');
        }

        if ($this->submissiontypeoptionids) {
            // The field is only rendered (and only required) when the instance has at
            // least one submission type configured -- see definition()'s docblock.
            $submissiontypeid = (int) ($data['submissiontypeid'] ?? 0);
            if (!in_array($submissiontypeid, $this->submissiontypeoptionids, true)) {
                $errors['submissiontypeid'] = get_string('error:invalidsubmissiontype', 'mod_confsubmissions');
            }
        }

        if (limits::exceeds($data['title'] ?? '', (int) $cs->titlelimit, $cs->titlelimittype)) {
            $errors['title'] = get_string('error:titletoolong', 'mod_confsubmissions', [
                'limit' => $cs->titlelimit,
                'type'  => get_string('limittype_' . $cs->titlelimittype, 'mod_confsubmissions'),
                'count' => limits::count($data['title'] ?? '', $cs->titlelimittype),
            ]);
        }

        if (limits::exceeds($data['abstract'] ?? '', (int) $cs->abstractlimit, $cs->abstractlimittype)) {
            $errors['abstract'] = get_string('error:abstracttoolong', 'mod_confsubmissions', [
                'limit' => $cs->abstractlimit,
                'type'  => get_string('limittype_' . $cs->abstractlimittype, 'mod_confsubmissions'),
                'count' => limits::count($data['abstract'] ?? '', $cs->abstractlimittype),
            ]);
        }

        $speakercount = 0;
        $repeatcount = (int) ($data['speakerrepeats'] ?? 0);

        if ($repeatcount > self::MAX_SPEAKERS) {
            $errors['speakersheader'] = get_string('error:toomanyspeakers', 'mod_confsubmissions', self::MAX_SPEAKERS);
            $repeatcount = self::MAX_SPEAKERS;
        }

        for ($i = 0; $i < $repeatcount; $i++) {
            if (!array_key_exists("speakermanual", $data) || !array_key_exists($i, $data['speakermanual'] ?? [])) {
                // This row was deleted (no-submit "remove" button); nothing to validate.
                continue;
            }

            $speakercount++;
            $ismanual = !empty($data['speakermanual'][$i]);

            if ($ismanual) {
                if (trim((string) ($data['speakername'][$i] ?? '')) === '') {
                    $errors['speakername[' . $i . ']'] = get_string('required');
                }
                $email = trim((string) ($data['speakeremail'][$i] ?? ''));
                if ($email !== '' && !validate_email($email)) {
                    $errors['speakeremail[' . $i . ']'] = get_string('invalidemail');
                }
            } else if (empty($data['speakeruserid'][$i])) {
                $errors['speakeruserid[' . $i . ']'] = get_string('required');
            } else if (!is_enrolled($context, (int) $data['speakeruserid'][$i])) {
                // Guards against a crafted POST naming an arbitrary site user (not enrolled in
                // this course) as a speaker, which would attach their real identity to this
                // submission without their knowledge. The autocomplete only ever offers
                // enrolled users, so a legitimate client never triggers this.
                $errors['speakeruserid[' . $i . ']'] = get_string('error:usernotenrolled', 'mod_confsubmissions');
            }
        }

        if ($speakercount < 1) {
            $errors['speakersheader'] = get_string('error:needsspeaker', 'mod_confsubmissions');
        }

        return $errors;
    }

    /**
     * Extracts the submitted speaker rows into the ordered, plain-array format
     * expected by \mod_confsubmissions\api::sync_speakers().
     *
     * The first surviving row is always kept first (it becomes the 'primary' speaker
     * in sync_speakers()); every subsequent surviving row is a co-presenter, and those
     * are ordered by their submitted 'speakerposition' value (ties, or a missing value,
     * fall back to the order they were submitted in, so this is a stable sort) - this is
     * what lets a submitter explicitly control co-presenter display order via the
     * "Display order" selector, rather than it always following row/insertion order.
     *
     * @param \stdClass $data The validated form data (as returned by get_data())
     * @return array Ordered list of speaker rows
     */
    public static function extract_speakers(\stdClass $data): array {
        $primary = null;
        $cospeakers = [];
        $repeatcount = (int) ($data->speakerrepeats ?? 0);

        for ($i = 0; $i < $repeatcount; $i++) {
            if (!isset($data->speakermanual) || !array_key_exists($i, (array) $data->speakermanual)) {
                continue;
            }

            $ismanual = !empty($data->speakermanual[$i]);

            if ($ismanual) {
                $speaker = [
                    'name'  => trim((string) ($data->speakername[$i] ?? '')),
                    'email' => trim((string) ($data->speakeremail[$i] ?? '')) ?: null,
                ];
            } else if (!empty($data->speakeruserid[$i])) {
                $speaker = ['userid' => (int) $data->speakeruserid[$i]];
            } else {
                continue;
            }

            if ($primary === null) {
                $primary = $speaker;
                continue;
            }

            $order = count($cospeakers);
            $position = isset($data->speakerposition[$i]) ? (int) $data->speakerposition[$i] : ($order + 2);
            $cospeakers[] = ['position' => $position, 'order' => $order, 'speaker' => $speaker];
        }

        usort($cospeakers, function ($a, $b) {
            return $a['position'] <=> $b['position'] ?: $a['order'] <=> $b['order'];
        });

        $speakers = [];
        if ($primary !== null) {
            $speakers[] = $primary;
        }
        foreach ($cospeakers as $row) {
            $speakers[] = $row['speaker'];
        }

        return $speakers;
    }

    /**
     * Extracts the submitted optional-field values into fieldname => value pairs
     * for \mod_confsubmissions\api::sync_optional_fields().
     *
     * @param \stdClass $data The validated form data (as returned by get_data())
     * @param string[] $enabledfieldnames The optional fields enabled for this instance
     * @return array Values keyed by fieldname
     */
    public static function extract_optional_fields(\stdClass $data, array $enabledfieldnames): array {
        $values = [];
        foreach ($enabledfieldnames as $fieldname) {
            $values[$fieldname] = trim((string) ($data->{'field_' . $fieldname} ?? ''));
        }
        return $values;
    }
}
