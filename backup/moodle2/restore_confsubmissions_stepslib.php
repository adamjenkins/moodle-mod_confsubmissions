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

/**
 * Defines the restore structure for mod_confsubmissions.
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Structure step to restore one confsubmissions activity.
 *
 * process_submission() defines the 'confsubmissions_submission' id mapping that
 * mod_confprogram and mod_confscheduler both depend on to remap their own tables'
 * submissionid references -- since those sibling plugins resolve it via
 * get_mappingid('confsubmissions_submission', ...) from their own after_restore()
 * (restore processing order across activities in the same course is not guaranteed
 * until every activity's main structure step has completed), this mapping must be set
 * here unconditionally, even though it lives entirely within this plugin's own restore.
 */
class restore_confsubmissions_activity_structure_step extends restore_activity_structure_step {
    /**
     * Defines the confsubmissions activity structure for restore.
     *
     * @return array The restore_path_element[] paths, wrapped into standard activity structure
     */
    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('confsubmissions', '/activity/confsubmissions');
        $paths[] = new restore_path_element('confsubmissions_track', '/activity/confsubmissions/tracks/track');
        $paths[] = new restore_path_element(
            'confsubmissions_submissiontype',
            '/activity/confsubmissions/submissiontypes/submissiontype'
        );
        $paths[] = new restore_path_element('confsubmissions_field', '/activity/confsubmissions/fields/field');
        $paths[] = new restore_path_element(
            'confsubmissions_notiftemplate',
            '/activity/confsubmissions/notiftemplates/notiftemplate'
        );

