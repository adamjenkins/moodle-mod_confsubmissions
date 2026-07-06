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

namespace mod_confsubmissions\backup;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/phpunit/classes/restore_date_testcase.php');
require_once($CFG->dirroot . '/mod/confsubmissions/backup/moodle2/restore_confsubmissions_stepslib.php');

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Backup/restore tests for mod_confsubmissions (user request, 2026-07-06: "Also make
 * sure backup/restore/reset all works fine with all plugins").
 *
 * Exercises a real backup_controller/restore_controller cycle (not just a unit test of
 * the stepslib classes in isolation) -- the same pattern core's own
 * restore_date_testcase-based tests use, e.g. mod_choice/tests/backup/restore_date_test.php.
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\restore_confsubmissions_activity_structure_step::class)]
final class restore_confsubmissions_test extends \restore_date_testcase {
    /**
     * A full backup/restore round-trip correctly reconstructs an instance's
     * configuration (tracks, submission types, optional fields, notification
     * templates) AND a submission's data (speakers, optional-field answers, date
     * preferences), with every same-plugin foreign key (trackid, submissiontypeid,
     * fieldid) correctly remapped to the restored copy's own new ids -- not left
     * pointing at the old, no-longer-relevant ids.
     */
    public function test_backup_and_restore_reconstructs_everything(): void {
        global $DB;

        [$course, $confsubmissions] = $this->create_course_and_module('confsubmissions', [
            'conferencestart' => $this->startdate,
            'conferenceend' => $this->startdate + (3 * DAYSECS),
            'offerpreferreddates' => 1,
        ]);

        $trackid = \mod_confsubmissions\api::add_track((int) $confsubmissions->id, 'Security', '#3366cc', 'shield_halved');
        $typeid = \mod_confsubmissions\api::add_submission_type((int) $confsubmissions->id, 'Lightning Talk', 15);
        $fieldid = \mod_confsubmissions\api::add_field((int) $confsubmissions->id, 'Equipment needed', 'text', null, false);

        $speakeruser = $this->getDataGenerator()->create_user();

        $now = time();
        $submissionid = (int) $DB->insert_record('confsubmissions_submission', (object) [
            'confsubmissions'  => $confsubmissions->id,
            'userid'           => $speakeruser->id,
            'title'            => 'A Test Talk',
            'abstract'         => 'Abstract text',
            'trackid'          => $trackid,
            'submissiontypeid' => $typeid,
            'status'           => 'submitted',
            'timecreated'      => $now,
            'timemodified'     => $now,
        ]);

        \mod_confsubmissions\api::sync_speakers($submissionid, [
            ['userid' => $speakeruser->id],
            ['name' => 'External Guest', 'email' => 'guest@example.com'],
        ]);
        \mod_confsubmissions\api::sync_optional_fields($submissionid, [$fieldid => 'A projector']);
        \mod_confsubmissions\api::sync_date_preferences($submissionid, [$this->startdate]);

        $newcourseid = $this->backup_and_restore($course);

        $newconfsubmissions = $DB->get_record('confsubmissions', ['course' => $newcourseid], '*', MUST_EXIST);

        $newtracks = $DB->get_records('confsubmissions_track', ['confsubmissions' => $newconfsubmissions->id]);
        $this->assertCount(1, $newtracks);
        $newtrack = reset($newtracks);
        $this->assertSame('Security', $newtrack->name);

        $newtypes = $DB->get_records('confsubmissions_submissiontype', ['confsubmissions' => $newconfsubmissions->id]);
        $this->assertCount(1, $newtypes);
        $newtype = reset($newtypes);
        $this->assertSame('Lightning Talk', $newtype->name);

        $newfields = $DB->get_records('confsubmissions_field', ['confsubmissions' => $newconfsubmissions->id]);
        $this->assertCount(1, $newfields);
        $newfield = reset($newfields);
        $this->assertSame('Equipment needed', $newfield->name);

        $newsubmissions = $DB->get_records('confsubmissions_submission', ['confsubmissions' => $newconfsubmissions->id]);
        $this->assertCount(1, $newsubmissions);
        $newsubmission = reset($newsubmissions);
        $this->assertSame('A Test Talk', $newsubmission->title);

        // The critical checks: same-plugin foreign keys point at the RESTORED copy's
        // own new ids, not the original (now-irrelevant-to-this-course) old ids.
        $this->assertSame((int) $newtrack->id, (int) $newsubmission->trackid);
        $this->assertSame((int) $newtype->id, (int) $newsubmission->submissiontypeid);

        $newspeakers = $DB->get_records('confsubmissions_speaker', ['submissionid' => $newsubmission->id], 'sortorder ASC');
        $this->assertCount(2, $newspeakers);
        $newspeakers = array_values($newspeakers);
        $this->assertSame((int) $speakeruser->id, (int) $newspeakers[0]->userid);
        $this->assertSame('External Guest', $newspeakers[1]->name);
        $this->assertSame('guest@example.com', $newspeakers[1]->email);

        $newfieldvals = $DB->get_records('confsubmissions_fieldval', ['submissionid' => $newsubmission->id]);
        $this->assertCount(1, $newfieldvals);
        $newfieldval = reset($newfieldvals);
        $this->assertSame((int) $newfield->id, (int) $newfieldval->fieldid);
        $this->assertSame('A projector', $newfieldval->value);

        $newdateprefs = $DB->get_records('confsubmissions_datepref', ['submissionid' => $newsubmission->id]);
        $this->assertCount(1, $newdateprefs);
    }

    /**
     * Instance configuration (tracks, submission types, optional fields) is included
     * in the backup even when 'userinfo' is off -- only submissions (and everything
     * attached to one) are gated on that setting.
     */
    public function test_config_is_backed_up_without_userinfo(): void {
        global $DB, $USER, $CFG;

        [$course, $confsubmissions] = $this->create_course_and_module('confsubmissions');
        \mod_confsubmissions\api::add_track((int) $confsubmissions->id, 'Security');

        $speakeruser = $this->getDataGenerator()->create_user();
        $now = time();
        $DB->insert_record('confsubmissions_submission', (object) [
            'confsubmissions' => $confsubmissions->id,
            'userid'          => $speakeruser->id,
            'title'           => 'A Test Talk',
            'abstract'        => 'Abstract text',
            'status'          => 'submitted',
            'timecreated'     => $now,
            'timemodified'    => $now,
        ]);

        $CFG->backup_file_logger_level = \backup::LOG_NONE;
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        // Turn off user info for this backup -- submissions should NOT come across.
        $bc->get_plan()->get_setting('users')->set_value(false);
        $bc->execute_plan();
        $results = $bc->get_results();
        $file = $results['backup_destination'];
        $fp = get_file_packer('application/vnd.moodle.backup');
        $filepath = $CFG->dataroot . '/temp/backup/test-restore-course-nouserinfo';
        $file->extract_to_pathname($fp, $filepath);
        $bc->destroy();

        $newcourseid = \restore_dbops::create_new_course(
            $course->fullname,
            $course->shortname . '_nouserinfo',
            $course->category
        );
        $rc = new \restore_controller(
            'test-restore-course-nouserinfo',
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        $newconfsubmissions = $DB->get_record('confsubmissions', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertTrue($DB->record_exists('confsubmissions_track', ['confsubmissions' => $newconfsubmissions->id]));
        $this->assertFalse($DB->record_exists('confsubmissions_submission', ['confsubmissions' => $newconfsubmissions->id]));
    }
}
