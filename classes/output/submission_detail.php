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

    /** @var string|null The track name, or null if unassigned */
    protected $trackname;

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
     * @param string|null $trackname The track name, or null if unassigned
     * @param bool $canedit Whether the current user may edit this submission
     * @param \moodle_url|null $editurl The edit URL, when $canedit is true
     */
    public function __construct(
        stdClass $submission,
        array $speakers,
        array $fields,
        array $fieldvalues,
        ?string $trackname,
        bool $canedit,
        ?\moodle_url $editurl
    ) {
        $this->submission = $submission;
        $this->speakers = $speakers;
        $this->fields = $fields;
        $this->fieldvalues = $fieldvalues;
        $this->trackname = $trackname;
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
                'label' => format_string($field->name),
                'value' => $this->format_field_value($field, $value),
            ];
        }

        return [
            'title'         => format_string($this->submission->title),
            'abstract'      => nl2br(s($this->submission->abstract)),
            'trackname'     => $this->trackname ? format_string($this->trackname) : get_string('notrack', 'mod_confsubmissions'),
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
