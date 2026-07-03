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

namespace mod_confsubmissions\form;

use advanced_testcase;
use mod_confsubmissions\api;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for \mod_confsubmissions\form\submission_form: server-side title/abstract
 * limit enforcement (the authoritative check; the AMD live counter is UX-only),
 * in both character and word counting modes.
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(submission_form::class)]
final class submission_form_test extends advanced_testcase {
    /**
     * Builds a submission_form instance for the given instance limits.
     *
     * @param \stdClass $confsubmissions The confsubmissions instance record (limits only matter)
     * @return submission_form
     */
    private function build_form(\stdClass $confsubmissions): submission_form {
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('confsubmissions', array_merge(
            ['course' => $course->id],
            (array) $confsubmissions
        ));
        $cm = get_coursemodule_from_instance('confsubmissions', $instance->id);

        return new submission_form(null, [
            'cmid'            => $cm->id,
            'confsubmissions' => $instance,
            'speakers'        => [],
        ]);
    }

    /**
     * A minimal, otherwise-valid submitted-data array with one primary speaker row,
     * so speaker validation always passes and only the limit checks under test can fail.
     *
     * @param string $title
     * @param string $abstract
     * @return array
     */
    private function base_data(string $title, string $abstract): array {
        return [
            'title'          => $title,
            'abstract'       => $abstract,
            'trackid'        => 0,
            'speakerrepeats' => 1,
            'speakermanual'  => [0 => 1],
            'speakername'    => [0 => 'Primary Presenter'],
            'speakeremail'   => [0 => ''],
        ];
    }

    /**
     * A title within a character-mode limit passes; one over it fails, with the error
     * attached to the 'title' field.
     */
    public function test_title_character_limit_enforced(): void {
        $this->resetAfterTest();

        $form = $this->build_form((object) ['titlelimit' => 10, 'titlelimittype' => 'chars']);

        $errors = $form->validation($this->base_data('short', 'Abstract text'), []);
        $this->assertArrayNotHasKey('title', $errors);

        $errors = $form->validation($this->base_data('this title is far too long', 'Abstract text'), []);
        $this->assertArrayHasKey('title', $errors);
    }

    /**
     * A title within a word-mode limit passes; one over it fails.
     */
    public function test_title_word_limit_enforced(): void {
        $this->resetAfterTest();

        $form = $this->build_form((object) ['titlelimit' => 3, 'titlelimittype' => 'words']);

        $errors = $form->validation($this->base_data('one two three', 'Abstract text'), []);
        $this->assertArrayNotHasKey('title', $errors);

        $errors = $form->validation($this->base_data('one two three four', 'Abstract text'), []);
        $this->assertArrayHasKey('title', $errors);
    }

    /**
     * An abstract within a character-mode limit passes; one over it fails, with the
     * error attached to the 'abstract' field. A limit of 0 means unlimited.
     */
    public function test_abstract_character_limit_enforced(): void {
        $this->resetAfterTest();

        $form = $this->build_form((object) ['abstractlimit' => 20, 'abstractlimittype' => 'chars']);

        $errors = $form->validation($this->base_data('Title', 'A short abstract'), []);
        $this->assertArrayNotHasKey('abstract', $errors);

        $errors = $form->validation($this->base_data('Title', str_repeat('x', 50)), []);
        $this->assertArrayHasKey('abstract', $errors);

        $unlimited = $this->build_form((object) ['abstractlimit' => 0, 'abstractlimittype' => 'chars']);
        $errors = $unlimited->validation($this->base_data('Title', str_repeat('x', 5000)), []);
        $this->assertArrayNotHasKey('abstract', $errors);
    }

    /**
     * An abstract within a word-mode limit passes; one over it fails.
     */
    public function test_abstract_word_limit_enforced(): void {
        $this->resetAfterTest();

        $form = $this->build_form((object) ['abstractlimit' => 5, 'abstractlimittype' => 'words']);

        $errors = $form->validation($this->base_data('Title', 'one two three four five'), []);
        $this->assertArrayNotHasKey('abstract', $errors);

        $errors = $form->validation($this->base_data('Title', 'one two three four five six'), []);
        $this->assertArrayHasKey('abstract', $errors);
    }

    /**
     * At least one speaker row is required; a submission with every row deleted fails
     * validation even when title/abstract are otherwise valid.
     */
    public function test_needs_at_least_one_speaker(): void {
        $this->resetAfterTest();

        $form = $this->build_form((object) ['titlelimit' => 0, 'abstractlimit' => 0]);

        $data = $this->base_data('Title', 'Abstract');
        $data['speakermanual'] = [];

        $errors = $form->validation($data, []);
        $this->assertArrayHasKey('speakersheader', $errors);
    }

    /**
     * A speakeruserid naming a user who is NOT enrolled in the submission's course is
     * rejected, even though it passes PARAM_INT typing (regression test: this used to
     * be trusted unchecked, allowing an arbitrary site user's identity to be attached
     * to a submission via a crafted POST).
     */
    public function test_speaker_userid_must_be_enrolled(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('confsubmissions', [
            'course'      => $course->id,
            'titlelimit'  => 0,
            'abstractlimit' => 0,
        ]);
        $cm = get_coursemodule_from_instance('confsubmissions', $instance->id);

        $enrolled = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $notenrolled = $this->getDataGenerator()->create_user();

        $form = new submission_form(null, [
            'cmid'            => $cm->id,
            'confsubmissions' => $instance,
            'speakers'        => [],
        ]);

        $data = [
            'title' => 'Title', 'abstract' => 'Abstract', 'trackid' => 0,
            'speakerrepeats' => 1,
            'speakermanual'  => [0 => 0],
            'speakeruserid'  => [0 => $enrolled->id],
        ];
        $errors = $form->validation($data, []);
        $this->assertArrayNotHasKey('speakeruserid[0]', $errors);

        $data['speakeruserid'] = [0 => $notenrolled->id];
        $errors = $form->validation($data, []);
        $this->assertArrayHasKey('speakeruserid[0]', $errors);
    }

