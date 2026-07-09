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

namespace mod_confsubmissions\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * AJAX-only external function backing the speaker-picker autocomplete
 * (amd/src/speaker_selector.js) in the submission form.
 *
 * Scoped to users enrolled in the submission's course only, and requires
 * mod/confsubmissions:submit so only presenters (not arbitrary logged-in
 * users) can enumerate course participants through this endpoint.
 *
 * @package    mod_confsubmissions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_course_users extends external_api {
    /** @var int Maximum number of matches returned, to keep the AJAX response small. */
    const MAX_RESULTS = 20;

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'The confsubmissions course-module id'),
            'query' => new external_value(PARAM_RAW, 'Search string matched against enrolled users\' full names'),
        ]);
    }

    /**
     * Searches users enrolled in the course of the given confsubmissions instance.
     *
     * @param int $cmid The confsubmissions course-module id
     * @param string $query Search string matched against enrolled users' full names
     * @return array{id: int, fullname: string}[] Matching users, id + fullname only
     */
    public static function execute(int $cmid, string $query): array {
        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid, 'query' => $query]);

        $cm = get_coursemodule_from_id('confsubmissions', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        // An editingteacher who holds mod/confsubmissions:editany (but not :submit, which
        // is a student-only archetype) may edit any submission via edit.php, including its
        // speakers, so the speaker-picker autocomplete they rely on must accept either
        // capability -- otherwise the editany edit flow can display a submission's existing
        // speakers but never search for a new one.
        $cansearch = has_capability('mod/confsubmissions:submit', $context)
            || has_capability('mod/confsubmissions:editany', $context);
        if (!$cansearch) {
            // Neither held: raise the usual "you need :submit" exception, unchanged.
            require_capability('mod/confsubmissions:submit', $context);
        }

        global $DB;

        // The name match runs in SQL, selecting only id + name fields and capped at
        // MAX_RESULTS rows -- fetching every enrolled user's full record and
        // filtering in PHP made each autocomplete keystroke hydrate the whole
        // course enrolment (u.* includes the password hash) on large sites.
        [$enrolsql, $enrolparams] = get_enrolled_sql($context);
        $namefields = implode(', ', array_map(
            static fn(string $field): string => 'u.' . $field,
            array_merge(['id'], \core_user\fields::for_name()->get_required_fields())
        ));

        $query = trim($params['query']);
        $wheres = ['u.deleted = 0'];
        $sqlparams = $enrolparams;
        if ($query !== '') {
            $wheres[] = $DB->sql_like($DB->sql_fullname('u.firstname', 'u.lastname'), ':search', false);
            $sqlparams['search'] = '%' . $DB->sql_like_escape($query) . '%';
        }

        $sql = "SELECT $namefields
                  FROM {user} u
                  JOIN ($enrolsql) eu ON eu.id = u.id
                 WHERE " . implode(' AND ', $wheres) . '
              ORDER BY u.lastname, u.firstname';

        $viewfullnames = has_capability('moodle/site:viewfullnames', $context);

        $results = [];
        foreach ($DB->get_records_sql($sql, $sqlparams, 0, self::MAX_RESULTS) as $user) {
            $results[] = ['id' => (int) $user->id, 'fullname' => fullname($user, $viewfullnames)];
        }

        return $results;
    }

    /**
     * Returns description of method result value.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'id' => new external_value(PARAM_INT, 'User id'),
            'fullname' => new external_value(PARAM_TEXT, 'User full name'),
        ]));
    }
}
