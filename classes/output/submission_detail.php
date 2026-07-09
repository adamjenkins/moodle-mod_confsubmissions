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

namespace mod_confsubmissions\output;

use renderable;
use renderer_base;
use templatable;
use stdClass;

/**
 * Read-only detail view of a single submission (submission.php).
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission_detail implements renderable, templatable {
    /** @var stdClass The submission record */
    protected $submission;

    /** @var stdClass[] Speaker records, in sort order */
    protected $speakers;

    /** @var stdClass[] This instance's optional-field configuration rows, keyed by id */
    protected $fields;

    /** @var array Optional field values keyed by fieldid */
    protected $fieldvalues;

    /** @var stdClass|null The confsubmissions_track record, or null if unassigned */
    protected $track;

    /** @var bool Whether the current user may edit this submission */
    protected $canedit;

    /** @var \moodle_url|null The edit URL, when $canedit is true */
    protected $editurl;

    /**
     * Constructor.
     *
     * @param stdClass $submission The submission record
     * @param array $speakers Speaker records, in sort order
     * @param array $fields This instance's optional-field configuration rows, keyed by id
     * @param array $fieldvalues Optional field values keyed by fieldid
     * @param stdClass|null $track The confsubmissions_track record, or null if unassigned
     * @param bool $canedit Whether the current user may edit this submission
     * @param \moodle_url|null $editurl The edit URL, when $canedit is true
     */
    public function __construct(
        stdClass $submission,
        array $speakers,
        array $fields,
        array $fieldvalues,
        ?stdClass $track,
        bool $canedit,
        ?\moodle_url $editurl
    ) {
        $this->submission = $submission;
        $this->speakers = $speakers;
        $this->fields = $fields;
        $this->fieldvalues = $fieldvalues;
        $this->track = $track;
        $this->canedit = $canedit;
        $this->editurl = $editurl;
    }

    /**
     * Exports data for the submission_detail.mustache template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $speakers = [];
        foreach ($this->speakers as $speaker) {
            if (!empty($speaker->userid)) {
                $user = \core_user::get_user($speaker->userid);
                $name = $user ? fullname($user) : '';
            } else {
                $name = $speaker->name;
            }

            $speakers[] = [
                'name'      => $name,
                'email'     => $speaker->email,
                'isprimary' => $speaker->role === 'primary',
            ];
        }

        $fields = [];
        foreach ($this->fields as $field) {
            $value = $this->fieldvalues[$field->id] ?? '';
            if ($value === '' || $value === null) {
                continue;
            }

            $fields[] = [
                // Escape => false, same as 'value' below: the template outputs both via
                // {{...}}, so Mustache's own auto-escaping must be the only escape --
                // format_string()'s default escaping on top rendered a field named
                // "R&D" as the literal text "R&amp;D".
                'label' => format_string($field->name, true, ['escape' => false]),
                'value' => $this->format_field_value($field, $value),
            ];
        }

        return [
            // format_string() already HTML-escapes by default (e.g. '&' -> '&amp;'); the
            // template outputs both of these unescaped ({{{...}}}, not {{...}}), matching
            // 'abstract' below -- otherwise Mustache's own default auto-escaping runs a
            // SECOND time on top of format_string()'s, turning '&amp;' into '&amp;amp;'
            // (user report, 2026-07-08: this is exactly why "Product & Design" rendered as
            // the literal text "Product &amp; Design" on submission.php).
            'title'         => format_string($this->submission->title),
            'abstract'      => nl2br(s($this->submission->abstract)),
            'trackpill'     => $this->get_track_pill_html(),
            'status'        => get_string('status_' . $this->submission->status, 'mod_confsubmissions'),
            'timecreated'   => userdate($this->submission->timecreated),
            'timemodified'  => userdate($this->submission->timemodified),
            'speakers'      => $speakers,
            'hasfields'     => !empty($fields),
            'fields'        => $fields,
            'canedit'       => $this->canedit,
            'editurl'       => $this->editurl ? $this->editurl->out(false) : null,
        ];
    }

    /**
     * Builds the coloured pill-badge HTML for this submission's track, or a plain "No
     * track" string when unassigned -- matching the visual language
     * mod_confprogram\local\field_formatter::get_track_pill_html() and
     * mod_confscheduler's own track pills already use elsewhere in this project (user
     * report, 2026-07-08: this page was showing the track as plain text, not a pill).
     * A duplicate implementation, not a shared call into mod_confprogram\local\
     * field_formatter::get_track_pill_html(): this plugin is upstream of
     * mod_confprogram (see RELATIONS.md's dependency graph) and must not depend on it.
     *
     * Deliberately WITHOUT escape => false on format_string() below: html_writer::tag()
     * does not itself escape its content argument, so format_string()'s own default
     * HTML-entity escaping is exactly what's needed for this to be valid HTML -- not a
     * double-escape, since (unlike 'title'/'abstract' above) this returned string is
     * never passed through Mustache's auto-escaping downstream (the template outputs
     * it via {{{trackpill}}}).
     *
     * @return string Safe HTML: a <span> pill, or an already-escaped "No track" string
     */
    protected function get_track_pill_html(): string {
        if (!$this->track) {
            return get_string('notrack', 'mod_confsubmissions');
        }

        $name = format_string($this->track->name, true);
        $style = '';
        if (!empty($this->track->colour) && preg_match('/^#[0-9a-fA-F]{6}$/', $this->track->colour)) {
            $textcolour = self::contrast_text_colour($this->track->colour);
            $style = "background-color:{$this->track->colour};color:{$textcolour}";
        }

        return \html_writer::tag('span', $name, [
            'class' => 'mod_confsubmissions-track-pill',
            'style' => $style,
        ]);
    }

    /**
     * Picks black or white text to sit legibly on top of a given background hex
     * colour, using the classic YIQ "perceived brightness" formula. A duplicate of
     * mod_confprogram\local\field_formatter::contrast_text_colour() (itself a PHP-side
     * duplicate of mod_confscheduler/amd/src/colour_utils.js's contrastTextColour()) --
     * kept in sync by hand, matching this project's established practice of
     * duplicating small pure display logic rather than sharing it across plugins.
     *
     * @param string $hex A 6-digit hex colour, with or without a leading '#'
     * @return string '#000000' or '#ffffff'
     */
    private static function contrast_text_colour(string $hex): string {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;

        return $brightness >= 128 ? '#000000' : '#ffffff';
    }

    /**
     * Formats a single optional-field answer for display, per its own field type.
     * The template renders 'value' as escaped plain text (see submission_detail.mustache),
     * so this never needs to (and must not) return raw HTML.
     *
     * @param stdClass $field The confsubmissions_field configuration row
     * @param string $value The raw stored value (never '' -- callers already skip that)
     * @return string
     */
    protected function format_field_value(stdClass $field, string $value): string {
        switch ($field->type) {
            case 'checkbox':
                return $value === '1' ? get_string('yes') : get_string('no');
            case 'date':
                return userdate((int) $value, get_string('strftimedate'));
            case 'menu':
            case 'text':
            case 'textarea':
            case 'number':
            case 'url':
            default:
                return format_string($value, true, ['escape' => false]);
        }
    }
}
