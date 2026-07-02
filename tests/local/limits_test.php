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

declare(strict_types=1);

namespace mod_confsubmissions\local;

use advanced_testcase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for \mod_confsubmissions\local\limits, the character/word counting
 * helper shared by submission_form::validation() and the AMD live counter.
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(limits::class)]
final class limits_test extends advanced_testcase {
    /**
     * count() in character mode returns the raw string length.
     */
    public function test_count_chars(): void {
        $this->assertSame(5, limits::count('hello', limits::TYPE_CHARS));
        $this->assertSame(0, limits::count('', limits::TYPE_CHARS));
        $this->assertSame(11, limits::count('hello world', limits::TYPE_CHARS));
    }

    /**
     * count() in word mode splits on whitespace and discards empty tokens,
     * including leading/trailing/collapsed whitespace.
     */
    public function test_count_words(): void {
        $this->assertSame(2, limits::count('hello world', limits::TYPE_WORDS));
        $this->assertSame(0, limits::count('', limits::TYPE_WORDS));
        $this->assertSame(0, limits::count('   ', limits::TYPE_WORDS));
        $this->assertSame(3, limits::count('  hello   world  again  ', limits::TYPE_WORDS));
        $this->assertSame(1, limits::count('single', limits::TYPE_WORDS));
    }

    /**
     * exceeds() always returns false when the limit is 0 (unlimited), regardless of type.
     */
    public function test_exceeds_zero_limit_is_unlimited(): void {
        $this->assertFalse(limits::exceeds(str_repeat('x', 1000), 0, limits::TYPE_CHARS));
        $this->assertFalse(limits::exceeds(str_repeat('word ', 1000), 0, limits::TYPE_WORDS));
    }

    /**
     * exceeds() in character mode.
     */
    public function test_exceeds_chars(): void {
        $this->assertFalse(limits::exceeds('hello', 5, limits::TYPE_CHARS));
        $this->assertTrue(limits::exceeds('hello!', 5, limits::TYPE_CHARS));
    }

    /**
     * exceeds() in word mode.
     */
    public function test_exceeds_words(): void {
        $this->assertFalse(limits::exceeds('one two three', 3, limits::TYPE_WORDS));
        $this->assertTrue(limits::exceeds('one two three four', 3, limits::TYPE_WORDS));
    }
}
