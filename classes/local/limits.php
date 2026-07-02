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

namespace mod_confsubmissions\local;

/**
 * Character/word counting and limit-checking helper.
 *
 * This is the single source of truth for how "length" is measured for the
 * title/abstract limits. The AMD module amd/src/limitcounter.js mirrors this
 * logic in JavaScript for the live counter (chars: str.length; words:
 * str.trim().split(/\s+/).filter(Boolean).length) so that what the presenter
 * sees while typing matches what is enforced server-side in
 * \mod_confsubmissions\form\submission_form::validation(). If either side of
 * this logic changes, the other must be updated to match.
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class limits {
    /** @var string Count length in characters. */
    const TYPE_CHARS = 'chars';

    /** @var string Count length in words. */
    const TYPE_WORDS = 'words';

    /**
     * Counts the length of a value, either in characters or words.
     *
     * @param string $value The text to measure
     * @param string $type Either self::TYPE_CHARS or self::TYPE_WORDS
     * @return int The character or word count
     */
    public static function count(string $value, string $type): int {
        if ($type === self::TYPE_WORDS) {
            $parts = preg_split('/\s+/', trim($value));
            return count(array_filter($parts, fn($part) => $part !== ''));
        }

        return \core_text::strlen($value);
    }

    /**
     * Checks whether a value exceeds a configured limit.
     *
     * A limit of 0 means unlimited, so this always returns false in that case.
     *
     * @param string $value The text to check
     * @param int $limit The configured limit; 0 means unlimited
     * @param string $type Either self::TYPE_CHARS or self::TYPE_WORDS
     * @return bool True if the value exceeds the limit
     */
    public static function exceeds(string $value, int $limit, string $type): bool {
        if ($limit <= 0) {
            return false;
        }

        return self::count($value, $type) > $limit;
    }
}
