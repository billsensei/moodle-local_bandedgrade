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
 * Colour the grade of each attempt in the quiz's Grades and Responses reports by the percentage of questions
 * answered correctly.
 *
 * @module     local_bandedgrade/attemptcolours
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The attempt a link to the review page points at.
 *
 * @param {HTMLAnchorElement} link The link.
 * @returns {String|null} The attempt id, or null when the link is not a link to an attempt.
 */
const attemptOf = (link) => {
    try {
        const url = new URL(link.getAttribute('href'), window.location.href);
        return url.pathname.endsWith('/review.php') ? url.searchParams.get('attempt') : null;
    } catch (e) {
        return null;
    }
};

/**
 * The cell of a row that shows the attempt's grade: the one whose link goes to the review page.
 *
 * The name cell also links to the review page (with the class "reviewlink"), and the cells of the questions link to a
 * single question, so they are skipped. The position of the column changes with the options of the report.
 *
 * @param {HTMLTableRowElement} row The row.
 * @returns {Array} The cell and the attempt id, or an empty array when the row has no grade link.
 */
const gradeCellOf = (row) => {
    for (const link of row.querySelectorAll('td a[href*="review.php"]')) {
        const attempt = attemptOf(link);
        if (attempt !== null && !link.classList.contains('reviewlink')) {
            return [link.closest('td'), attempt];
        }
    }
    return [];
};

/**
 * Colour the cells listed in the page's hidden element.
 *
 * The element holds {attemptid: [cssclass, title]}.
 */
export const init = () => {
    const holder = document.getElementById('local_bandedgrade-attempt-colours');
    if (!holder) {
        return;
    }
    let colours;
    try {
        colours = JSON.parse(holder.dataset.colours);
    } catch (e) {
        return;
    }
    document.querySelectorAll('table#attempts tbody tr, table#responses tbody tr').forEach((row) => {
        const [cell, attempt] = gradeCellOf(row);
        if (cell && Object.prototype.hasOwnProperty.call(colours, attempt)) {
            const [cssclass, title] = colours[attempt];
            cell.classList.add(cssclass);
            cell.title = title;
        }
    });
};
