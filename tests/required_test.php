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

use local_bandedgrade\local\counter;
use local_bandedgrade\local\form_section;
use local_bandedgrade\local\quiz_config;
use local_bandedgrade\local\report;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/lib.php');

/**
 * Tests for questions that must be correct: missing one gives the lowest score.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(counter::class)]
#[CoversClass(form_section::class)]
#[CoversClass(quiz_config::class)]
final class required_test extends \advanced_testcase {
    use \local_bandedgrade\tests\quiz_trait;

    /**
     * Answers for a 4-question frog quiz: right on the given slots, wrong on the others.
     *
     * @param int[] $rightslots Slots answered right.
     * @return array Responses.
     */
    private function answers(array $rightslots): array {
        $responses = [];
        for ($slot = 1; $slot <= 4; $slot++) {
            $responses[$slot] = in_array($slot, $rightslots) ? 'frog' : 'cat';
        }
        return $responses;
    }

    /**
     * A 4-question quiz with bands 0 → 0, 2 → 1, 4 → 2 where question 1 must be correct.
     *
     * @param array $options Quiz fields.
     * @return \stdClass The quiz.
     */
    private function quiz_with_required_first(array $options = []): \stdClass {
        $this->setup_course();
        $quiz = $this->make_frog_quiz(4, $options);
        $this->enable($quiz, [0 => 0, 2 => 1, 4 => 2], true, true, 'bands', [1]);
        return $quiz;
    }

    public function test_missing_a_required_question_gives_the_lowest_score(): void {
        $quiz = $this->quiz_with_required_first();
        $this->assertSame([$this->slot_id($quiz, 1)], quiz_config::get($quiz->id)->requiredslots);
        $got = $this->getDataGenerator()->create_and_enrol($this->course);
        $missed = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->attempt($quiz, $got, $this->answers([1, 2]));
        $this->attempt($quiz, $missed, $this->answers([2, 3, 4]));
        $this->assertEquals(1, $this->score($quiz, $got->id), '2 correct, including question 1.');
        $this->assertEquals(0, $this->score($quiz, $missed->id), '3 correct but not question 1: lowest band.');
    }

    public function test_the_count_is_still_stored_and_the_miss_is_flagged(): void {
        global $DB;
        $quiz = $this->quiz_with_required_first();
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $attempt = $this->attempt($quiz, $student, $this->answers([2, 3, 4]));
        $row = $DB->get_record('local_bandedgrade_attempt', ['attemptid' => $attempt->id], '*', MUST_EXIST);
        $this->assertEquals(3, $row->correctcount);
        $this->assertEquals(1, $row->missedrequired);

        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        [$report] = report::rows($quiz, \cm_info::create($cm), \context_module::instance($cm->id), 0);
        $this->assertSame([3], array_column($report->attempts, 'correct'));
        $this->assertTrue($report->attempts[0]->missed);
        $this->assertEquals(0, $report->used, 'The score comes from the lowest band, not from the 3.');
    }

    public function test_highest_attempt_can_make_up_for_a_miss(): void {
        $quiz = $this->quiz_with_required_first(['grademethod' => QUIZ_GRADEHIGHEST, 'attempts' => 0]);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->attempt($quiz, $student, $this->answers([2, 3, 4]));
        $this->assertEquals(0, $this->score($quiz, $student->id));
        $this->attempt($quiz, $student, $this->answers([1, 2]));
        $this->assertEquals(1, $this->score($quiz, $student->id), 'The second attempt has question 1 and 2 correct.');
    }

    public function test_average_counts_a_missed_attempt_as_zero(): void {
        $quiz = $this->quiz_with_required_first(['grademethod' => QUIZ_GRADEAVERAGE, 'attempts' => 0]);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->attempt($quiz, $student, $this->answers([2, 3, 4]));
        $this->attempt($quiz, $student, $this->answers([1, 2, 3, 4]));
        $this->assertEquals(1, $this->score($quiz, $student->id), '(0 + 4) / 2 = 2 correct: band 1.');
    }

    public function test_first_and_last_attempt(): void {
        global $DB;
        $quiz = $this->quiz_with_required_first(['grademethod' => QUIZ_ATTEMPTFIRST, 'attempts' => 0]);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->attempt($quiz, $student, $this->answers([2, 3, 4]));
        $this->attempt($quiz, $student, $this->answers([1, 2, 3, 4]));
        $this->assertEquals(0, $this->score($quiz, $student->id), 'First attempt missed question 1.');

        $DB->set_field('quiz', 'grademethod', QUIZ_ATTEMPTLAST, ['id' => $quiz->id]);
        $this->enable($quiz, [0 => 0, 2 => 1, 4 => 2], true, true, 'bands', [1]);
        $this->run_tasks();
        $this->assertEquals(2, $this->score($quiz, $student->id), 'Last attempt has everything.');
    }

    public function test_works_with_pass_fail(): void {
        $this->setup_course();
        $quiz = $this->make_frog_quiz(4);
        $this->enable_passfail($quiz, '2', '5', '1', 'bands', [1]);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->attempt($quiz, $student, $this->answers([2, 3, 4]));
        $this->assertEquals(1, $this->score($quiz, $student->id), 'Enough correct, but question 1 missed: the fail score.');
    }

    public function test_works_with_percentages(): void {
        $this->setup_course();
        $quiz = $this->make_frog_quiz(4);
        $this->enable($quiz, [0 => 0, 50 => 1, 100 => 2], true, true, 'percent', [1]);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->attempt($quiz, $student, $this->answers([2, 3, 4]));
        $this->assertEquals(0, $this->score($quiz, $student->id), '75% but question 1 missed.');
    }

    public function test_changing_the_required_questions_rescores(): void {
        $quiz = $this->quiz_with_required_first();
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->attempt($quiz, $student, $this->answers([2, 3, 4]));
        $this->assertEquals(0, $this->score($quiz, $student->id));

        $this->enable($quiz, [0 => 0, 2 => 1, 4 => 2], true, true, 'bands', []);
        $this->run_tasks();
        $this->assertSame([], quiz_config::get($quiz->id)->requiredslots);
        $this->assertEquals(1, $this->score($quiz, $student->id), 'Nothing required now: 3 correct is band 1.');

        $this->enable($quiz, [0 => 0, 2 => 1, 4 => 2], true, true, 'bands', [4]);
        $this->run_tasks();
        $this->assertEquals(1, $this->score($quiz, $student->id), 'Question 4 was right.');
    }

    public function test_saving_without_the_box_keeps_the_required_questions(): void {
        $quiz = $this->quiz_with_required_first();
        // A save that does not post the box (for example the section turned off) leaves the list alone.
        $this->enable($quiz, [0 => 0, 2 => 1, 4 => 2], true, false);
        $config = quiz_config::get($quiz->id);
        $this->assertEquals(0, $config->enabled);
        $this->assertSame([$this->slot_id($quiz, 1)], $config->requiredslots);
    }

    public function test_only_counted_questions_of_this_quiz_can_be_required(): void {
        $this->setup_course();
        $quiz = $this->make_frog_quiz(2);
        $this->add_question($quiz, 'shortanswer', 'frogtoad', 0.0);
        $other = $this->make_frog_quiz(1);
        $options = form_section::slot_options($quiz->id);
        $this->assertSame([$this->slot_id($quiz, 1), $this->slot_id($quiz, 2)], array_keys($options));

        $moduleinfo = (object)['modulename' => 'quiz', 'instance' => $quiz->id, 'bandedgrade_enabled' => 1,
            'bandedgrade_preset' => 'zerotothree10', 'bandedgrade_zeroweight' => 1,
            'bandedgrade_required' => [$this->slot_id($quiz, 2), $this->slot_id($quiz, 3), $this->slot_id($other, 1), 99999]];
        form_section::save($moduleinfo, $this->course);
        $this->assertSame([$this->slot_id($quiz, 2)], quiz_config::get($quiz->id)->requiredslots);
    }

    public function test_a_deleted_question_is_no_longer_required(): void {
        $this->setup_course();
        $quiz = $this->make_frog_quiz(3);
        $this->enable($quiz, [0 => 0, 1 => 1], true, true, 'bands', [1, 3]);
        $ids = quiz_config::get($quiz->id)->requiredslots;
        $this->assertSame([1, 3], counter::required_slot_numbers($quiz->id, $ids));

        $structure = \mod_quiz\structure::create_for_quiz(\mod_quiz\quiz_settings::create($quiz->id));
        $structure->remove_slot(1);
        $this->assertSame([2], counter::required_slot_numbers($quiz->id, $ids), 'Old slot 3 is now slot 2.');
    }

    public function test_slot_list_encoding(): void {
        $this->assertSame('[1,5,7]', quiz_config::encode_slots(['7', 1, 5, 5]));
        $this->assertSame([1, 5, 7], quiz_config::decode_slots('[7,"5",1,0,-2,5]'));
        $this->assertSame([], quiz_config::decode_slots(null));
        $this->assertSame([], quiz_config::decode_slots('nonsense'));
    }
}
