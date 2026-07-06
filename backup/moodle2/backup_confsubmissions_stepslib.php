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
 * Defines the backup structure for mod_confsubmissions.
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the complete confsubmissions structure for backup, with file and id annotations.
 *
 * Instance CONFIGURATION (tracks, submission types, optional fields, notification
 * templates) is always included, regardless of the 'userinfo' setting -- these are not
 * personal data, matching how mod_choice's choice_options are backed up unconditionally.
 * Submissions themselves (and everything hanging off one: speakers, optional-field
 * answers, date preferences) are user data and only included when 'userinfo' is on.
 *
 * Tracks/submission types/fields are backed up as SIBLINGS of, and before, submissions
 * in document order (see define_structure()'s add_child() order below) -- this matters
 * for restore, since confsubmissions_submission.trackid/submissiontypeid and
 * confsubmissions_fieldval.fieldid are old-id references that restore must remap via
 * set_mapping()/get_mappingid(), and that only works if the referenced track/type/field
 * was already restored (i.e. appears earlier in the XML) by the time the submission
 * that references it is processed.
 */
class backup_confsubmissions_activity_structure_step extends backup_activity_structure_step {
    /**
     * Defines the confsubmissions activity structure for backup.
     *
     * @return backup_nested_element The root element, wrapped into standard activity structure
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $confsubmissions = new backup_nested_element('confsubmissions', ['id'], [
            'name', 'intro', 'introformat', 'timeopen', 'timeclose',
            'titlelimit', 'titlelimittype', 'abstractlimit', 'abstractlimittype',
            'conferencestart', 'conferenceend', 'offerpreferreddates', 'disableddates',
            'notificationsenabled', 'timecreated', 'timemodified',
        ]);

        $tracks = new backup_nested_element('tracks');
        $track = new backup_nested_element('track', ['id'], [
            'name', 'sortorder', 'colour', 'icon',
        ]);

        $submissiontypes = new backup_nested_element('submissiontypes');
        $submissiontype = new backup_nested_element('submissiontype', ['id'], [
            'name', 'durationminutes', 'sortorder',
        ]);

        $fields = new backup_nested_element('fields');
        $field = new backup_nested_element('field', ['id'], [
            'name', 'type', 'options', 'required', 'sortorder',
        ]);

        $notiftemplates = new backup_nested_element('notiftemplates');
        $notiftemplate = new backup_nested_element('notiftemplate', ['id'], [
            'notiftype', 'subject', 'body', 'bodyformat', 'timecreated', 'timemodified',
        ]);

        $submissions = new backup_nested_element('submissions');
        $submission = new backup_nested_element('submission', ['id'], [
            'userid', 'title', 'abstract', 'trackid', 'submissiontypeid', 'status',
            'timecreated', 'timemodified',
        ]);

        $speakers = new backup_nested_element('speakers');
        $speaker = new backup_nested_element('speaker', ['id'], [
            'userid', 'name', 'email', 'role', 'sortorder',
        ]);

        $fieldvals = new backup_nested_element('fieldvals');
        $fieldval = new backup_nested_element('fieldval', ['id'], [
            'fieldid', 'value',
        ]);

        $dateprefs = new backup_nested_element('dateprefs');
        $datepref = new backup_nested_element('datepref', ['id'], [
            'prefdate',
        ]);

        // Build the tree. Config elements (tracks/submissiontypes/fields/notiftemplates)
        // come before submissions -- see this class's docblock for why the order matters.
        $confsubmissions->add_child($tracks);
        $tracks->add_child($track);

        $confsubmissions->add_child($submissiontypes);
        $submissiontypes->add_child($submissiontype);

        $confsubmissions->add_child($fields);
        $fields->add_child($field);

        $confsubmissions->add_child($notiftemplates);
        $notiftemplates->add_child($notiftemplate);

        $confsubmissions->add_child($submissions);
        $submissions->add_child($submission);

        $submission->add_child($speakers);
        $speakers->add_child($speaker);

        $submission->add_child($fieldvals);
        $fieldvals->add_child($fieldval);

        $submission->add_child($dateprefs);
        $dateprefs->add_child($datepref);

        // Define sources.
        $confsubmissions->set_source_table('confsubmissions', ['id' => backup::VAR_ACTIVITYID]);
        $track->set_source_table('confsubmissions_track', ['confsubmissions' => backup::VAR_PARENTID], 'sortorder ASC');
        $submissiontype->set_source_table(
            'confsubmissions_submissiontype',
            ['confsubmissions' => backup::VAR_PARENTID],
            'sortorder ASC'
        );
        $field->set_source_table('confsubmissions_field', ['confsubmissions' => backup::VAR_PARENTID], 'sortorder ASC');
        $notiftemplate->set_source_table('confsubmissions_notiftemplate', ['confsubmissions' => backup::VAR_PARENTID]);

        // The rest only happen if we are including user info.
        if ($userinfo) {
            $submission->set_source_table('confsubmissions_submission', ['confsubmissions' => backup::VAR_PARENTID]);
            $speaker->set_source_table('confsubmissions_speaker', ['submissionid' => backup::VAR_PARENTID], 'sortorder ASC');
            $fieldval->set_source_table('confsubmissions_fieldval', ['submissionid' => backup::VAR_PARENTID]);
            $datepref->set_source_table('confsubmissions_datepref', ['submissionid' => backup::VAR_PARENTID]);
        }

        // Define id annotations (global entities restore's generic machinery needs to
        // know about -- NOT needed for same-plugin foreign keys like trackid/
        // submissiontypeid/fieldid, which restore's own process_*() methods remap by
        // hand via set_mapping()/get_mappingid()).
        $submission->annotate_ids('user', 'userid');
        $speaker->annotate_ids('user', 'userid');

        // Define file annotations.
        $confsubmissions->annotate_files('mod_confsubmissions', 'intro', null);

        // Return the root element, wrapped into standard activity structure.
        return $this->prepare_activity_structure($confsubmissions);
    }
}
