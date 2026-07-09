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
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Smoke tests for mod_confsubmissions: confirms the plugin installs cleanly
 * and that a course-module instance can be created via the standard data
 * generator.
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class confsubmissions_test extends advanced_testcase {
    /**
     * An activity instance can be added to a course via the data generator,
     * and the resulting row exists in the confsubmissions table.
     */
    public function test_instance_can_be_added_via_generator(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', [
            'course' => $course->id,
            'name'   => 'Test call for abstracts',
        ]);

        $this->assertNotEmpty($confsubmissions->id);

        global $DB;
        $record = $DB->get_record('confsubmissions', ['id' => $confsubmissions->id]);
        $this->assertNotFalse($record);
        $this->assertSame('Test call for abstracts', $record->name);
    }

    /**
     * The privacy provider class exists and implements the expected interfaces.
     */
    public function test_privacy_provider_exists(): void {
        $this->resetAfterTest();

        $this->assertTrue(class_exists(\mod_confsubmissions\privacy\provider::class));
        $this->assertInstanceOf(
            \core_privacy\local\metadata\provider::class,
            new \mod_confsubmissions\privacy\provider()
        );
        $this->assertInstanceOf(
            \core_privacy\local\request\plugin\provider::class,
            new \mod_confsubmissions\privacy\provider()
        );
        $this->assertInstanceOf(
            \core_privacy\local\request\core_userlist_provider::class,
            new \mod_confsubmissions\privacy\provider()
        );
    }

    /**
     * confsubmissions_supports() answers sensibly for the core feature constants used.
     */
    public function test_supports_returns_expected_values(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/confsubmissions/lib.php');

        $this->assertTrue(confsubmissions_supports(FEATURE_MOD_INTRO));
        $this->assertTrue(confsubmissions_supports(FEATURE_BACKUP_MOODLE2));
        $this->assertNull(confsubmissions_supports('some_unknown_feature'));
    }

    /**
     * confsubmissions_reset_userdata() deletes every submission (and everything
     * attached to one) for instances in the given course, when reset_confsubmissions_submissions
     * is set, but leaves instance configuration (tracks) untouched.
     */
    public function test_reset_userdata_removes_submissions_but_keeps_tracks(): void {
        $this->resetAfterTest();
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/confsubmissions/lib.php');

        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);

        $trackid = \mod_confsubmissions\api::add_track((int) $confsubmissions->id, 'Security');

        $speaker = $this->getDataGenerator()->create_user();
        $now = time();
        $submissionid = (int) $DB->insert_record('confsubmissions_submission', (object) [
            'confsubmissions' => $confsubmissions->id,
            'userid'          => $speaker->id,
            'title'           => 'A Test Talk',
            'abstract'        => 'Abstract text',
            'trackid'         => $trackid,
            'status'          => 'submitted',
            'timecreated'     => $now,
            'timemodified'    => $now,
        ]);
        \mod_confsubmissions\api::sync_speakers($submissionid, [['userid' => $speaker->id]]);

        $status = confsubmissions_reset_userdata((object) [
            'courseid' => $course->id,
            'reset_confsubmissions_submissions' => 1,
            'timeshift' => 0,
        ]);

        $this->assertNotEmpty($status);
        $this->assertFalse($DB->record_exists('confsubmissions_submission', ['id' => $submissionid]));
        $this->assertFalse($DB->record_exists('confsubmissions_speaker', ['submissionid' => $submissionid]));
        $this->assertTrue($DB->record_exists('confsubmissions_track', ['id' => $trackid]));
    }

    /**
     * confsubmissions_delete_instance() empties every child table for the instance --
     * including confsubmissions_datepref, which it previously leaked (a submitter's
     * preferred-day rows survived activity deletion as unreachable orphans; found
     * by the 2026-07-09 review, FABLE.md confsubmissions H1).
     */
    public function test_delete_instance_removes_all_child_rows(): void {
        $this->resetAfterTest();
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/confsubmissions/lib.php');

        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);
        $instanceid = (int) $confsubmissions->id;

        $trackid = api::add_track($instanceid, 'Security');
        $fieldid = api::add_field($instanceid, 'Notes', 'text', null, false);
        api::add_submission_type($instanceid, 'Long talk', 45);

        $speaker = $this->getDataGenerator()->create_user();
        $now = time();
        $submissionid = (int) $DB->insert_record('confsubmissions_submission', (object) [
            'confsubmissions' => $instanceid,
            'userid'          => $speaker->id,
            'title'           => 'A Test Talk',
            'abstract'        => 'Abstract text',
            'trackid'         => $trackid,
            'status'          => 'submitted',
            'timecreated'     => $now,
            'timemodified'    => $now,
        ]);
        api::sync_speakers($submissionid, [['userid' => $speaker->id]]);
        api::sync_optional_fields($submissionid, [$fieldid => 'an answer']);
        api::sync_date_preferences($submissionid, [$now]);
        $DB->insert_record('confsubmissions_notiftemplate', (object) [
            'confsubmissions' => $instanceid,
            'notiftype'       => 'created',
            'subject'         => 'S',
            'body'            => 'B',
            'bodyformat'      => FORMAT_HTML,
        ]);

        $this->assertTrue(confsubmissions_delete_instance($instanceid));

        $this->assertFalse($DB->record_exists('confsubmissions', ['id' => $instanceid]));
        $this->assertFalse($DB->record_exists('confsubmissions_submission', ['confsubmissions' => $instanceid]));
        $this->assertFalse($DB->record_exists('confsubmissions_speaker', ['submissionid' => $submissionid]));
        $this->assertFalse($DB->record_exists('confsubmissions_fieldval', ['submissionid' => $submissionid]));
        $this->assertFalse($DB->record_exists('confsubmissions_datepref', ['submissionid' => $submissionid]));
        $this->assertFalse($DB->record_exists('confsubmissions_field', ['confsubmissions' => $instanceid]));
        $this->assertFalse($DB->record_exists('confsubmissions_track', ['confsubmissions' => $instanceid]));
        $this->assertFalse($DB->record_exists('confsubmissions_submissiontype', ['confsubmissions' => $instanceid]));
        $this->assertFalse($DB->record_exists('confsubmissions_notiftemplate', ['confsubmissions' => $instanceid]));
    }
}