        if ($userinfo) {
            $paths[] = new restore_path_element('confsubmissions_submission', '/activity/confsubmissions/submissions/submission');
            $paths[] = new restore_path_element(
                'confsubmissions_speaker',
                '/activity/confsubmissions/submissions/submission/speakers/speaker'
            );
            $paths[] = new restore_path_element(
                'confsubmissions_fieldval',
                '/activity/confsubmissions/submissions/submission/fieldvals/fieldval'
            );
            $paths[] = new restore_path_element(
                'confsubmissions_datepref',
                '/activity/confsubmissions/submissions/submission/dateprefs/datepref'
            );
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restores the main confsubmissions instance record.
     *
     * @param array|stdClass $data The parsed confsubmissions element
     * @return void
     */
    protected function process_confsubmissions($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();

        $data->timeopen = $this->apply_date_offset($data->timeopen);
        $data->timeclose = $this->apply_date_offset($data->timeclose);
        $data->conferencestart = $this->apply_date_offset($data->conferencestart);
        $data->conferenceend = $this->apply_date_offset($data->conferenceend);
        // Disableddates (a comma-separated list of timestamps embedded in a text field,
        // not a single column) is deliberately NOT date-offset here -- unlike the plain
        // timestamp columns above, shifting embedded values inside a text blob is not a
        // pattern any core activity does either; a restored instance keeps its original
        // disabled-day timestamps, which may now fall outside its (offset) conference
        // date range and simply have no effect until an organiser revisits the setting.

        $newitemid = $DB->insert_record('confsubmissions', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restores a track, and records its old-to-new id mapping for
     * process_confsubmissions_submission() to resolve trackid against.
     *
     * @param array|stdClass $data The parsed track element
     * @return void
     */
    protected function process_confsubmissions_track($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->confsubmissions = $this->get_new_parentid('confsubmissions');

        $newitemid = $DB->insert_record('confsubmissions_track', $data);
        $this->set_mapping('confsubmissions_track', $oldid, $newitemid);
    }

    /**
     * Restores a submission type, and records its old-to-new id mapping for
     * process_confsubmissions_submission() to resolve submissiontypeid against.
     *
     * @param array|stdClass $data The parsed submissiontype element
     * @return void
     */
    protected function process_confsubmissions_submissiontype($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->confsubmissions = $this->get_new_parentid('confsubmissions');

        $newitemid = $DB->insert_record('confsubmissions_submissiontype', $data);
        $this->set_mapping('confsubmissions_submissiontype', $oldid, $newitemid);
    }

    /**
     * Restores an optional field, and records its old-to-new id mapping for
     * process_confsubmissions_fieldval() to resolve fieldid against.
     *
     * @param array|stdClass $data The parsed field element
     * @return void
     */
    protected function process_confsubmissions_field($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->confsubmissions = $this->get_new_parentid('confsubmissions');

        $newitemid = $DB->insert_record('confsubmissions_field', $data);
        $this->set_mapping('confsubmissions_field', $oldid, $newitemid);
    }

    /**
     * Restores a notification template.
     *
     * @param array|stdClass $data The parsed notiftemplate element
     * @return void
     */
    protected function process_confsubmissions_notiftemplate($data) {
        global $DB;

        $data = (object) $data;
        $data->confsubmissions = $this->get_new_parentid('confsubmissions');

        $DB->insert_record('confsubmissions_notiftemplate', $data);
    }

    /**
     * Restores a submission, and records its old-to-new id mapping -- depended on by
     * mod_confprogram/mod_confscheduler's own restore steps, see this class's docblock.
     *
     * @param array|stdClass $data The parsed submission element
     * @return void
     */
    protected function process_confsubmissions_submission($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->confsubmissions = $this->get_new_parentid('confsubmissions');
        $data->userid = $this->get_mappingid('user', $data->userid);

        // Null (rather than 0/false) when the referenced track/type wasn't included in
        // this backup/restore -- matches the columns' own NOTNULL="false" schema, and
        // avoids silently pointing at an unrelated track/type that happens to share the
        // old numeric id in the destination site.
        $data->trackid = !empty($data->trackid) ? ($this->get_mappingid('confsubmissions_track', $data->trackid) ?: null) : null;
        $data->submissiontypeid = !empty($data->submissiontypeid)
            ? ($this->get_mappingid('confsubmissions_submissiontype', $data->submissiontypeid) ?: null)
            : null;

        $newitemid = $DB->insert_record('confsubmissions_submission', $data);
        $this->set_mapping('confsubmissions_submission', $oldid, $newitemid);
    }

    /**
     * Restores a speaker attached to a submission.
     *
     * @param array|stdClass $data The parsed speaker element
     * @return void
     */
    protected function process_confsubmissions_speaker($data) {
        global $DB;

        $data = (object) $data;
        $data->submissionid = $this->get_new_parentid('confsubmissions_submission');
        if (!empty($data->userid)) {
            $data->userid = $this->get_mappingid('user', $data->userid) ?: null;
        }

        $DB->insert_record('confsubmissions_speaker', $data);
    }

    /**
     * Restores a submitter's answer to an optional field.
     *
     * @param array|stdClass $data The parsed fieldval element
     * @return void
     */
    protected function process_confsubmissions_fieldval($data) {
        global $DB;

        $data = (object) $data;
        $data->submissionid = $this->get_new_parentid('confsubmissions_submission');
        $newfieldid = $this->get_mappingid('confsubmissions_field', $data->fieldid);
        if (!$newfieldid) {
            // The field this answer belongs to wasn't included in this backup/restore
            // (or no longer exists) -- nothing to attach the answer to.
            return;
        }
        $data->fieldid = $newfieldid;

        $DB->insert_record('confsubmissions_fieldval', $data);
    }

    /**
     * Restores a submitter's preferred conference day for a submission.
     *
     * @param array|stdClass $data The parsed datepref element
     * @return void
     */
    protected function process_confsubmissions_datepref($data) {
        global $DB;

        $data = (object) $data;
        $data->submissionid = $this->get_new_parentid('confsubmissions_submission');
        $data->prefdate = $this->apply_date_offset($data->prefdate);

        $DB->insert_record('confsubmissions_datepref', $data);
    }

    /**
     * Restores files attached to the confsubmissions intro.
     *
     * @return void
     */
    protected function after_execute() {
        // Add confsubmissions related files, no need to match by itemname (just
        // internally handled context).
        $this->add_related_files('mod_confsubmissions', 'intro', null);
    }
}