    /**
     * A trackid outside the instance's own track list (e.g. belonging to a different
     * confsubmissions instance entirely) is rejected server-side.
     */
    public function test_trackid_must_belong_to_instance(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('confsubmissions', [
            'course' => $course->id, 'titlelimit' => 0, 'abstractlimit' => 0,
        ]);
        $cm = get_coursemodule_from_instance('confsubmissions', $instance->id);

        $foreigntrackid = api::add_track($instance->id + 1000, 'Foreign track');

        $form = new submission_form(null, [
            'cmid'            => $cm->id,
            'confsubmissions' => $instance,
            'speakers'        => [],
        ]);

        $data = $this->base_data('Title', 'Abstract');
        $data['trackid'] = $foreigntrackid;

        $errors = $form->validation($data, []);
        $this->assertArrayHasKey('trackid', $errors);
    }

    /**
     * More than MAX_SPEAKERS rows fails validation.
     */
    public function test_too_many_speakers_rejected(): void {
        $this->resetAfterTest();

        $form = $this->build_form((object) ['titlelimit' => 0, 'abstractlimit' => 0]);

        $data = [
            'title' => 'Title', 'abstract' => 'Abstract', 'trackid' => 0,
            'speakerrepeats' => submission_form::MAX_SPEAKERS + 1,
            'speakermanual'  => array_fill(0, submission_form::MAX_SPEAKERS + 1, 1),
            'speakername'    => array_fill(0, submission_form::MAX_SPEAKERS + 1, 'Someone'),
            'speakeremail'   => array_fill(0, submission_form::MAX_SPEAKERS + 1, ''),
        ];

        $errors = $form->validation($data, []);
        $this->assertArrayHasKey('speakersheader', $errors);
    }

    /**
     * extract_speakers() converts the repeat-indexed submitted data into the ordered
     * plain-array format api::sync_speakers() expects, correctly distinguishing manual
     * entries from picked users and skipping deleted rows.
     */
    public function test_extract_speakers(): void {
        $this->resetAfterTest();

        $data = (object) [
            'speakerrepeats' => 3,
            'speakermanual'  => [0 => 0, 2 => 1], // Index 1 was deleted (no-submit remove button).
            'speakeruserid'  => [0 => 42],
            'speakername'    => [2 => 'Co Presenter'],
            'speakeremail'   => [2 => 'co@example.com'],
        ];

        $speakers = submission_form::extract_speakers($data);

        $this->assertSame([
            ['userid' => 42],
            ['name' => 'Co Presenter', 'email' => 'co@example.com'],
        ], $speakers);
    }

    /**
     * extract_speakers() orders co-presenters by their submitted 'speakerposition'
     * value, not by row/insertion order, and always keeps the first surviving row as
     * the primary speaker regardless of its position value (Revision round 1,
     * 2026-07-03 -- the "speaker display order should be specifiable" fix).
     */
    public function test_extract_speakers_respects_submitted_position(): void {
        $this->resetAfterTest();

        $data = (object) [
            'speakerrepeats'   => 3,
            'speakermanual'    => [0 => 0, 1 => 1, 2 => 1],
            'speakeruserid'    => [0 => 42],
            'speakername'      => [1 => 'Second co-presenter', 2 => 'First co-presenter'],
            'speakeremail'     => [1 => 'second@example.com', 2 => 'first@example.com'],
            // Row 1 (submitted second) should display AFTER row 2 (submitted third),
            // because its position value is higher -- the opposite of insertion order.
            'speakerposition'  => [1 => 3, 2 => 2],
        ];

        $speakers = submission_form::extract_speakers($data);

        $this->assertSame([
            ['userid' => 42],
            ['name' => 'First co-presenter', 'email' => 'first@example.com'],
            ['name' => 'Second co-presenter', 'email' => 'second@example.com'],
        ], $speakers);
    }

    /**
     * A tied or missing 'speakerposition' value falls back to submission order, so
     * extract_speakers() never produces an unstable/arbitrary ordering.
     */
    public function test_extract_speakers_position_tie_falls_back_to_submission_order(): void {
        $this->resetAfterTest();

        $data = (object) [
            'speakerrepeats'  => 3,
            'speakermanual'   => [0 => 0, 1 => 1, 2 => 1],
            'speakeruserid'   => [0 => 42],
            'speakername'     => [1 => 'Submitted first', 2 => 'Submitted second'],
            'speakeremail'    => [1 => '', 2 => ''],
            'speakerposition' => [1 => 2, 2 => 2],
        ];

        $speakers = submission_form::extract_speakers($data);

        $this->assertSame('Submitted first', $speakers[1]['name']);
        $this->assertSame('Submitted second', $speakers[2]['name']);
    }

    /**
     * extract_optional_fields() only extracts values for the enabled fieldnames given.
     */
    public function test_extract_optional_fields(): void {
        $this->resetAfterTest();

        $data = (object) [
            'field_language' => 'English',
            'field_subtopic' => '',
        ];

        $values = submission_form::extract_optional_fields($data, ['language', 'subtopic']);

        $this->assertSame(['language' => 'English', 'subtopic' => ''], $values);
    }
}
