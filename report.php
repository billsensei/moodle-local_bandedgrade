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

/**
 * "Number correct" report for a quiz: how many questions each student got fully right, and the score that gave.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_bandedgrade\event\report_viewed;
use local_bandedgrade\local\counter;
use local_bandedgrade\local\quiz_config;
use local_bandedgrade\local\report;

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

$cmid = required_param('cmid', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'quiz');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/quiz:viewreports', $context);
$quiz = $DB->get_record('quiz', ['id' => $cm->instance], 'id, course, name, grademethod', MUST_EXIST);

$url = new moodle_url('/local/bandedgrade/report.php', ['cmid' => $cmid]);
$returnurl = new moodle_url('/mod/quiz/view.php', ['id' => $cmid]);
$PAGE->set_url($url);
$PAGE->set_title(get_string('report', 'local_bandedgrade'));
$PAGE->set_heading($course->fullname);
$PAGE->activityheader->disable();

$config = quiz_config::get_enabled($quiz->id);
if (!$config) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('recalculatenotenabled', 'local_bandedgrade'), 'info');
    echo $OUTPUT->continue_button($returnurl);
    echo $OUTPUT->footer();
    exit;
}

report_viewed::create(['objectid' => $quiz->id, 'context' => $context])->trigger();

$quizname = format_string($quiz->name, true, ['context' => $context]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reportheading', 'local_bandedgrade', $quizname));
groups_print_activity_menu($cm, $url);
$groupid = (int)groups_get_activity_group($cm, true);

$total = counter::question_total($quiz->id);
$a = (object)[
    'total' => $total,
    'method' => quiz_get_grading_option_name($quiz->grademethod),
];
echo html_writer::tag('p', get_string('reportintro', 'local_bandedgrade', $a));

$rows = report::rows($quiz, $cm, $context, $groupid);
if (!$rows) {
    echo $OUTPUT->notification(get_string('reportempty', 'local_bandedgrade'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->id = 'local_bandedgrade_report';
$table->attributes['class'] = 'generaltable';
$table->head = [
    get_string('reportstudent', 'local_bandedgrade'),
    get_string('reportattempts', 'local_bandedgrade'),
    get_string('reportused', 'local_bandedgrade'),
    get_string('reportscore', 'local_bandedgrade'),
];
$waiting = get_string('reportwaiting', 'local_bandedgrade');
$percent = $config->ruletype === 'percent' && $total > 0;
foreach ($rows as $row) {
    $attempts = array_map(fn($attempt) => $attempt->pending ? $waiting : $attempt->correct, $row->attempts);
    $score = $row->score === null ? '-' : format_float($row->score, -1);
    if ($row->byhand) {
        $score .= ' ' . html_writer::span(get_string('reportbyhand', 'local_bandedgrade'), 'badge bg-info text-dark');
    }
    $table->data[] = [
        html_writer::link(new moodle_url('/user/view.php', ['id' => $row->userid, 'course' => $course->id]), s($row->name)),
        $attempts ? s(implode(', ', $attempts)) : '-',
        $row->used === null ? '-' : format_float($row->used, 2, true, true) .
            ($percent ? ' (' . format_float($row->used / $total * 100, 2, true, true) . '%)' : ''),
        $score,
    ];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
