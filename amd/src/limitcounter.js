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

import {getString} from 'core/str';

/**
 * Live character/word counter for the submission form's title and abstract
 * fields.
 *
 * Counting logic is UX-only here and must stay in sync with the authoritative
 * server-side check in \mod_confsubmissions\local\limits (chars: the raw
 * string length; words: the string trimmed then split on whitespace, empty
 * tokens discarded).
 *
 * 2026-07-09: a field may now have a word limit, a character limit, both, or
 * neither, independently (0 means unlimited for that one) -- the counter
 * shows both counts at once and highlights whichever one is exceeded, rather
 * than assuming exactly one "mode" per field.
 *
 * @module     mod_confsubmissions/limitcounter
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Counts the length of a value, either in characters or words.
 *
 * @param {String} value The text to measure
 * @param {String} limitType Either "chars" or "words"
 * @return {Number} The character or word count
 */
const count = (value, limitType) => {
    if (limitType === 'words') {
        return value.trim().split(/\s+/).filter(Boolean).length;
    }

    // Count Unicode code points, not UTF-16 code units, to match PHP's
    // core_text::strlen() (used server-side) for characters outside the
    // basic multilingual plane (e.g. many emoji).
    return Array.from(value).length;
};

/**
 * Creates (or returns the existing) counter <span> immediately after a field.
 *
 * @param {HTMLElement} field The input/textarea element being counted
 * @param {String} fieldId The field's id, used to derive the counter's id
 * @return {HTMLElement} The counter span
 */
const getOrCreateCounterElement = (field, fieldId) => {
    const counterId = `${fieldId}_limitcounter`;
    let counter = document.getElementById(counterId);

    if (!counter) {
        counter = document.createElement('span');
        counter.id = counterId;
        counter.classList.add('form-text', 'small', 'mod_confsubmissions-limitcounter');
        field.insertAdjacentElement('afterend', counter);
    }

    return counter;
};

/**
 * Builds one metric's "N / limit unit" segment, with its own independent
 * exceeded/ok styling.
 *
 * @param {String} value The field's current text
 * @param {Number} maxvalue The configured limit for this metric; 0 means unlimited
 * @param {String} limitType Either "chars" or "words"
 * @param {String} unitLabel The already-loaded unit label (e.g. "words")
 * @return {{text: String, exceeded: Boolean}}
 */
const renderMetric = (value, maxvalue, limitType, unitLabel) => {
    const current = count(value, limitType);
    return {
        text: `${current} / ${maxvalue} ${unitLabel}`,
        exceeded: current > maxvalue,
    };
};

/**
 * Sets up a live character/word counter on a form field, showing whichever of
 * the two independent limits (maxwords, maxchars) are actually configured for
 * it (0 means unlimited, i.e. not shown).
 *
 * Skipped entirely (not called) by the form when BOTH configured limits for
 * that field are 0 (unlimited).
 *
 * @param {String} fieldId The id of the input/textarea element to watch
 * @param {Number} maxwords The configured word limit; 0 means unlimited/not shown
 * @param {Number} maxchars The configured character limit; 0 means unlimited/not shown
 */
export const init = (fieldId, maxwords, maxchars) => {
    const field = document.getElementById(fieldId);
    if (!field) {
        return;
    }

    const counter = getOrCreateCounterElement(field, fieldId);

    Promise.all([
        getString('limittype_words', 'mod_confsubmissions'),
        getString('limittype_chars', 'mod_confsubmissions'),
    ]).then(([wordsLabel, charsLabel]) => {
        const render = () => {
            const segments = [];
            let exceeded = false;

            if (maxwords > 0) {
                const metric = renderMetric(field.value, maxwords, 'words', wordsLabel);
                segments.push(metric.text);
                exceeded = exceeded || metric.exceeded;
            }
            if (maxchars > 0) {
                const metric = renderMetric(field.value, maxchars, 'chars', charsLabel);
                segments.push(metric.text);
                exceeded = exceeded || metric.exceeded;
            }

            counter.textContent = segments.join(' · ');
            counter.classList.toggle('text-danger', exceeded);
            counter.classList.toggle('text-muted', !exceeded);
        };

        field.addEventListener('input', render);
        render();

        return null;
    }).catch(() => {
        // If the strings fail to load, silently skip the live counter; server-side
        // validation still enforces the limit(s) regardless.
    });
};
