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

import $ from 'jquery';
import SortableList from 'core/sortable_list';

/**
 * Restructures the submission form's repeated "Speakers" fields into one visual
 * section with drag-and-drop reordering, instead of formslib's default of one
 * collapsible sub-section per repeated row (user feedback, 2026-07-05: "Speakers
 * should be one section containing all the speaker settings ... Speaker 1, Speaker 2
 * ... should NOT be separate sections. Speaker order should be settable by drag and
 * drop"). \mod_confsubmissions\form\submission_form deliberately no longer adds a
 * per-row 'header' element (that was what made every "Speaker N" formslib's own
 * fieldset) or a visible "Display order" dropdown (now a hidden
 * speakerposition[N] input) -- both are entirely reconstructed here instead, by
 * re-parenting the real .fitem field wrappers PHP already rendered into new card
 * containers, rather than duplicating or replacing any form field.
 *
 * Row 0 (the primary presenter) is pinned outside the sortable container -- it has
 * no speakerposition/speakerdelete field to begin with (submission_form.php removes
 * both for row 0), matching the existing "cannot be removed or reordered" rule.
 *
 * @module     mod_confsubmissions/speaker_order
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const FIELD_BASENAMES = [
    'speakeruserid', 'speakermanual', 'speakername', 'speakeremail', 'speakerposition', 'speakerdelete',
];

/**
 * Finds the .fitem wrapper formslib rendered around a given form field.
 *
 * @param {HTMLElement} field The form field
 * @return {HTMLElement} Its closest .fitem ancestor (or the field itself, as a fallback)
 */
const closestFitem = (field) => field.closest('.fitem') || field;

/**
 * Collects the rendered .fitem wrappers for every field belonging to a given
 * repeat-row index, in a stable field order.
 *
 * @param {Number} index The repeat-row index (formslib's speakeruserid[N] etc.)
 * @return {HTMLElement[]} The .fitem wrappers found for this row (skips any field
 *         submission_form.php removed for this row, e.g. speakerdelete[0])
 */
const getRowFitems = (index) => {
    const fitems = [];
    FIELD_BASENAMES.forEach((base) => {
        const field = document.querySelector(`[name="${base}[${index}]"]`);
        if (field) {
            fitems.push(closestFitem(field));
        }
    });
    return fitems;
};

/**
 * Renumbers every card's visible "Speaker N" label and hidden speakerposition value
 * to match current DOM order. Called on init and after every drag-and-drop reorder.
 *
 * @param {HTMLElement} sortableContainer The container holding the reorderable cards
 * @param {String} labeltemplate get_string('speakerno', ...) with a literal '{no}'
 *        placeholder (row 0, the primary presenter, is always "Speaker 1"; the first
 *        card in the sortable container is therefore "Speaker 2", and so on)
 * @param {String} removelabeltemplate get_string('removespeaker', ...) with a literal
 *        '{no}' placeholder, kept in sync with the visible label so the delete
 *        button's own text always names the row's current visual position
 */
const renumber = (sortableContainer, labeltemplate, removelabeltemplate) => {
    const cards = Array.from(sortableContainer.querySelectorAll(':scope > .mod_confsubmissions-speaker-row'));
    cards.forEach((card, i) => {
        const label = card.querySelector('.mod_confsubmissions-speaker-label');
        if (label) {
            label.textContent = labeltemplate.replace('{no}', String(i + 2));
        }
        const posField = card.querySelector('[name^="speakerposition"]');
        if (posField) {
            posField.value = String(i + 1);
        }
        const deleteButton = card.querySelector('[name^="speakerdelete"]');
        if (deleteButton) {
            deleteButton.value = removelabeltemplate.replace('{no}', String(i + 2));
        }
    });
};

/**
 * Builds the fixed, non-draggable card for row 0 (the primary presenter).
 *
 * @param {String} primarylabel get_string('primaryspeaker', 'mod_confsubmissions')
 * @return {HTMLElement} The card, with row 0's real .fitem fields moved inside it
 */
