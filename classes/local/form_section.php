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

namespace local_bandedgrade\local;

use local_bandedgrade\task\rescore_quiz;

/**
 * The "Grade by number correct" section of the quiz settings form.
 *
 * Called from the coursemodule_* callbacks in lib.php (RESEARCH-bandedgrade.md §7).
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class form_section {
    /**
     * Whether the form is a quiz settings form.
     *
     * @param \moodleform_mod $formwrapper The form.
     * @return bool True for a quiz.
     */
    private static function is_quiz_form(\moodleform_mod $formwrapper): bool {
        $current = $formwrapper->get_current();
        return isset($current->modulename) && $current->modulename === 'quiz';
    }

    /**
     * Whether the current user may change the gradebook setup of a course (create columns, change weights).
     *
     * @param int $courseid The course id.
     * @return bool True if allowed.
     */
    private static function can_manage_gradebook(int $courseid): bool {
        return has_capability('moodle/grade:manage', \context_course::instance($courseid));
    }

    /**
     * Add the section to the quiz settings form.
     *
     * @param \moodleform_mod $formwrapper The form.
     * @param \MoodleQuickForm $mform The form elements.
     */
    public static function add_elements(\moodleform_mod $formwrapper, \MoodleQuickForm $mform): void {
        global $PAGE;
        if (!self::is_quiz_form($formwrapper)) {
            return;
        }
        $quizid = (int)$formwrapper->get_instance();
        $config = $quizid ? quiz_config::get($quizid) : null;
        $total = $quizid ? counter::question_total($quizid) : 0;
        $presets = bands::presets();
        $current = $config && $config->bands ? $config->bands : $presets['zerotothree10'];
        $preset = $config ? bands::matching_preset($current) : 'zerotothree10';

        $mform->addElement('header', 'bandedgradehdr', get_string('formheader', 'local_bandedgrade'));

        // The section creates gradebook columns and changes course-total weights, so it needs gradebook rights,
        // not just the right to edit the quiz. Others only see how it is set up.
        if (!self::can_manage_gradebook((int)$formwrapper->get_course()->id)) {
            $enabled = $config && $config->enabled;
            $mform->addElement(
                'static',
                'bandedgrade_readonly',
                get_string('enabled', 'local_bandedgrade'),
                get_string($enabled ? 'statuson' : 'statusoff', 'local_bandedgrade') . ' ' .
                get_string('nogrademanage', 'local_bandedgrade')
            );
            if ($enabled) {
                $mform->addElement(
                    'static',
                    'bandedgrade_preview',
                    get_string('preview', 'local_bandedgrade'),
                    self::preview_html($current, $total)
                );
            }
            return;
        }

        $mform->addElement('advcheckbox', 'bandedgrade_enabled', get_string('enabled', 'local_bandedgrade'));
        $mform->addHelpButton('bandedgrade_enabled', 'enabled', 'local_bandedgrade');
        $mform->setDefault('bandedgrade_enabled', $config ? (int)$config->enabled : 0);

        $options = ['' => get_string('preset_custom', 'local_bandedgrade')];
        foreach (array_keys($presets) as $key) {
            $options[$key] = get_string('preset_' . $key, 'local_bandedgrade');
        }
        $mform->addElement('select', 'bandedgrade_preset', get_string('preset', 'local_bandedgrade'), $options);
        $mform->addHelpButton('bandedgrade_preset', 'preset', 'local_bandedgrade');
        $mform->setDefault('bandedgrade_preset', $preset);
        $mform->hideIf('bandedgrade_preset', 'bandedgrade_enabled');

        $totaltext = $quizid ? get_string('questiontotal', 'local_bandedgrade', $total)
            : get_string('questiontotalnew', 'local_bandedgrade');
        $mform->addElement('static', 'bandedgrade_total', '', $totaltext);
        $mform->hideIf('bandedgrade_total', 'bandedgrade_enabled');

        for ($i = 0; $i < bands::MAX_ROWS; $i++) {
            $row = [
                $mform->createElement(
                    'text',
                    "bandedgrade_from[$i]",
                    get_string('fromlabel', 'local_bandedgrade', $i + 1),
                    ['size' => 3, 'inputmode' => 'numeric', 'class' => 'local-bandedgrade-from']
                ),
                $mform->createElement('static', "bandedgrade_arrow$i", '', get_string('arrow', 'local_bandedgrade')),
                $mform->createElement(
                    'text',
                    "bandedgrade_score[$i]",
                    get_string('scorelabel', 'local_bandedgrade', $i + 1),
                    ['size' => 4, 'inputmode' => 'decimal', 'class' => 'local-bandedgrade-score']
                ),
            ];
            $mform->addGroup($row, "bandedgrade_row$i", get_string('bandrow', 'local_bandedgrade', $i + 1), ' ', false);
            $mform->setType("bandedgrade_from[$i]", PARAM_RAW_TRIMMED);
            $mform->setType("bandedgrade_score[$i]", PARAM_RAW_TRIMMED);
            if (isset($current[$i])) {
                $mform->setDefault("bandedgrade_from[$i]", $current[$i]['from']);
                $mform->setDefault("bandedgrade_score[$i]", format_float($current[$i]['score'], -1));
            }
            $mform->hideIf("bandedgrade_row$i", 'bandedgrade_enabled');
            $mform->disabledIf("bandedgrade_row$i", 'bandedgrade_preset', 'neq', '');
        }

        $mform->addElement(
            'static',
            'bandedgrade_preview',
            get_string('preview', 'local_bandedgrade'),
            \html_writer::div(self::preview_html($current, $total), '', ['id' => 'local_bandedgrade_preview',
            'aria-live' => 'polite'])
        );
        $mform->hideIf('bandedgrade_preview', 'bandedgrade_enabled');

        $cm = $formwrapper->get_coursemodule();
        if (
            $config && $config->enabled && $cm
                && has_capability('local/bandedgrade:recalculate', \context_module::instance($cm->id))
        ) {
            $link = \html_writer::link(
                new \moodle_url('/local/bandedgrade/recalculate.php', ['cmid' => $cm->id]),
                get_string('recalculatelink', 'local_bandedgrade')
            );
            $mform->addElement('static', 'bandedgrade_recalculate', '', $link);
            $mform->hideIf('bandedgrade_recalculate', 'bandedgrade_enabled');
        }

        $mform->addElement('advcheckbox', 'bandedgrade_zeroweight', get_string('zeroweight', 'local_bandedgrade'));
        $mform->addHelpButton('bandedgrade_zeroweight', 'zeroweight', 'local_bandedgrade');
        $mform->setDefault('bandedgrade_zeroweight', $config ? (int)$config->zeroweight : 1);
        $mform->hideIf('bandedgrade_zeroweight', 'bandedgrade_enabled');

        $PAGE->requires->js_call_amd('local_bandedgrade/form', 'init', [[
            'presets' => $presets,
            'total' => $total,
            'rows' => bands::MAX_ROWS,
            'strings' => self::preview_strings(),
        ]]);
    }

    /**
     * Check the section when the form is submitted.
     *
     * @param \moodleform_mod $formwrapper The form.
     * @param array $data Submitted data.
     * @return array Errors keyed by element name.
     */
    public static function validate(\moodleform_mod $formwrapper, array $data): array {
        if (!self::is_quiz_form($formwrapper) || empty($data['bandedgrade_enabled'])) {
            return [];
        }
        [, $errors] = self::bands_from_data($data);
        $result = [];
        foreach ($errors as $key => $message) {
            $result[$key === 'all' ? 'bandedgrade_row0' : "bandedgrade_row$key"] = $message;
        }
        return $result;
    }

    /**
     * Save the section after the quiz is saved: settings, grade item, weight, and a rescore if needed.
     *
     * @param \stdClass $moduleinfo The saved module data.
     * @param \stdClass $course The course.
     * @return \stdClass The module data, unchanged.
     */
    public static function save(\stdClass $moduleinfo, \stdClass $course): \stdClass {
        global $DB;
        if (($moduleinfo->modulename ?? '') !== 'quiz' || !isset($moduleinfo->bandedgrade_enabled)) {
            return $moduleinfo;
        }
        if (!self::can_manage_gradebook((int)$course->id)) {
            return $moduleinfo; // The form does not offer the section then; ignore anything posted anyway.
        }
        $quiz = $DB->get_record('quiz', ['id' => $moduleinfo->instance], 'id, course, name, grademethod', MUST_EXIST);
        $old = quiz_config::get($quiz->id);
        $enabled = !empty($moduleinfo->bandedgrade_enabled);
        $zeroweight = !empty($moduleinfo->bandedgrade_zeroweight);

        [$bands, $errors] = self::bands_from_data((array)$moduleinfo);
        if ($errors) {
            if ($enabled || !$old) {
                return $moduleinfo; // Cannot happen after validation; keep what was there.
            }
            $bands = $old->bands; // Turned off with untidy rows: keep the old bands.
        }
        if (!$enabled && !$old) {
            return $moduleinfo;
        }

        $config = quiz_config::save($quiz->id, $course->id, $enabled, $bands, $zeroweight);
        if (!$enabled) {
            if ($old && $old->enabled && $old->zeroweight) {
                gradebook::set_quiz_weight($quiz, false);
            }
            return $moduleinfo;
        }

        gradebook::ensure_item($config, $quiz);
        $weight = gradebook::set_quiz_weight($quiz, $zeroweight);
        if (!$zeroweight && $old && $old->zeroweight) {
            gradebook::set_quiz_weight($quiz, false);
        }
        if ($zeroweight && $weight === gradebook::WEIGHT_UNSUPPORTED) {
            \core\notification::warning(get_string('weightunsupported', 'local_bandedgrade'));
        }

        // Always rescore: the bands or the quiz's grading method may have changed. Duplicate tasks are merged.
        rescore_quiz::queue($quiz->id);
        return $moduleinfo;
    }

    /**
     * Get the bands from submitted data: a preset, or the rows.
     *
     * @param array $data Submitted data.
     * @return array [bands, errors] as from bands::from_rows().
     */
    private static function bands_from_data(array $data): array {
        $presets = bands::presets();
        $preset = $data['bandedgrade_preset'] ?? '';
        if ($preset !== '' && isset($presets[$preset])) {
            return [bands::normalise($presets[$preset]), []];
        }
        return bands::from_rows((array)($data['bandedgrade_from'] ?? []), (array)($data['bandedgrade_score'] ?? []));
    }

    /**
     * Strings for the preview, with placeholders the JavaScript fills in ({from}, {to}, {score}, {total}).
     *
     * @return array<string, string> Strings.
     */
    private static function preview_strings(): array {
        $a = (object)['from' => '{from}', 'to' => '{to}', 'score' => '{score}', 'total' => '{total}'];
        $strings = [];
        foreach (['previewsingle', 'previewrange', 'previewopen', 'previewunreachable', 'previewempty'] as $name) {
            $strings[$name] = get_string($name, 'local_bandedgrade', $a);
        }
        return $strings;
    }

    /**
     * The preview shown under the bands: which numbers correct give which score.
     *
     * The same text is rebuilt live by amd/src/form.js while the teacher types.
     *
     * @param array $bands Bands.
     * @param int $total Number of questions (0 if not known yet).
     * @return string HTML.
     */
    public static function preview_html(array $bands, int $total): string {
        $bands = bands::normalise($bands);
        if (!$bands) {
            return get_string('previewempty', 'local_bandedgrade');
        }
        $items = [];
        foreach ($bands as $i => $band) {
            $a = (object)['from' => $band['from'], 'score' => format_float($band['score'], -1), 'total' => $total];
            $next = $bands[$i + 1]['from'] ?? null;
            if ($total > 0 && $band['from'] > $total) {
                $items[] = get_string('previewunreachable', 'local_bandedgrade', $a);
                continue;
            }
            $a->to = $next !== null ? $next - 1 : ($total > 0 ? $total : null);
            if ($a->to !== null && $total > 0) {
                $a->to = min($a->to, $total);
            }
            if ($a->to === null) {
                $items[] = get_string('previewopen', 'local_bandedgrade', $a);
            } else {
                $items[] = get_string($a->to == $a->from ? 'previewsingle' : 'previewrange', 'local_bandedgrade', $a);
            }
        }
        return \html_writer::alist($items, ['class' => 'list-unstyled mb-0']);
    }
}
