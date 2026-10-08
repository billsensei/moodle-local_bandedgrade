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
 * @param {boolean} percent True when "from" is a percentage (0 to 100, up to 2 decimals).
 * @returns {Array} Bands {from, score}.
 */
const readBands = (form, rows, percent) => {
    const bands = [];
    for (let i = 0; i < rows; i++) {
        const from = input(form, 'from', i);
        const score = input(form, 'score', i);
        if (!from || !score) {
            continue;
        }
        const fromText = from.value.trim().replace(',', '.');
        const scoreText = score.value.trim().replace(',', '.');
        // The same rules as bands::from_rows() in PHP, so the preview never shows a band the server would refuse.
        const fromOk = percent ? /^\d+(\.\d{1,2})?$/.test(fromText) && Number(fromText) <= 100 : /^\d+$/.test(fromText);
        if (!fromOk || !/^\d+(\.\d{1,5})?$/.test(scoreText)) {
            continue;
        }
        bands.push({from: Number(fromText), score: Number(scoreText)});
    }
    return bands.sort((a, b) => a.from - b.from);
};

/**
 * Read the pass mark and the two scores as two bands (fail from 0, pass from the pass mark), if they are valid.
 *
 * The same rules as bands::from_passfail() in PHP.
 *
 * @param {HTMLFormElement} form The form.
 * @param {boolean} percent True when the pass mark is a percentage.
 * @returns {Array} Bands {from, score}, or none while a box is not valid.
 */
const readPassFail = (form, percent) => {
    const value = (name) => {
        const box = form.querySelector(`[name="bandedgrade_pf_${name}"]`);
        return box ? box.value.trim().replace(',', '.') : '';
    };
    const mark = value('mark');
    const markOk = percent ? /^\d+(\.\d{1,2})?$/.test(mark) && Number(mark) <= 100 : /^\d+$/.test(mark);
    const scoreOk = /^\d+(\.\d{1,5})?$/;
    if (!markOk || Number(mark) <= 0 || !scoreOk.test(value('pass')) || !scoreOk.test(value('fail'))) {
        return [];
    }
    return [{from: 0, score: Number(value('fail'))}, {from: Number(mark), score: Number(value('pass'))}];
};

/**
 * The fewest correct questions that reach a percentage (as bands::min_correct() in PHP).
 *
 * @param {number} percent The lower bound.
 * @param {number} total Number of questions (above 0).
 * @returns {number} The number of questions.
 */
const minCorrect = (percent, total) => {
    for (let correct = 0; correct <= total; correct++) {
        if (Math.round(correct / total * 10000) / 100 >= percent - 0.00001) {
            return correct;
        }
    }
    return total;
};

/**
 * Rebuild the preview list.
 *
 * @param {HTMLElement} target The preview container.
 * @param {Array} bands Bands.
 * @param {number} total Number of questions (0 if unknown).
 * @param {Object} strings Preview strings with placeholders.
 * @param {boolean} percent True when the bands are percentages.
 */
const renderPreview = (target, bands, total, strings, percent) => {
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
        if (percent) {
            values.min = total > 0 ? minCorrect(band.from, total) : 0;
            text = fill(total > 0 ? strings.previewpercentmin : strings.previewpercent, values);
        } else if (total > 0 && band.from > total) {
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
 * Set up the form section. The settings (presets, total, rows, strings) come from the preview's data-config attribute.
 */
export const init = () => {
    const preview = document.getElementById('local_bandedgrade_preview');
    const preset = document.querySelector('select[name="bandedgrade_preset"]');
    if (!preview || !preset) {
        return;
    }
    const config = JSON.parse(preview.dataset.config);
    const form = preset.form;
    const ruletype = form.querySelector('select[name="bandedgrade_ruletype"]');
    const scheme = form.querySelector('select[name="bandedgrade_scheme"]');
    const pfRuletype = form.querySelector('select[name="bandedgrade_pf_ruletype"]');
    const isPassFail = () => scheme !== null && scheme.value === config.passfail;
    const isPercent = () => {
        const select = isPassFail() ? pfRuletype : ruletype;
        return select !== null && select.value === 'percent';
    };
    const update = () => {
        const percent = isPercent();
        form.querySelectorAll('.local-bandedgrade-arrow').forEach((arrow) => {
            arrow.textContent = percent ? config.strings.arrowpercent : config.strings.arrow;
        });
        const bands = isPassFail() ? readPassFail(form, percent) : readBands(form, config.rows, percent);
        renderPreview(preview, bands, config.total, config.strings, percent);
    };

    preset.addEventListener('change', () => {
        const chosen = config.presets[preset.value];
        const bands = chosen ? chosen.bands : null;
        if (chosen && ruletype) {
            ruletype.value = chosen.ruletype;
        }
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
    [ruletype, pfRuletype, scheme].forEach((select) => {
        if (select) {
            select.addEventListener('change', update);
        }
    });
    update();
};
