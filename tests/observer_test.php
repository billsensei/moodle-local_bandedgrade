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

use local_bandedgrade\local\quiz_config;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/report/reportlib.php');
require_once($CFG->dirroot . '/mod/quiz/report/overview/report.php');
require_once($CFG->dirroot . '/mod/quiz/report/overview/overview_form.php');

/**
 * Tests that quiz and course events keep the scores up to date.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(observer::class)]
#[CoversClass(task\rescore_quiz::class)]
final class observer_test extends \advanced_testcase {
    use \local_bandedgrade\tests\quiz_trait;

    protected function setUp(): void {
        parent::setUp();
        $this->setup_course();
    }

    /**
     * Number of queued rescore tasks.
     *
     * @return int Count.
     */
    private function queued(): int {
        global $DB;
        return $DB->count_records('task_adhoc', ['classname' => '\\local_bandedgrade\\task\\rescore_quiz']);
    }

    public function test_regrade_updates_score(): void {
        global $DB;
        $quiz = $this->make_frog_quiz(2);
        $this->enable($quiz, [0 => 0, 1 => 1, 2 => 2]);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $attempt = $this->attempt($quiz, $student, [1 => 'toad', 2 => 'frog']);
        $this->assertEquals(1, $this->score($quiz, $student->id), '"toad" is only partly right.');

        // The teacher decides "toad" is fully right for question 1, then regrades.
        $questionid = $DB->get_field_sql('SELECT qa.questionid FROM {question_attempts} qa
                                            WHERE qa.questionusageid = ? AND qa.slot = 1', [$attempt->uniqueid]);
        $DB->set_field_select(
            'question_answers',
            'fraction',
            1,
            'question = ? AND ' . $DB->sql_compare_text('answer') . ' = ?',
            [$questionid, 'toad']
        );
        \question_bank::notify_question_edited($questionid);
        [$course, $cm] = get_course_and_cm_from_instance($quiz->id, 'quiz');
        $report = new \quiz_overview_report();
        $report->init('overview', 'quiz_overview_settings_form', $quiz, $cm, $course);
        $report->regrade_attempt($attempt);

        $this->assertEquals(2, $this->score($quiz, $student->id));
    }

    public function test_deleting_attempts_updates_score(): void {
        global $DB;
        $quiz = $this->make_frog_quiz(2);
        $this->enable($quiz, [0 => 0, 1 => 1, 2 => 2]);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $best = $this->attempt($quiz, $student, $this->frogs(2, 2));
        $other = $this->attempt($quiz, $student, $this->frogs(1, 2));
        $this->assertEquals(2, $this->score($quiz, $student->id));

        quiz_delete_attempt($best, $quiz);
        $this->assertEquals(1, $this->score($quiz, $student->id));
        $this->assertFalse($DB->record_exists('local_bandedgrade_attempt', ['attemptid' => $best->id]));

        quiz_delete_attempt($other, $quiz);
        $this->assertNull($this->score($quiz, $student->id), 'No attempts left: the score is blank.');
    }

    public function test_essay_marked_by_hand_fills_the_score(): void {
        $quiz = $this->make_quiz();
        $this->add_question($quiz, 'shortanswer', 'frogtoad', 1.0);
        $this->add_question($quiz, 'essay', 'plain', 5.0);
        $this->enable($quiz, [0 => 0, 1 => 1, 2 => 2]);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $attempt = $this->attempt($quiz, $student, [1 => 'frog', 2 => 'My essay.']);
        $this->assertNull($this->score($quiz, $student->id), 'Blank until the essay is marked.');

        // What mod/quiz/comment.php does: save the mark, then fire the event.
        $quba = \question_engine::load_questions_usage_by_activity($attempt->uniqueid);
        $quba->manual_grade(2, 'Good', 5, FORMAT_HTML);
        \question_engine::save_questions_usage_by_activity($quba);
        \mod_quiz\event\question_manually_graded::create([
            'objectid' => $quba->get_question_attempt(2)->get_question_id(),
            'courseid' => $this->course->id,
            'context' => \context_module::instance($quiz->cmid),
            'other' => ['quizid' => $quiz->id, 'attemptid' => $attempt->id, 'slot' => 2],
        ])->trigger();

        $this->assertEquals(2, $this->score($quiz, $student->id));
    }

    public function test_changing_bands_rescores_everyone(): void {
        $quiz = $this->make_frog_quiz(4);
        $this->enable($quiz, [0 => 0, 2 => 1, 4 => 2]);
        $one = $this->getDataGenerator()->create_and_enrol($this->course);
        $two = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->attempt($quiz, $one, $this->frogs(1, 4));
        $this->attempt($quiz, $two, $this->frogs(3, 4));
        $this->run_tasks();
        $this->assertEquals(0, $this->score($quiz, $one->id));
        $this->assertEquals(1, $this->score($quiz, $two->id));

        $this->enable($quiz, [0 => 0, 1 => 5, 3 => 10]);
        $this->assertSame(1, $this->queued());
        $this->run_tasks();

        $this->assertEquals(5, $this->score($quiz, $one->id));
        $this->assertEquals(10, $this->score($quiz, $two->id));
        $this->assertEquals(10, $this->item($quiz)->grademax);
    }

    public function test_question_changes_queue_a_rescore(): void {
        global $DB;
        $quiz = $this->make_frog_quiz(2);
        $this->enable($quiz, [0 => 0, 1 => 1]);
        $this->run_tasks();

        $this->add_question($quiz);
        $this->assertSame(1, $this->queued(), 'Adding a question queues a rescore.');
        $this->run_tasks();

        // A mark change from 1 to 2 does not change what counts; 1 to 0 does.
        $structure = \mod_quiz\quiz_settings::create($quiz->id)->get_structure();
        $slotid = $DB->get_field('quiz_slots', 'id', ['quizid' => $quiz->id, 'slot' => 1]);
        $structure->update_slot_maxmark($structure->get_slot_by_id($slotid), 2);
        $this->assertSame(0, $this->queued());
        $structure->update_slot_maxmark($structure->get_slot_by_id($slotid), 0);
        $this->assertSame(1, $this->queued());
    }

    public function test_off_quiz_queues_nothing(): void {
        $quiz = $this->make_frog_quiz(1);
        $this->add_question($quiz);
        $this->assertSame(0, $this->queued());
    }

    public function test_deleting_the_quiz_removes_item_and_data(): void {
        global $DB;
        $quiz = $this->make_frog_quiz(2);
        $this->enable($quiz, [0 => 0, 1 => 1]);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->attempt($quiz, $student, $this->frogs(1, 2));
        $itemid = quiz_config::get($quiz->id)->gradeitemid;

        course_delete_module($quiz->cmid);

        $this->assertFalse($DB->record_exists('grade_items', ['id' => $itemid]));
        foreach (['local_bandedgrade_quiz', 'local_bandedgrade_attempt', 'local_bandedgrade_written'] as $table) {
            $this->assertSame(0, $DB->count_records($table), $table);
        }
    }

    public function test_course_reset_forgets_counts_and_scores(): void {
        global $DB;
        $quiz = $this->make_frog_quiz(2);
        $this->enable($quiz, [0 => 0, 1 => 1]);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->attempt($quiz, $student, $this->frogs(1, 2));

        reset_course_userdata((object)['id' => $this->course->id, 'reset_quiz_attempts' => 1,
            'reset_gradebook_grades' => 1]);

        $this->assertSame(0, $DB->count_records('local_bandedgrade_attempt'));
        $this->assertSame(0, $DB->count_records('local_bandedgrade_written'));
        $this->assertEquals(1, quiz_config::get($quiz->id)->enabled, 'The settings stay.');

        // A new attempt after the reset is scored normally.
        $this->attempt($quiz, $student, $this->frogs(2, 2));
        $this->assertEquals(1, $this->score($quiz, $student->id));
    }

    public function test_reset_of_gradebook_items_recreates_our_item(): void {
        $quiz = $this->make_frog_quiz(2);
        $this->enable($quiz, [0 => 0, 1 => 1]);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $olditemid = quiz_config::get($quiz->id)->gradeitemid;

        reset_course_userdata((object)['id' => $this->course->id, 'reset_gradebook_items' => 1]);
        $newitemid = quiz_config::get($quiz->id)->gradeitemid;
        $this->assertNotNull($newitemid);
        $this->assertNotEquals($olditemid, $newitemid);

        $this->attempt($quiz, $student, $this->frogs(1, 2));
        $this->assertEquals(1, $this->score($quiz, $student->id));
    }
}
