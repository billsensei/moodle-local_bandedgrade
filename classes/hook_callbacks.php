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

namespace local_bandedgrade;

use local_bandedgrade\local\bands;
use local_bandedgrade\local\colour;
use local_bandedgrade\local\counter;
use local_bandedgrade\local\quiz_config;
use local_bandedgrade\local\report;

/**
 * Hook callbacks for local_bandedgrade.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /** @var string[] Quiz pages that show the warning: the quiz page and the "Questions" page. */
    const WARNING_PAGES = ['mod-quiz-view', 'mod-quiz-edit'];

    /**
     * Warn the people who can edit a quiz when its questions no longer reach every band.
     *
     * This happens when questions are removed after the bands were set. The page header is printed after this
     * hook, so the notification appears on the same page.
     *
     * @param \core\hook\output\before_http_headers $hook The hook.
     */
    public static function before_http_headers(\core\hook\output\before_http_headers $hook): void {
        global $PAGE;
        if (during_initial_install() || !in_array($PAGE->pagetype, self::WARNING_PAGES, true) || !$PAGE->cm) {
            return;
        }
        $cm = $PAGE->cm;
        if ($cm->modname !== 'quiz' || !has_capability('moodle/course:manageactivities', $cm->context)) {
            return;
        }
        $config = quiz_config::get_enabled((int)$cm->instance);
        if (!$config || $config->ruletype !== bands::TYPE_COUNT) {
            return; // A percentage band can always be reached by getting every question right.
        }
        $total = counter::question_total((int)$cm->instance);
        $from = bands::first_unreachable($config->bands, $total);
        if ($from === null) {
            return;
        }
        $a = (object)[
            'total' => $total,
            'from' => $from,
            'url' => (new \moodle_url('/course/modedit.php', ['update' => $cm->id]))->out(),
        ];
        \core\notification::warning(get_string('unreachablewarning', 'local_bandedgrade', $a));
    }

    /**
     * Colour the banded scores in the grader report by the percentage of questions answered correctly.
     *
     * Adds the colours of every banded quiz of the course as JSON in a hidden element, and a small module that
     * colours the matching cells. Only the students the current user may see are included.
     *
     * @param \core\hook\output\before_standard_footer_html_generation $hook The hook.
     */
    public static function before_standard_footer_html_generation(
        \core\hook\output\before_standard_footer_html_generation $hook
    ): void {
        global $PAGE, $DB;
        if (during_initial_install() || $PAGE->pagetype !== 'grade-report-grader-index' || !$PAGE->course) {
            return;
        }
        $course = $PAGE->course;
        $coursecontext = \context_course::instance($course->id);
        $configs = $DB->get_records_sql(
            'SELECT bq.quizid, cm.id AS cmid
               FROM {local_bandedgrade_quiz} bq
               JOIN {course_modules} cm ON cm.instance = bq.quizid AND cm.deletioninprogress = 0
               JOIN {modules} m ON m.id = cm.module AND m.name = :modname
              WHERE bq.courseid = :courseid AND bq.enabled = 1',
            ['modname' => 'quiz', 'courseid' => $course->id]
        );
        if (!$configs) {
            return;
        }
        $modinfo = get_fast_modinfo($course);
        $groupid = (int)groups_get_course_group($course);
        $colours = [];
        foreach ($configs as $row) {
            $config = quiz_config::get_enabled((int)$row->quizid);
            $item = $config ? local\gradebook::get_item($config) : null;
            $cm = $modinfo->cms[$row->cmid] ?? null;
            if (!$item || !$cm || !$cm->uservisible) {
                continue;
            }
            if (!has_capability('mod/quiz:viewreports', $cm->context)) {
                continue; // The colour shows the share correct, which only people who see the quiz reports may know.
            }
            if ($item->is_hidden() && !has_capability('moodle/grade:viewhidden', $coursecontext)) {
                continue;
            }
            $quiz = $DB->get_record('quiz', ['id' => $row->quizid], 'id, grademethod', MUST_EXIST);
            $total = counter::question_total((int)$quiz->id);
            foreach (report::rows($quiz, $cm, $cm->context, $groupid) as $userrow) {
                $percent = colour::percent($userrow->used, $total);
                $name = colour::name($percent);
                if ($name !== null) {
                    $colours[$item->id][$userrow->userid] = [
                        colour::css_class($name),
                        get_string('gradecolourtitle', 'local_bandedgrade', format_float($percent, 1, true, true)),
                    ];
                }
            }
        }
        if (!$colours) {
            return;
        }
        $hook->add_html(\html_writer::div('', '', [
            'id' => 'local_bandedgrade-colours',
            'hidden' => 'hidden',
            'data-colours' => json_encode($colours),
        ]));
        $PAGE->requires->js_call_amd('local_bandedgrade/gradecolours', 'init');
    }
}
