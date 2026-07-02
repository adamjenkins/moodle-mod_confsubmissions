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

import Ajax from 'core/ajax';

/**
 * Transport/processResults pair used by the "autocomplete" form element that
 * picks an existing enrolled user for a submission's speaker rows (see
 * \mod_confsubmissions\form\submission_form). Backed by the
 * mod_confsubmissions_search_course_users external function.
 *
 * @module     mod_confsubmissions/speaker_selector
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Loads the list of enrolled users matching the query.
 *
 * @param {String} selector The selector of the autocomplete element
 * @param {String} query The query string
 * @param {Function} callback A callback function receiving an array of results
 * @param {Function} failure A function to call in case of failure, receiving the error
 */
export const transport = (selector, query, callback, failure) => {
    const field = document.querySelector(selector);
    const cmid = field ? field.getAttribute('data-cmid') : 0;

    Ajax.call([{
        methodname: 'mod_confsubmissions_search_course_users',
        args: {
            cmid: cmid,
            query: query,
        },
    }])[0].then(callback).catch(failure);
};

/**
 * Processes the raw external function results into autocomplete options.
 *
 * @param {String} selector The selector of the autocomplete element
 * @param {Array} results An array of results returned by transport()
 * @return {Array} Array of {value, label} options
 */
export const processResults = (selector, results) => {
    if (!Array.isArray(results)) {
        return results;
    }

    return results.map(user => ({value: user.id, label: user.fullname}));
};
