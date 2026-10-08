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
 * "Recalculate scores" page for a quiz: keep or overwrite scores changed by hand (PLAN-bandedgrade.md §3a).
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_bandedgrade\local\quiz_config;
use local_bandedgrade\local\recalculate;

require(__DIR__ . '/../../config.php');

$cmid = required_param('cmid', PARAM_INT);
$mode = optional_param('mode', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'quiz');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('local/bandedgrade:recalculate', $context);
$quiz = $DB->get_record('quiz', ['id' => $cm->instance], 'id, course, name', MUST_EXIST);

$url = new moodle_url('/local/bandedgrade/recalculate.php', ['cmid' => $cmid]);
$returnurl = new moodle_url('/mod/quiz/view.php', ['id' => $cmid]);
$PAGE->set_url($url);
$PAGE->set_title(get_string('recalculate', 'local_bandedgrade'));
$PAGE->set_heading($course->fullname);
$PAGE->activityheader->disable();

if (!quiz_config::get_enabled($quiz->id)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('recalculatenotenabled', 'local_bandedgrade'), 'info');
    echo $OUTPUT->continue_button($returnurl);
    echo $OUTPUT->footer();
    exit;
}

if ($mode === 'keep' || ($mode === 'overwrite' && $confirm)) {
    require_sesskey();
    recalculate::request($quiz, $context, $mode === 'overwrite');
    redirect(
        $returnurl,
        get_string('recalculatequeued', 'local_bandedgrade'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$names = recalculate::changed_by_hand_names($quiz->id);
$quizname = format_string($quiz->name, true, ['context' => $context]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('recalculateheading', 'local_bandedgrade', $quizname));

if ($mode === 'overwrite') {
    // Second step: confirm before replacing scores changed by hand.
    require_sesskey();
    $a = (object)['count' => count($names), 'names' => implode(', ', $names)];
    echo $OUTPUT->confirm(
        get_string('overwriteconfirm', 'local_bandedgrade', $a),
        new moodle_url($url, ['mode' => 'overwrite', 'confirm' => 1, 'sesskey' => sesskey()]),
        $url
    );
} else {
    echo $OUTPUT->render_from_template('local_bandedgrade/recalculate_form', [
        'action' => $url->out(false),
        'cmid' => $cmid,
        'sesskey' => sesskey(),
        'changedcount' => count($names),
        'changednames' => implode(', ', $names),
        'cancelurl' => $returnurl->out(false),
    ]);
}
echo $OUTPUT->footer();