const buildPrimaryCard = (primarylabel) => {
    const card = document.createElement('div');
    card.className = 'mod_confsubmissions-speaker-row mod_confsubmissions-speaker-row-primary';

    const heading = document.createElement('div');
    heading.className = 'mod_confsubmissions-speaker-heading';
    heading.textContent = primarylabel;
    card.appendChild(heading);

    getRowFitems(0).forEach((fitem) => card.appendChild(fitem));
    return card;
};

/**
 * Builds one draggable card for a co-presenter row.
 *
 * @param {Number} index The repeat-row index
 * @param {String} reorderlabel get_string('speakerposition', 'mod_confsubmissions'),
 *        used as the drag handle's aria-label
 * @return {HTMLElement} The card, with this row's real .fitem fields moved inside it
 */
const buildCoPresenterCard = (index, reorderlabel) => {
    const card = document.createElement('div');
    card.className = 'mod_confsubmissions-speaker-row';
    card.dataset.speakerIndex = String(index);

    const heading = document.createElement('div');
    heading.className = 'mod_confsubmissions-speaker-heading';

    const handle = document.createElement('span');
    handle.className = 'mod_confsubmissions-speaker-draghandle';
    handle.setAttribute('data-drag-type', 'move');
    handle.setAttribute('tabindex', '0');
    handle.setAttribute('role', 'button');
    handle.setAttribute('aria-label', reorderlabel);
    handle.textContent = '⠿';
    heading.appendChild(handle);

    const label = document.createElement('strong');
    label.className = 'mod_confsubmissions-speaker-label';
    heading.appendChild(label);

    card.appendChild(heading);
    getRowFitems(index).forEach((fitem) => card.appendChild(fitem));
    return card;
};

/**
 * Restructures the form and wires up drag-and-drop reordering.
 *
 * @param {String} labeltemplate get_string('speakerno', 'mod_confsubmissions', '{no}')
 * @param {String} primarylabel get_string('primaryspeaker', 'mod_confsubmissions')
 * @param {String} reorderlabel get_string('speakerposition', 'mod_confsubmissions')
 * @param {String} removelabeltemplate get_string('removespeaker', 'mod_confsubmissions', '{no}')
 */
export const init = (labeltemplate, primarylabel, reorderlabel, removelabeltemplate) => {
    const repeatsInput = document.querySelector('[name="speakerrepeats"]');
    const firstField = document.querySelector('[name="speakeruserid[0]"]');
    if (!repeatsInput || !firstField) {
        return;
    }

    const count = parseInt(repeatsInput.value, 10) || 0;
    const anchorFitem = closestFitem(firstField);
    const parent = anchorFitem.parentNode;

    // A placeholder marks the insertion point before any .fitem gets moved out of the
    // DOM's current flow and into a card (moving a node removes it from its original
    // parent, so the original anchor can no longer be used as an insertBefore() target
    // by the time the cards are built).
    const marker = document.createComment('mod_confsubmissions-speaker-rows');
    parent.insertBefore(marker, anchorFitem);

    const primaryCard = buildPrimaryCard(primarylabel);

    const sortableContainer = document.createElement('div');
    sortableContainer.className = 'mod_confsubmissions-speaker-sortable';
    for (let i = 1; i < count; i++) {
        const fitems = getRowFitems(i);
        if (fitems.length === 0) {
            // A deleted row: repeat_elements() still reserves the index, but
            // submission_form.php's isDeleted branch adds no visible fields for it.
            continue;
        }
        sortableContainer.appendChild(buildCoPresenterCard(i, reorderlabel));
    }

    parent.insertBefore(primaryCard, marker);
    parent.insertBefore(sortableContainer, marker);
    marker.remove();

    renumber(sortableContainer, labeltemplate, removelabeltemplate);

    if (sortableContainer.children.length > 1) {
        // eslint-disable-next-line no-new
        new SortableList('.mod_confsubmissions-speaker-sortable');
        $(sortableContainer).on(
            SortableList.EVENTS.DROP,
            () => renumber(sortableContainer, labeltemplate, removelabeltemplate)
        );
    }
};
