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
 * Upgrade steps for mod_confsubmissions.
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Runs upgrade steps between versions.
 *
 * @param int $oldversion Plugin version being upgraded from
 * @return bool
 */
function xmldb_confsubmissions_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026070201) {
        // Add a UNIQUE key on confsubmissions_field(confsubmissions, fieldname) so a single
        // optional field cannot be configured twice with contradictory enabled values.
        $table = new xmldb_table('confsubmissions_field');
        $key = new xmldb_key('confsubmissions-fieldname', XMLDB_KEY_UNIQUE, ['confsubmissions', 'fieldname']);
        if (!$dbman->find_key_name($table, $key)) {
            $dbman->add_key($table, $key);
        }

        // Add a UNIQUE key on confsubmissions_fieldval(submissionid, fieldname) so a
        // submitter cannot end up with two silently-colliding answers to the same field.
        $table = new xmldb_table('confsubmissions_fieldval');
        $key = new xmldb_key('submissionid-fieldname', XMLDB_KEY_UNIQUE, ['submissionid', 'fieldname']);
        if (!$dbman->find_key_name($table, $key)) {
            $dbman->add_key($table, $key);
        }

        upgrade_mod_savepoint(true, 2026070201, 'confsubmissions');
    }

    if ($oldversion < 2026070202) {
        // No schema change here: this savepoint exists purely to make sure the upgrade
        // pipeline runs, which is what registers the new mod_confsubmissions_search_course_users
        // external function declared in db/services.php (added alongside the submission form's
        // speaker-picker autocomplete in this release).
        upgrade_mod_savepoint(true, 2026070202, 'confsubmissions');
    }

    if ($oldversion < 2026070204) {
        // Add organiser-configurable colour/icon theming to tracks: colour is a nullable
        // hex string (same convention as mod_confscheduler's confscheduler_room.colour),
        // icon is a nullable machine key from a fixed, curated allow-list (never free text
        // or an uploaded asset - see confsubmissions_track_icon_options() in lib.php and
        // the validation in classes/api.php).
        $table = new xmldb_table('confsubmissions_track');

        $field = new xmldb_field('colour', XMLDB_TYPE_CHAR, '7', null, null, null, null, 'sortorder');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('icon', XMLDB_TYPE_CHAR, '30', null, null, null, null, 'colour');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026070204, 'confsubmissions');
    }

    if ($oldversion < 2026070205) {
        // Add organiser-configurable submission types (e.g. Lightning Talk, Workshop),
        // each with a default presentation duration in minutes -- consumed by
        // mod_confscheduler as the initial block length when a presentation is first
        // scheduled (Revision round 1, 2026-07-04).
        if (!$dbman->table_exists('confsubmissions_submissiontype')) {
            $table = new xmldb_table('confsubmissions_submissiontype');
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('confsubmissions', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('durationminutes', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '30');
            $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('confsubmissions', XMLDB_KEY_FOREIGN, ['confsubmissions'], 'confsubmissions', ['id']);
            $dbman->create_table($table);
        }

        $table = new xmldb_table('confsubmissions_submission');
        $field = new xmldb_field('submissiontypeid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'trackid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $key = new xmldb_key('submissiontypeid', XMLDB_KEY_FOREIGN, ['submissiontypeid'], 'confsubmissions_submissiontype', ['id']);
        if (!$dbman->find_key_name($table, $key)) {
            $dbman->add_key($table, $key);
        }

        upgrade_mod_savepoint(true, 2026070205, 'confsubmissions');
    }

    if ($oldversion < 2026070206) {
        // Replace the fixed, closed set of three optional-field checkboxes (language,
        // teaching context, sub-topic) with fully dynamic, organiser-named fields, each
        // with a chosen type (Revision round 1 follow-up, 2026-07-04): the organiser
        // should be able to name each field and specify its own field type.
        $fieldtable = new xmldb_table('confsubmissions_field');

        $namefield = new xmldb_field('name', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'fieldname');
        if (!$dbman->field_exists($fieldtable, $namefield)) {
            $dbman->add_field($fieldtable, $namefield);
        }
        $typefield = new xmldb_field('type', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'text', 'name');
        if (!$dbman->field_exists($fieldtable, $typefield)) {
            $dbman->add_field($fieldtable, $typefield);
        }
        $optionsfield = new xmldb_field('options', XMLDB_TYPE_TEXT, null, null, null, null, null, 'type');
        if (!$dbman->field_exists($fieldtable, $optionsfield)) {
            $dbman->add_field($fieldtable, $optionsfield);
        }
        $requiredfield = new xmldb_field('required', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'options');
        if (!$dbman->field_exists($fieldtable, $requiredfield)) {
            $dbman->add_field($fieldtable, $requiredfield);
        }

        // Backfill `name`/`type` from the old fixed fieldname for any row that predates
        // this upgrade. A row that was never enabled is deleted outright rather than
        // migrated forward: it was never shown to a presenter and carries no data.
        if ($dbman->field_exists($fieldtable, new xmldb_field('fieldname'))) {
            $legacylabels = [
                'language'        => 'Presentation language',
                'teachingcontext' => 'Teaching context',
                'subtopic'        => 'Sub-topic area',
            ];
            $rows = $DB->get_records('confsubmissions_field');
            foreach ($rows as $row) {
                if (empty($row->enabled)) {
                    $DB->delete_records('confsubmissions_field', ['id' => $row->id]);
                    continue;
                }
                $DB->update_record('confsubmissions_field', (object) [
                    'id'   => $row->id,
                    'name' => $legacylabels[$row->fieldname] ?? $row->fieldname,
                    'type' => 'text',
                ]);
            }
        }

        // Migrate confsubmissions_fieldval from fieldname-keyed to fieldid-keyed, since a
        // field's machine identity is now its row id, not a fixed vocabulary string.
        $valtable = new xmldb_table('confsubmissions_fieldval');
        $fieldidfield = new xmldb_field('fieldid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'submissionid');
        if (!$dbman->field_exists($valtable, $fieldidfield)) {
            $dbman->add_field($valtable, $fieldidfield);
        }

        if ($dbman->field_exists($valtable, new xmldb_field('fieldname'))) {
            // Portable per-row loop rather than a single UPDATE ... JOIN: that syntax
            // is MySQL/MariaDB-only (PostgreSQL needs UPDATE ... FROM), and this step
            // must keep working on every DB family composer.json/ci.yml claim support
            // for -- released versions exist behind this savepoint.
            $rs = $DB->get_recordset_sql(
                'SELECT fv.id AS fieldvalid, f.id AS fieldid
                   FROM {confsubmissions_fieldval} fv
                   JOIN {confsubmissions_submission} sub ON sub.id = fv.submissionid
                   JOIN {confsubmissions_field} f
                        ON f.confsubmissions = sub.confsubmissions AND f.fieldname = fv.fieldname'
            );
            foreach ($rs as $row) {
                $DB->set_field('confsubmissions_fieldval', 'fieldid', $row->fieldid, ['id' => $row->fieldvalid]);
            }
            $rs->close();

            // A value whose field was disabled (and so deleted, above) is orphaned and
            // meaningless without a field to attach to.
            $DB->delete_records_select('confsubmissions_fieldval', 'fieldid IS NULL');

            $oldvalkey = new xmldb_key('submissionid-fieldname', XMLDB_KEY_UNIQUE, ['submissionid', 'fieldname']);
            if ($dbman->find_key_name($valtable, $oldvalkey)) {
                $dbman->drop_key($valtable, $oldvalkey);
            }
            $dbman->drop_field($valtable, new xmldb_field('fieldname'));
        }

        $newvalkey = new xmldb_key('submissionid-fieldid', XMLDB_KEY_UNIQUE, ['submissionid', 'fieldid']);
        if (!$dbman->find_key_name($valtable, $newvalkey)) {
            $dbman->add_key($valtable, $newvalkey);
        }

        if ($dbman->field_exists($fieldtable, new xmldb_field('fieldname'))) {
            $oldfieldkey = new xmldb_key('confsubmissions-fieldname', XMLDB_KEY_UNIQUE, ['confsubmissions', 'fieldname']);
            if ($dbman->find_key_name($fieldtable, $oldfieldkey)) {
                $dbman->drop_key($fieldtable, $oldfieldkey);
            }
            $dbman->drop_field($fieldtable, new xmldb_field('fieldname'));
        }
        if ($dbman->field_exists($fieldtable, new xmldb_field('enabled'))) {
            $dbman->drop_field($fieldtable, new xmldb_field('enabled'));
        }

        upgrade_mod_savepoint(true, 2026070206, 'confsubmissions');
    }

    if ($oldversion < 2026070504) {
        // Add organiser-configurable (not required) conference dates and an
        // "offer preferred dates" toggle: when enabled, a submitter sees one checkbox
        // per day in the conferencestart/conferenceend range, all checked by default,
        // and mod_confscheduler's autoscheduler tries to honour whichever days a
        // submitter left checked (user feedback, 2026-07-05).
        $table = new xmldb_table('confsubmissions');

        $field = new xmldb_field('conferencestart', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'abstractlimittype');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('conferenceend', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'conferencestart');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('offerpreferreddates', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'conferenceend');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        if (!$dbman->table_exists('confsubmissions_datepref')) {
            $table = new xmldb_table('confsubmissions_datepref');
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('submissionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('prefdate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('submissionid', XMLDB_KEY_FOREIGN, ['submissionid'], 'confsubmissions_submission', ['id']);
            $table->add_key('submissionid-prefdate', XMLDB_KEY_UNIQUE, ['submissionid', 'prefdate']);
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026070504, 'confsubmissions');
    }

    if ($oldversion < 2026070505) {
        // Add an org-wide "disable specific preferred days" setting (user feedback,
        // 2026-07-05): a regular submitter never sees a disabled day as a preferred-date
        // checkbox, but a user with mod/confsubmissions:manageform (editingteacher+)
        // still sees and can select every day, disabled or not -- see dates.php.
        $table = new xmldb_table('confsubmissions');
        $field = new xmldb_field('disableddates', XMLDB_TYPE_TEXT, null, null, null, null, null, 'offerpreferreddates');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026070505, 'confsubmissions');
    }

    if ($oldversion < 2026070506) {
        // No schema change here (same convention as the 2026070202 step above): this
        // savepoint exists only to catch version.php up to the fix in commit c25a390
        // (the disabled-preferred-days save-persistence bug and submission-form
        // grey-out rework), which bumped $plugin->version without a matching upgrade
        // step at the time -- caught later while adding the notifications feature.
        upgrade_mod_savepoint(true, 2026070506, 'confsubmissions');
    }

    if ($oldversion < 2026070507) {
        // Notifications (user request, 2026-07-05): a submission being made or
        // withdrawn sends a notification via Moodle's own message system, with an
        // organiser-editable template per notification type.
        if (!$dbman->table_exists('confsubmissions_notiftemplate')) {
            $table = new xmldb_table('confsubmissions_notiftemplate');
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('confsubmissions', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('notiftype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('subject', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('body', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('bodyformat', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('confsubmissions', XMLDB_KEY_FOREIGN, ['confsubmissions'], 'confsubmissions', ['id']);
            $table->add_index('confsubmissionstype', XMLDB_INDEX_UNIQUE, ['confsubmissions', 'notiftype']);
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026070507, 'confsubmissions');
    }

    if ($oldversion < 2026070508) {
        // No schema change here: notifier::send()'s message_send() call is now
        // wrapped in a try/catch (a failed notification send must never break the
        // real submit/withdraw action that triggered it) -- see changelog.md.
        upgrade_mod_savepoint(true, 2026070508, 'confsubmissions');
    }

    if ($oldversion < 2026070601) {
        // Notifications master switch (user request, 2026-07-06): a single
        // instance-level on/off toggle that overrides every per-type template.
        // Defaults to 1 (enabled) so existing instances keep sending exactly as
        // they do today until an organiser explicitly turns it off.
        $table = new xmldb_table('confsubmissions');
        $field = new xmldb_field('notificationsenabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'disableddates');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026070601, 'confsubmissions');
    }

    if ($oldversion < 2026070800) {
        // Per-date reasons for org-wide disabled preferred days (user request,
        // 2026-07-09): disableddates' stored format changes from a plain
        // comma-separated timestamp list to a JSON array of {date, reason}
        // objects -- see classes/api.php's get_disabled_dates()/
        // get_disabled_date_reasons() docblocks. Migrate every existing row's
        // old-format value to the new shape (empty reason) here, once, so
        // application code can assume the new format unconditionally afterwards
        // rather than permanently supporting two stored shapes.
        $rs = $DB->get_recordset_select('confsubmissions', 'disableddates IS NOT NULL', []);
        foreach ($rs as $instance) {
            $trimmed = trim((string) $instance->disableddates);
            if ($trimmed === '' || $trimmed[0] === '[') {
                // Already empty, or already JSON (e.g. this step re-running after a
                // partial upgrade) -- leave it alone rather than double-encoding.
                continue;
            }

            $dates = array_map('intval', array_filter(explode(',', $trimmed), 'strlen'));
            $entries = array_map(fn($date) => ['date' => $date, 'reason' => ''], $dates);
            $DB->set_field('confsubmissions', 'disableddates', json_encode($entries), ['id' => $instance->id]);
        }
        $rs->close();

        upgrade_mod_savepoint(true, 2026070800, 'confsubmissions');
    }

    if ($oldversion < 2026070901) {
        // Notifications master switch default flipped 1 -> 0 (user request,
        // 2026-07-09): new instances now default to notifications OFF. Existing
        // instances' stored notificationsenabled values are deliberately left
        // untouched -- only the column's default (used by the next INSERT with
        // no explicit value) changes.
        $table = new xmldb_table('confsubmissions');
        $field = new xmldb_field(
            'notificationsenabled',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'disableddates'
        );
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_default($table, $field);
        }

        upgrade_mod_savepoint(true, 2026070901, 'confsubmissions');
    }

    if ($oldversion < 2026070902) {
        // Independent word + character limits per field (user request,
        // 2026-07-09): titlelimit/titlelimittype and abstractlimit/
        // abstractlimittype (a single limit, either chars OR words) are
        // replaced with two independent columns per field, so both a word
        // count (e.g. for English) and a character count (e.g. for Zenkaku
        // Japanese) can apply to the same field at once -- see
        // classes/local/limits.php (count()/exceeds() were already generic
        // per type, so no change needed there) and
        // classes/form/submission_form.php's validation().
        $table = new xmldb_table('confsubmissions');

        $field = new xmldb_field('titlemaxwords', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timeclose');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field(
            'titlemaxchars',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'titlemaxwords'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field(
            'abstractmaxwords',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'titlemaxchars'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field(
            'abstractmaxchars',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'abstractmaxwords'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // One-time backfill: keep each existing single limit under its matching
        // new column (chars -> maxchars, words -> maxwords), leaving the other
        // side at its default 0 (unlimited) -- no behaviour change for existing
        // instances until an organiser explicitly configures the second cap.
        if ($dbman->field_exists($table, new xmldb_field('titlelimit'))) {
            $DB->execute("UPDATE {confsubmissions} SET titlemaxwords = titlelimit WHERE titlelimittype = 'words'");
            $DB->execute("UPDATE {confsubmissions} SET titlemaxchars = titlelimit WHERE titlelimittype = 'chars'");
            $DB->execute("UPDATE {confsubmissions} SET abstractmaxwords = abstractlimit WHERE abstractlimittype = 'words'");
            $DB->execute("UPDATE {confsubmissions} SET abstractmaxchars = abstractlimit WHERE abstractlimittype = 'chars'");

            $dbman->drop_field($table, new xmldb_field('titlelimit'));
            $dbman->drop_field($table, new xmldb_field('titlelimittype'));
            $dbman->drop_field($table, new xmldb_field('abstractlimit'));
            $dbman->drop_field($table, new xmldb_field('abstractlimittype'));
        }

        upgrade_mod_savepoint(true, 2026070902, 'confsubmissions');
    }

    return true;
}
