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
 * Presets and live preview for the "Grade by number correct" quiz settings.
 *
 * The preview text matches form_section::preview_html() in PHP.
 *
 * @module     local_bandedgrade/form
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Replace {name} placeholders in a string.
 *
 * @param {string} template Text with placeholders.
 * @param {Object} values Placeholder values.
 * @returns {string} The text.
 */
const fill = (template, values) => Object.keys(values).reduce(
    (text, key) => text.split(`{${key}}`).join(String(values[key])), template);

/**
 * Get a band row's input.
 *
 * @param {HTMLFormElement} form The form.
 * @param {string} kind "from" or "score".
 * @param {number} index Row index.
 * @returns {HTMLInputElement|null} The input.
 */
const input = (form, kind, index) => form.querySelector(`[name="bandedgrade_${kind}[${index}]"]`);

/**
 * Read the complete, valid rows, sorted by "from".
 *
 * @param {HTMLFormElement} form The form.
 * @param {number} rows Number of rows.
 * @returns {Array} Bands {from, score}.
 */
const readBands = (form, rows) => {
    const bands = [];
    for (let i = 0; i < rows; i++) {
        const from = input(form, 'from', i);
        const score = input(form, 'score', i);
        if (!from || !score) {
            continue;
        }
        const fromText = from.value.trim();
        const scoreText = score.value.trim().replace(',', '.');
        // The same rules as bands::from_rows() in PHP, so the preview never shows a band the server would refuse.
        if (!/^\d+$/.test(fromText) || !/^\d+(\.\d{1,5})?$/.test(scoreText)) {
            continue;
        }
        bands.push({from: parseInt(fromText, 10), score: Number(scoreText)});
    }
    return bands.sort((a, b) => a.from - b.from);
};

/**
 * Rebuild the preview list.
 *
 * @param {HTMLElement} target The preview container.
 * @param {Array} bands Bands.
 * @param {number} total Number of questions (0 if unknown).
 * @param {Object} strings Preview strings with placeholders.
 */
const renderPreview = (target, bands, total, strings) => {
    target.textContent = '';
    if (!bands.length) {
        target.textContent = strings.previewempty;
        return;
    }
    const list = document.createElement('ul');
    list.className = 'list-unstyled mb-0';
    bands.forEach((band, i) => {
        const values = {from: band.from, score: band.score, total: total};
        const next = i + 1 < bands.length ? bands[i + 1].from : null;
        let text;
        if (total > 0 && band.from > total) {
            text = fill(strings.previewunreachable, values);
        } else {
            let to = null;
            if (next !== null) {
                to = next - 1;
            } else if (total > 0) {
                to = total;
            }
            if (to !== null && total > 0) {
                to = Math.min(to, total);
            }
            if (to === null) {
                text = fill(strings.previewopen, values);
            } else {
                values.to = to;
                text = fill(to === band.from ? strings.previewsingle : strings.previewrange, values);
            }
        }
        const item = document.createElement('li');
        item.textContent = text;
        list.appendChild(item);
    });
    target.appendChild(list);
};

/**
 * Set up the form section.
 *
 * @param {Object} config Settings from PHP: presets, total, rows, strings.
 */
export const init = (config) => {
    const preview = document.getElementById('local_bandedgrade_preview');
    const preset = document.querySelector('select[name="bandedgrade_preset"]');
    if (!preview || !preset) {
        return;
    }
    const form = preset.form;
    const update = () => renderPreview(preview, readBands(form, config.rows), config.total, config.strings);

    preset.addEventListener('change', () => {
        const bands = config.presets[preset.value];
        if (bands) {
            for (let i = 0; i < config.rows; i++) {
                input(form, 'from', i).value = bands[i] ? bands[i].from : '';
                input(form, 'score', i).value = bands[i] ? bands[i].score : '';
            }
        }
        update();
    });
    form.addEventListener('input', (e) => {
        if (e.target.name && e.target.name.startsWith('bandedgrade_')) {
            update();
        }
    });
    update();
};
