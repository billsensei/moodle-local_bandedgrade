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

use local_bandedgrade\local\scorer;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/lib.php');

/**
 * Tests for the scoring rules on real quizzes and attempts: what is written to the gradebook, and when it is kept.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(scorer::class)]
final class scorer_rescore_test extends \advanced_testcase {
    use \local_bandedgrade\tests\quiz_trait;

    public function test_rescore_user_writes_the_band(): void {
        $this->setup_course();
        $quiz = $this->make_frog_quiz(10);
        $this->enable($quiz, [0 => 0, 1 => 1, 5 => 2, 9 => 3]);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);

        $this->attempt($quiz, $student, $this->frogs(6, 10));

        $this->assertEquals(2, $this->score($quiz, $student->id), 'The observer scored the marked attempt.');
        $this->assertEquals(3, $this->item($quiz)->grademax);
    }

    public function test_rescore_quiz_in_several_chunks(): void {
        global $DB;
        $this->setup_course();
        $quiz = $this->make_frog_quiz(10);
        $this->enable($quiz, [0 => 0, 1 => 1, 5 => 2, 9 => 3]);
        $rights = [0, 3, 5, 9, 10];
        $students = [];
        foreach ($rights as $right) {
            $students[$right] = $this->getDataGenerator()->create_and_enrol($this->course);
            $this->attempt($quiz, $students[$right], $this->frogs($right, 10));
        }
        $bystudent = fn($right) => $this->score($quiz, $students[$right]->id);
        $this->assertEquals([0, 1, 2, 3, 3], array_map($bystudent, $rights));

        // Lose all counts and add a count row for a student who has no attempt.
        $ghost = $this->getDataGenerator()->create_and_enrol($this->course);
        $DB->delete_records('local_bandedgrade_attempt', ['quizid' => $quiz->id]);
        $DB->insert_record('local_bandedgrade_attempt', ['attemptid' => 0, 'quizid' => $quiz->id, 'userid' => $ghost->id,
            'correctcount' => 4, 'missedrequired' => 0, 'pending' => 0, 'timemodified' => time()]);

        $original = scorer::$chunksize;
        scorer::$chunksize = 2; // 5 students and the ghost: three chunks.
        try {
            $results = scorer::rescore_quiz($quiz->id);
        } finally {
            scorer::$chunksize = $original;
        }

        $this->assertSame(6, array_sum($results), 'Every student is handled once.');
        $this->assertEquals([0, 1, 2, 3, 3], array_map($bystudent, $rights));
        $stored = $DB->get_records_menu('local_bandedgrade_attempt', ['quizid' => $quiz->id], 'correctcount', 'id, correctcount');
        $this->assertEquals($rights, array_values($stored));
        $this->assertFalse($DB->record_exists('local_bandedgrade_attempt', ['quizid' => $quiz->id, 'userid' => $ghost->id]));
    }

    public function test_grading_methods_on_real_attempts(): void {
        $this->setup_course();
        $bands = [0 => 0, 1 => 1, 3 => 2, 5 => 3];
        $expected = [QUIZ_GRADEHIGHEST => 3, QUIZ_GRADEAVERAGE => 2, QUIZ_ATTEMPTFIRST => 1, QUIZ_ATTEMPTLAST => 2];
        foreach ($expected as $method => $score) {
            $quiz = $this->make_frog_quiz(5, ['grademethod' => $method]);
            $this->enable($quiz, $bands);
            $student = $this->getDataGenerator()->create_and_enrol($this->course);
            // Counts 1, 5, 4: highest 5, first 1, last 4, average 3.33 (between the bands starting at 3 and 5).
            foreach ([1, 5, 4] as $right) {
                $this->attempt($quiz, $student, $this->frogs($right, 5));
            }
            $this->assertEquals($score, $this->score($quiz, $student->id), "Grading method $method");
        }
    }

    public function test_change_by_hand_is_kept_until_overwrite(): void {
        $this->setup_course();
        $quiz = $this->make_frog_quiz(4);
        $this->enable($quiz, [0 => 0, 2 => 1, 4 => 2]);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->attempt($quiz, $student, $this->frogs(2, 4));
        $this->assertEquals(1, $this->score($quiz, $student->id));

        // The teacher changes it in the gradebook.
        $this->item($quiz)->update_final_grade($student->id, 1.5, 'gradebook');
        $this->assertSame([$student->id], array_map('strval', scorer::changed_by_hand($quiz->id)));

        // A better attempt does not replace the teacher's score...
        $this->attempt($quiz, $student, $this->frogs(4, 4));
        $this->assertEquals(1.5, $this->score($quiz, $student->id));
        $this->assertSame(scorer::RESULT_KEPT, scorer::rescore_user($quiz->id, $student->id));
        $this->assertSame([scorer::RESULT_KEPT => 1], scorer::rescore_quiz($quiz->id));

        // ...until the teacher chooses to overwrite.
        $this->assertSame(scorer::RESULT_WRITTEN, scorer::rescore_user($quiz->id, $student->id, true));
        $this->assertEquals(2, $this->score($quiz, $student->id));
        $this->assertSame([], scorer::changed_by_hand($quiz->id));
    }

    public function test_score_entered_before_any_attempt_is_kept(): void {
        $this->setup_course();
        $quiz = $this->make_frog_quiz(2);
        $this->enable($quiz, [0 => 0, 1 => 1]);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->item($quiz)->update_final_grade($student->id, 0.5, 'gradebook');

        $this->attempt($quiz, $student, $this->frogs(2, 2));

        $this->assertEquals(0.5, $this->score($quiz, $student->id));
    }

    public function test_locked_grade_is_never_changed(): void {
        $this->setup_course();
        $quiz = $this->make_frog_quiz(2);
        $this->enable($quiz, [0 => 0, 1 => 1, 2 => 2]);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->attempt($quiz, $student, $this->frogs(1, 2));
        $this->item($quiz)->set_locked(true);

        $this->attempt($quiz, $student, $this->frogs(2, 2));

        $this->assertEquals(1, $this->score($quiz, $student->id));
        $this->assertSame(scorer::RESULT_LOCKED, scorer::rescore_user($quiz->id, $student->id, true));
    }

    public function test_preview_attempts_are_ignored(): void {
        global $DB;
        $this->setup_course();
        $quiz = $this->make_frog_quiz(2);
        $this->enable($quiz, [0 => 0, 1 => 1]);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $attempt = $this->attempt($quiz, $student, $this->frogs(2, 2));
        $DB->set_field('quiz_attempts', 'preview', 1, ['id' => $attempt->id]);

        scorer::rescore_quiz($quiz->id);

        $this->assertNull($this->score($quiz, $student->id));
    }

    public function test_off_does_nothing(): void {
        $this->setup_course();
        $quiz = $this->make_frog_quiz(2);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->attempt($quiz, $student, $this->frogs(2, 2));

        $this->assertNull(scorer::rescore_user($quiz->id, $student->id));
        $this->assertSame([], scorer::rescore_quiz($quiz->id));
        $this->assertNull($this->item($quiz));
    }
}
