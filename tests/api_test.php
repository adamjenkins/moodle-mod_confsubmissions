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
