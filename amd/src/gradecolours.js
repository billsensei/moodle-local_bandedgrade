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
 * Colour the banded scores in the grader report by the percentage of questions answered correctly.
 *
 * @module     local_bandedgrade/gradecolours
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Colour the cells listed in the page's hidden element.
 *
 * The element holds {itemid: {userid: [cssclass, title]}}; the grader report marks each user row with
 * data-uid and each grade cell with data-itemid.
 */
export const init = () => {
    const holder = document.getElementById('local_bandedgrade-colours');
    if (!holder) {
        return;
    }
    let colours;
    try {
        colours = JSON.parse(holder.dataset.colours);
    } catch (e) {
        return;
    }
    Object.entries(colours).forEach(([itemid, users]) => {
        Object.entries(users).forEach(([userid, [cssclass, title]]) => {
            const cell = document.querySelector(`tr[data-uid="${userid}"] td[data-itemid="${itemid}"]`);
            if (cell) {
                cell.classList.add(cssclass);
                cell.title = title;
            }
        });
    });
};
