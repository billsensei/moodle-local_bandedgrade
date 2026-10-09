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
        // A new quiz starts with the first preset (the 0-3 one unless an administrator changed the presets).
        $current = $config && $config->bands ? $config->bands : (reset($presets) ?: []);
        $ruletype = $config && $config->bands ? $config->ruletype : bands::preset_ruletype((string)array_key_first($presets));
        $preset = bands::matching_preset($current, $ruletype);
        $passfail = $config && $config->scheme === bands::SCHEME_PASSFAIL ? bands::to_passfail($config->bands) : null;
        $scheme = $passfail ? bands::SCHEME_PASSFAIL : bands::SCHEME_BANDS;

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
                    self::preview_html($current, $total, $ruletype)
                );
            }
            return;
        }

        $mform->addElement('advcheckbox', 'bandedgrade_enabled', get_string('enabled', 'local_bandedgrade'));
        $mform->addHelpButton('bandedgrade_enabled', 'enabled', 'local_bandedgrade');
        $mform->setDefault('bandedgrade_enabled', $config ? (int)$config->enabled : 0);

        $mform->addElement('select', 'bandedgrade_scheme', get_string('scheme', 'local_bandedgrade'), [
            bands::SCHEME_BANDS => get_string('scheme_bands', 'local_bandedgrade'),
            bands::SCHEME_PASSFAIL => get_string('scheme_passfail', 'local_bandedgrade'),
        ]);
        $mform->addHelpButton('bandedgrade_scheme', 'scheme', 'local_bandedgrade');
        $mform->setDefault('bandedgrade_scheme', $scheme);
        $mform->hideIf('bandedgrade_scheme', 'bandedgrade_enabled');

        $options = ['' => get_string('preset_custom', 'local_bandedgrade')] + bands::preset_names();
        $mform->addElement('select', 'bandedgrade_preset', get_string('preset', 'local_bandedgrade'), $options);
        $mform->addHelpButton('bandedgrade_preset', 'preset', 'local_bandedgrade');
        $mform->setDefault('bandedgrade_preset', $preset);
        $mform->hideIf('bandedgrade_preset', 'bandedgrade_enabled');
        $mform->hideIf('bandedgrade_preset', 'bandedgrade_scheme', 'eq', bands::SCHEME_PASSFAIL);

        $mform->addElement('select', 'bandedgrade_ruletype', get_string('ruletype', 'local_bandedgrade'), [
            bands::TYPE_COUNT => get_string('ruletype_count', 'local_bandedgrade'),
            bands::TYPE_PERCENT => get_string('ruletype_percent', 'local_bandedgrade'),
        ]);
        $mform->addHelpButton('bandedgrade_ruletype', 'ruletype', 'local_bandedgrade');
        $mform->setDefault('bandedgrade_ruletype', $ruletype);
        $mform->hideIf('bandedgrade_ruletype', 'bandedgrade_enabled');
        $mform->hideIf('bandedgrade_ruletype', 'bandedgrade_scheme', 'eq', bands::SCHEME_PASSFAIL);
        $mform->disabledIf('bandedgrade_ruletype', 'bandedgrade_preset', 'neq', '');

        // The pass/fail scheme: one pass mark and two scores. They are stored as two bands.
        $mform->addElement('select', 'bandedgrade_pf_ruletype', get_string('passfailbasedon', 'local_bandedgrade'), [
            bands::TYPE_COUNT => get_string('ruletype_count', 'local_bandedgrade'),
            bands::TYPE_PERCENT => get_string('ruletype_percent', 'local_bandedgrade'),
        ]);
        $mform->setDefault('bandedgrade_pf_ruletype', $passfail ? $config->ruletype : bands::TYPE_COUNT);
        $mform->addElement(
            'text',
            'bandedgrade_pf_mark',
            get_string('passmark', 'local_bandedgrade'),
            ['size' => 5, 'inputmode' => 'decimal']
        );
        $mform->addHelpButton('bandedgrade_pf_mark', 'passmark', 'local_bandedgrade');
        $mform->setType('bandedgrade_pf_mark', PARAM_RAW_TRIMMED);
        $mform->addElement(
            'text',
            'bandedgrade_pf_pass',
            get_string('passscore', 'local_bandedgrade'),
            ['size' => 5, 'inputmode' => 'decimal']
        );
        $mform->setType('bandedgrade_pf_pass', PARAM_RAW_TRIMMED);
        $mform->addElement(
            'text',
            'bandedgrade_pf_fail',
            get_string('failscore', 'local_bandedgrade'),
            ['size' => 5, 'inputmode' => 'decimal']
        );
        $mform->setType('bandedgrade_pf_fail', PARAM_RAW_TRIMMED);
        // Without a saved pass mark, start from the pass/fail preset (6 of 10 correct) with scores 1 and 0.
        $mform->setDefault('bandedgrade_pf_mark', $passfail ? $passfail[0] : 6);
        $mform->setDefault('bandedgrade_pf_pass', format_float($passfail ? $passfail[1] : 1, -1));
        $mform->setDefault('bandedgrade_pf_fail', format_float($passfail ? $passfail[2] : 0, -1));
        foreach (['ruletype', 'mark', 'pass', 'fail'] as $name) {
            $mform->hideIf("bandedgrade_pf_$name", 'bandedgrade_enabled');
            $mform->hideIf("bandedgrade_pf_$name", 'bandedgrade_scheme', 'neq', bands::SCHEME_PASSFAIL);
        }

        $totaltext = $quizid ? get_string('questiontotal', 'local_bandedgrade', $total)
            : get_string('questiontotalnew', 'local_bandedgrade');
        $mform->addElement('static', 'bandedgrade_total', '', $totaltext);
        $mform->hideIf('bandedgrade_total', 'bandedgrade_enabled');

        $slotoptions = $quizid ? self::slot_options($quizid) : [];
        if ($slotoptions) {
            $mform->addElement('autocomplete', 'bandedgrade_required', get_string('required', 'local_bandedgrade'), $slotoptions, [
                'multiple' => true,
                'noselectionstring' => get_string('requirednone', 'local_bandedgrade'),
            ]);
            $mform->addHelpButton('bandedgrade_required', 'required', 'local_bandedgrade');
            $mform->setType('bandedgrade_required', PARAM_INT);
            $chosen = $config ? array_values(array_intersect($config->requiredslots, array_keys($slotoptions))) : [];
            $mform->setDefault('bandedgrade_required', $chosen);
            $mform->hideIf('bandedgrade_required', 'bandedgrade_enabled');
        }

        for ($i = 0; $i < bands::MAX_ROWS; $i++) {
            $row = [
                $mform->createElement(
                    'text',
                    "bandedgrade_from[$i]",
                    get_string('fromlabel', 'local_bandedgrade', $i + 1),
                    ['size' => 3, 'inputmode' => 'numeric', 'class' => 'local-bandedgrade-from']
                ),
                $mform->createElement(
                    'static',
                    "bandedgrade_arrow$i",
                    '',
                    \html_writer::span(
                        get_string($ruletype === bands::TYPE_PERCENT ? 'arrowpercent' : 'arrow', 'local_bandedgrade'),
                        'local-bandedgrade-arrow'
                    )
                ),
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
            $mform->hideIf("bandedgrade_row$i", 'bandedgrade_scheme', 'eq', bands::SCHEME_PASSFAIL);
            $mform->disabledIf("bandedgrade_row$i", 'bandedgrade_preset', 'neq', '');
        }

        // The script reads its settings from a data attribute: they are too long for js_call_amd() arguments.
        $presetdata = [];
        foreach ($presets as $key => $presetbands) {
            $presetdata[$key] = ['ruletype' => bands::preset_ruletype($key), 'bands' => $presetbands];
        }
        $jsconfig = [
            'presets' => $presetdata,
            'total' => $total,
            'rows' => bands::MAX_ROWS,
            'passfail' => bands::SCHEME_PASSFAIL,
            'strings' => self::preview_strings() + [
                'arrow' => get_string('arrow', 'local_bandedgrade'),
                'arrowpercent' => get_string('arrowpercent', 'local_bandedgrade'),
            ],
        ];
        $mform->addElement(
            'static',
            'bandedgrade_preview',
            get_string('preview', 'local_bandedgrade'),
            \html_writer::div(self::preview_html($current, $total, $ruletype), '', [
                'id' => 'local_bandedgrade_preview',
                'aria-live' => 'polite',
                'data-config' => json_encode($jsconfig),
            ])
        );
        $mform->hideIf('bandedgrade_preview', 'bandedgrade_enabled');

        $cm = $formwrapper->get_coursemodule();
        $links = [];
        if ($config && $config->enabled && $cm) {
            $cmcontext = \context_module::instance($cm->id);
            if (has_capability('mod/quiz:viewreports', $cmcontext)) {
                $links[] = \html_writer::link(
                    new \moodle_url('/local/bandedgrade/report.php', ['cmid' => $cm->id]),
                    get_string('reportlink', 'local_bandedgrade')
                );
            }
            if (has_capability('local/bandedgrade:recalculate', $cmcontext)) {
                $links[] = \html_writer::link(
                    new \moodle_url('/local/bandedgrade/recalculate.php', ['cmid' => $cm->id]),
                    get_string('recalculatelink', 'local_bandedgrade')
                );
            }
        }
        if ($links) {
            $mform->addElement('static', 'bandedgrade_recalculate', '', implode(' · ', $links));
            $mform->hideIf('bandedgrade_recalculate', 'bandedgrade_enabled');
        }

        $mform->addElement('advcheckbox', 'bandedgrade_zeroweight', get_string('zeroweight', 'local_bandedgrade'));
        $mform->addHelpButton('bandedgrade_zeroweight', 'zeroweight', 'local_bandedgrade');
        $mform->setDefault('bandedgrade_zeroweight', $config ? (int)$config->zeroweight : 1);
        $mform->hideIf('bandedgrade_zeroweight', 'bandedgrade_enabled');

        $PAGE->requires->js_call_amd('local_bandedgrade/form', 'init');
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
        [, $errors, , $scheme] = self::bands_from_data($data);
        $result = [];
        foreach ($errors as $key => $message) {
            if ($scheme === bands::SCHEME_PASSFAIL) {
                $result["bandedgrade_pf_$key"] = $message; // The keys are mark, pass and fail.
            } else {
                $result[$key === 'all' ? 'bandedgrade_row0' : "bandedgrade_row$key"] = $message;
            }
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

        [$bands, $errors, $ruletype, $scheme] = self::bands_from_data((array)$moduleinfo);
        if ($errors) {
            if ($enabled || !$old) {
                return $moduleinfo; // Cannot happen after validation; keep what was there.
            }
            $bands = $old->bands; // Turned off with untidy rows: keep the old bands.
            $ruletype = $old->ruletype;
            $scheme = $old->scheme;
        }
        if (!$enabled && !$old) {
            return $moduleinfo;
        }

        // The required questions: only slots of this quiz that are counted. Not posted when the section is off
        // (the box always posts when it is shown, empty when nothing is chosen).
        $required = $old ? $old->requiredslots : [];
        if (isset($moduleinfo->bandedgrade_required) && $enabled) {
            $required = array_values(array_intersect(
                array_map('intval', (array)$moduleinfo->bandedgrade_required),
                array_keys(self::slot_options((int)$quiz->id))
            ));
        }

        $config = quiz_config::save($quiz->id, $course->id, $enabled, $bands, $zeroweight, $ruletype, $scheme, $required);
        if (!$enabled) {
            if ($old && $old->enabled && $old->zeroweight) {
                gradebook::set_quiz_weight($quiz, false);
            }
            return $moduleinfo;
        }

        gradebook::ensure_item($config, $quiz);
        if ($old && $old->scheme === bands::SCHEME_PASSFAIL && $config->scheme !== bands::SCHEME_PASSFAIL) {
            gradebook::clear_pass_grade($config); // Back to bands: the grade to pass was ours, take it back.
        }
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
     * The questions a teacher can make required: the quiz's counted slots, as slot id => "number. name".
     *
     * @param int $quizid The quiz id.
     * @return array<int, string> Options in quiz order (empty when the quiz has no counted question).
     */
    public static function slot_options(int $quizid): array {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $structure = \mod_quiz\structure::create_for_quiz(\mod_quiz\quiz_settings::create($quizid));
        $options = [];
        foreach ($structure->get_slots() as $slot) {
            if ((float)$slot->maxmark <= 0) {
                continue;
            }
            $name = $structure->get_question_in_slot($slot->slot)->name ?? '';
            $options[(int)$slot->id] = get_string('requiredoption', 'local_bandedgrade', (object)[
                'number' => $structure->get_displayed_number_for_slot($slot->slot),
                'name' => shorten_text(format_string($name), 80),
            ]);
        }
        return $options;
    }

    /**
     * Get the bands from submitted data: a pass mark, a preset, or the rows.
     *
     * @param array $data Submitted data.
     * @return array [bands, errors, ruletype, scheme]: bands and errors as from bands::from_rows() (for the pass/fail
     *     scheme the errors are keyed mark, pass and fail), then the rule type and the scheme.
     */
    private static function bands_from_data(array $data): array {
        if (($data['bandedgrade_scheme'] ?? '') === bands::SCHEME_PASSFAIL) {
            $ruletype = (string)($data['bandedgrade_pf_ruletype'] ?? '');
            $ruletype = bands::is_ruletype($ruletype) ? $ruletype : bands::TYPE_COUNT;
            [$bands, $errors] = bands::from_passfail(
                (string)($data['bandedgrade_pf_mark'] ?? ''),
                (string)($data['bandedgrade_pf_pass'] ?? ''),
                (string)($data['bandedgrade_pf_fail'] ?? ''),
                $ruletype
            );
            return [$bands, $errors, $ruletype, bands::SCHEME_PASSFAIL];
        }
        $presets = bands::presets();
        $preset = $data['bandedgrade_preset'] ?? '';
        if ($preset !== '' && isset($presets[$preset])) {
            return [bands::normalise($presets[$preset]), [], bands::preset_ruletype($preset), bands::SCHEME_BANDS];
        }
        $ruletype = (string)($data['bandedgrade_ruletype'] ?? '');
        $ruletype = bands::is_ruletype($ruletype) ? $ruletype : bands::TYPE_COUNT;
        [$bands, $errors] = bands::from_rows(
            (array)($data['bandedgrade_from'] ?? []),
            (array)($data['bandedgrade_score'] ?? []),
            $ruletype
        );
        return [$bands, $errors, $ruletype, bands::SCHEME_BANDS];
    }

    /**
     * Strings for the preview, with placeholders the JavaScript fills in ({from}, {to}, {score}, {total}).
     *
     * @return array<string, string> Strings.
     */
    private static function preview_strings(): array {
        $a = (object)['from' => '{from}', 'to' => '{to}', 'score' => '{score}', 'total' => '{total}', 'min' => '{min}'];
        $strings = [];
        foreach (
            ['previewsingle', 'previewrange', 'previewopen', 'previewunreachable', 'previewempty', 'previewpercent',
            'previewpercentmin'] as $name
        ) {
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
     * @param string $ruletype bands::TYPE_COUNT or bands::TYPE_PERCENT.
     * @return string HTML.
     */
    public static function preview_html(array $bands, int $total, string $ruletype = bands::TYPE_COUNT): string {
        $bands = bands::normalise($bands);
        if (!$bands) {
            return get_string('previewempty', 'local_bandedgrade');
        }
        $items = [];
        foreach ($bands as $i => $band) {
            if ($ruletype === bands::TYPE_PERCENT) {
                $a = (object)['from' => format_float($band['from'], 2, true, true), 'score' => format_float($band['score'], -1),
                    'total' => $total, 'min' => $total > 0 ? bands::min_correct($band['from'], $total) : null];
                $items[] = get_string($total > 0 ? 'previewpercentmin' : 'previewpercent', 'local_bandedgrade', $a);
                continue;
            }
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
