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

    return true;
}
