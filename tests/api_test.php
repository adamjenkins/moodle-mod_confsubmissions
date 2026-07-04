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

namespace mod_confsubmissions;

use advanced_testcase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the write operations on \mod_confsubmissions\api: track CRUD
 * (used by tracks.php) and speaker/optional-field syncing (used by edit.php
 * when a submission is saved).
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(api::class)]
final class api_test extends advanced_testcase {
    /**
     * Creates a course plus a confsubmissions instance, for tests that need one.
     *
     * @return \stdClass The confsubmissions instance record
     */
    private function create_instance(): \stdClass {
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);
        $instance->id = (int) $instance->id;
        return $instance;
    }

    /**
     * add_track() appends tracks in increasing sortorder, and delete_track()
     * removes a track while leaving any submissions referencing it with no track.
     */
    public function test_track_crud(): void {
        $this->resetAfterTest();
        global $DB;

        $confsubmissions = $this->create_instance();

        $trackid1 = api::add_track($confsubmissions->id, 'Track A');
        $trackid2 = api::add_track($confsubmissions->id, 'Track B');

        $track1 = $DB->get_record('confsubmissions_track', ['id' => $trackid1]);
        $track2 = $DB->get_record('confsubmissions_track', ['id' => $trackid2]);

        $this->assertSame('Track A', $track1->name);
        $this->assertSame('Track B', $track2->name);
        $this->assertGreaterThan((int) $track1->sortorder, (int) $track2->sortorder);

        $cm = get_coursemodule_from_instance('confsubmissions', $confsubmissions->id);
        $tracks = api::get_tracks((int) $cm->id);
        $this->assertCount(2, $tracks);

        // A submission referencing the track should have its trackid cleared, not be deleted.
        $submissionid = $DB->insert_record('confsubmissions_submission', (object) [
            'confsubmissions' => $confsubmissions->id,
            'userid'          => 2,
            'title'           => 'Test submission',
            'abstract'        => 'Abstract text',
            'trackid'         => $trackid1,
            'status'          => 'submitted',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        $result = api::delete_track($trackid1);
        $this->assertTrue($result);

        $this->assertFalse($DB->record_exists('confsubmissions_track', ['id' => $trackid1]));
        $submission = $DB->get_record('confsubmissions_submission', ['id' => $submissionid]);
        $this->assertNull($submission->trackid);

        $remaining = api::get_tracks((int) $cm->id);
        $this->assertCount(1, $remaining);
    }

    /**
     * add_track()/update_track() persist a valid colour/icon, expose them via
     * get_tracks(), and reject an invalid colour or an icon outside the curated
     * allow-list (Revision round 1, 2026-07-03).
     */
    public function test_track_colour_and_icon(): void {
        $this->resetAfterTest();
        global $DB;

        $confsubmissions = $this->create_instance();
        $cm = get_coursemodule_from_instance('confsubmissions', $confsubmissions->id);

        $trackid = api::add_track($confsubmissions->id, 'Coloured track', '#3366cc', 'book');
        $track = $DB->get_record('confsubmissions_track', ['id' => $trackid]);
        $this->assertSame('#3366cc', $track->colour);
        $this->assertSame('book', $track->icon);

        $fetched = api::get_tracks((int) $cm->id);
        $this->assertSame('#3366cc', $fetched[$trackid]->colour);
        $this->assertSame('book', $fetched[$trackid]->icon);

        api::update_track($trackid, 'Coloured track', '#ff0000', 'rocket');
        $track = $DB->get_record('confsubmissions_track', ['id' => $trackid]);
        $this->assertSame('#ff0000', $track->colour);
        $this->assertSame('rocket', $track->icon);

        $this->expectException(\invalid_parameter_exception::class);
        api::add_track($confsubmissions->id, 'Bad colour', 'not-a-colour', null);
    }

    /**
     * An icon key outside the curated allow-list is rejected, since tracks are
     * deliberately themed only with built-in icons, never free text or an uploaded
     * asset (XSS/file-safety risk).
     */
    public function test_track_icon_must_be_in_allowlist(): void {
        $this->resetAfterTest();

        $confsubmissions = $this->create_instance();

        $this->expectException(\invalid_parameter_exception::class);
        api::add_track($confsubmissions->id, 'Bad icon', null, 'javascript:alert(1)');
    }

    /**
     * add_submission_type() appends types in increasing sortorder, update_submission_type()
     * changes name/duration in place, and delete_submission_type() removes a type while
     * leaving any submissions referencing it with no type (Revision round 1, 2026-07-04).
     */
    public function test_submission_type_crud(): void {
        $this->resetAfterTest();
        global $DB;

        $confsubmissions = $this->create_instance();
        $cm = get_coursemodule_from_instance('confsubmissions', $confsubmissions->id);

        $typeid1 = api::add_submission_type($confsubmissions->id, 'Lightning Talk', 15);
        $typeid2 = api::add_submission_type($confsubmissions->id, 'Workshop', 90);

        $type1 = $DB->get_record('confsubmissions_submissiontype', ['id' => $typeid1]);
        $type2 = $DB->get_record('confsubmissions_submissiontype', ['id' => $typeid2]);

        $this->assertSame('Lightning Talk', $type1->name);
        $this->assertSame(15, (int) $type1->durationminutes);
        $this->assertSame('Workshop', $type2->name);
        $this->assertSame(90, (int) $type2->durationminutes);
        $this->assertGreaterThan((int) $type1->sortorder, (int) $type2->sortorder);

        $types = api::get_submission_types((int) $cm->id);
        $this->assertCount(2, $types);

        api::update_submission_type($typeid1, 'Lightning Talk (updated)', 20);
        $updated = api::get_submission_type($typeid1);
        $this->assertSame('Lightning Talk (updated)', $updated->name);
        $this->assertSame(20, (int) $updated->durationminutes);

        // A submission referencing the type should have its submissiontypeid cleared,
        // not be deleted.
        $submissionid = $DB->insert_record('confsubmissions_submission', (object) [
            'confsubmissions'  => $confsubmissions->id,
            'userid'           => 2,
            'title'            => 'Test submission',
            'abstract'         => 'Abstract text',
            'submissiontypeid' => $typeid1,
            'status'           => 'submitted',
            'timecreated'      => time(),
            'timemodified'     => time(),
        ]);

        $result = api::delete_submission_type($typeid1);
        $this->assertTrue($result);

        $this->assertFalse($DB->record_exists('confsubmissions_submissiontype', ['id' => $typeid1]));
        $submission = $DB->get_record('confsubmissions_submission', ['id' => $submissionid]);
        $this->assertNull($submission->submissiontypeid);

        $remaining = api::get_submission_types((int) $cm->id);
        $this->assertCount(1, $remaining);
    }

    /**
     * A non-positive duration is rejected by both add_submission_type() and
     * update_submission_type().
     */
    public function test_submission_type_duration_must_be_positive(): void {
        $this->resetAfterTest();

        $confsubmissions = $this->create_instance();

        $this->expectException(\invalid_parameter_exception::class);
        api::add_submission_type($confsubmissions->id, 'Bad duration', 0);
    }

    /**
     * sync_speakers() replaces existing speaker rows, assigns role 'primary' to the
     * first row and 'co-presenter' to the rest, and correctly stores manually-entered
     * co-presenters (name/email, no userid) alongside enrolled-user speakers.
     */
    public function test_sync_speakers_manual_entry(): void {
        $this->resetAfterTest();
        global $DB;

        $confsubmissions = $this->create_instance();
        $submissionid = $DB->insert_record('confsubmissions_submission', (object) [
            'confsubmissions' => $confsubmissions->id,
            'userid'          => 2,
            'title'           => 'Test submission',
            'abstract'        => 'Abstract text',
            'status'          => 'submitted',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        api::sync_speakers($submissionid, [
            ['userid' => 2],
            ['name' => 'Jane Co-Presenter', 'email' => 'jane@example.com'],
        ]);

        $speakers = api::get_speakers($submissionid);
        $this->assertCount(2, $speakers);

        $speakers = array_values($speakers);
        $this->assertSame('primary', $speakers[0]->role);
        $this->assertSame(2, (int) $speakers[0]->userid);

        $this->assertSame('co-presenter', $speakers[1]->role);
        $this->assertNull($speakers[1]->userid);
        $this->assertSame('Jane Co-Presenter', $speakers[1]->name);
        $this->assertSame('jane@example.com', $speakers[1]->email);

        // Re-syncing with fewer speakers replaces the old set entirely.
        api::sync_speakers($submissionid, [['name' => 'Solo Presenter', 'email' => '']]);
        $speakers = array_values(api::get_speakers($submissionid));
        $this->assertCount(1, $speakers);
        $this->assertSame('primary', $speakers[0]->role);
        $this->assertSame('Solo Presenter', $speakers[0]->name);
    }

    /**
     * sync_optional_fields() stores non-empty answers and omits empty ones, and
     * fully replaces the previous set of answers on each call.
     */
    public function test_sync_optional_fields(): void {
        $this->resetAfterTest();
        global $DB;

        $confsubmissions = $this->create_instance();
        $submissionid = $DB->insert_record('confsubmissions_submission', (object) [
            'confsubmissions' => $confsubmissions->id,
            'userid'          => 2,
            'title'           => 'Test submission',
            'abstract'        => 'Abstract text',
            'status'          => 'submitted',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        api::sync_optional_fields($submissionid, [
            'language'        => 'English',
            'teachingcontext' => '',
            'subtopic'        => 'Testing',
        ]);

        $values = api::get_optional_field_values($submissionid);
        $this->assertSame(['language' => 'English', 'subtopic' => 'Testing'], $values);

        api::sync_optional_fields($submissionid, ['language' => '', 'subtopic' => 'Replaced']);
        $values = api::get_optional_field_values($submissionid);
        $this->assertSame(['subtopic' => 'Replaced'], $values);
    }

    /**
     * get_enabled_fieldnames() returns only the fields marked enabled, in sortorder.
     */
    public function test_get_enabled_fieldnames(): void {
        $this->resetAfterTest();
        global $DB;

        // The generator's confsubmissions_add_instance() call already upserts a
        // confsubmissions_field row per fixed fieldname (all disabled by default, since
        // the generator does not tick any of the mod_form.php checkboxes). Enable two
        // of the three here, leaving 'teachingcontext' disabled.
        $confsubmissions = $this->create_instance();

        $DB->set_field(
            'confsubmissions_field',
            'enabled',
            1,
            ['confsubmissions' => $confsubmissions->id, 'fieldname' => 'language']
        );
        $DB->set_field(
            'confsubmissions_field',
            'enabled',
            1,
            ['confsubmissions' => $confsubmissions->id, 'fieldname' => 'subtopic']
        );

        $enabled = api::get_enabled_fieldnames($confsubmissions->id);
        $this->assertSame(['language', 'subtopic'], $enabled);
    }
}
