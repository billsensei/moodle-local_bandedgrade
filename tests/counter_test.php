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
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for counting fully correct questions.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(counter::class)]
final class counter_test extends \advanced_testcase {
    use \local_bandedgrade\tests\quiz_trait;

    protected function setUp(): void {
        parent::setUp();
        $this->setup_course();
    }

    /**
     * Count one attempt.
     *
     * @param \stdClass $attempt The attempt.
     * @return \stdClass Count (correct, pending).
     */
    private function count_one(\stdClass $attempt): \stdClass {
        return counter::count_attempts([$attempt->id => $attempt])[$attempt->id];
    }

    public function test_mixed_weights_partial_credit_and_description(): void {
        $quiz = $this->make_quiz();
        $this->add_question($quiz, 'shortanswer', 'frogtoad', 1.0);
        $this->add_question($quiz, 'shortanswer', 'frogtoad', 3.0);
        $this->add_question($quiz, 'description', 'info', 0.0);
        $this->add_question($quiz, 'shortanswer', 'frogtoad', 0.5);
        $this->add_question($quiz, 'shortanswer', 'frogtoad', 2.0);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);

        // Slot 1 right (1 mark), slot 2 partly right "toad" (80%), slot 4 right (0.5 mark), slot 5 wrong.
        $attempt = $this->attempt($quiz, $student, [1 => 'frog', 2 => 'toad', 4 => 'frog', 5 => 'cat']);

        $count = $this->count_one($attempt);
        $this->assertSame(2, $count->correct);
        $this->assertFalse($count->pending);
        $this->assertSame(4, counter::question_total($quiz->id), 'The description item is not a question.');
    }

    public function test_zero_mark_question_is_not_counted(): void {
        $quiz = $this->make_quiz();
        $this->add_question($quiz, 'shortanswer', 'frogtoad', 1.0);
        $this->add_question($quiz, 'shortanswer', 'frogtoad', 0.0);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);

        $attempt = $this->attempt($quiz, $student, $this->frogs(2, 2));

        $this->assertSame(1, $this->count_one($attempt)->correct);
        $this->assertSame(1, counter::question_total($quiz->id));
    }

    public function test_multipart_question_counts_only_when_every_part_is_right(): void {
        $quiz = $this->make_quiz();
        $this->add_question($quiz, 'multianswer', 'twosubq', 2.0);
        $this->add_question($quiz, 'multianswer', 'twosubq', 2.0);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);

        // The generator cannot simulate multi-part answers, so build the form data from the correct response.
        // Question 1: both parts right. Question 2: first part wrong ("Dog"), second part right.
        $this->setUser($student);
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $attempt = $quizgenerator->create_attempt($quiz->id, $student->id);
        $attemptobj = \mod_quiz\quiz_attempt::create($attempt->id);
        $postdata = [];
        foreach ([1 => 'Owl', 2 => 'Dog'] as $slot => $firstpart) {
            $qa = $attemptobj->get_question_attempt($slot);
            $response = $qa->get_question()->get_correct_response();
            $response['sub1_answer'] = $firstpart;
            $postdata[$qa->get_control_field_name('sequencecheck')] = (string)$qa->get_sequence_check_count();
            foreach ($response as $name => $value) {
                $postdata[$qa->get_qt_field_name($name)] = (string)$value;
            }
        }
        $attemptobj->process_submitted_actions(time(), false, $postdata);
        $attemptobj->process_submit(time(), false);
        $attemptobj->process_grade_submission(time());
        $this->setAdminUser();
        $attempt = $attemptobj->get_attempt();

        $this->assertSame(1, $this->count_one($attempt)->correct);
    }

    public function test_right_on_a_later_try_counts(): void {
        global $DB;
        $quiz = $this->make_quiz(['preferredbehaviour' => 'interactive']);
        $this->add_question($quiz, 'shortanswer', 'frogtoad', 1.0, ['penalty' => 0.3333333]);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');

        $this->setUser($student);
        $attempt = $quizgenerator->create_attempt($quiz->id, $student->id);
        $quizgenerator->submit_responses($attempt->id, [1 => 'cat'], true, false);
        $quizgenerator->submit_responses($attempt->id, [1 => 'frog'], true, true);
        $this->setAdminUser();
        $attempt = $DB->get_record('quiz_attempts', ['id' => $attempt->id]);

        $this->assertLessThan(1.0, (float)$attempt->sumgrades, 'The penalty lowered the mark.');
        $this->assertSame(1, $this->count_one($attempt)->correct);
    }

    public function test_essay_waits_for_marking(): void {
        $quiz = $this->make_quiz();
        $this->add_question($quiz, 'shortanswer', 'frogtoad', 1.0);
        $this->add_question($quiz, 'essay', 'plain', 5.0);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);

        $attempt = $this->attempt($quiz, $student, [1 => 'frog', 2 => 'My essay.']);
        $count = $this->count_one($attempt);
        $this->assertTrue($count->pending);
        $this->assertSame(1, $count->correct);

        $quba = \question_engine::load_questions_usage_by_activity($attempt->uniqueid);
        $quba->manual_grade(2, 'Good', 5, FORMAT_HTML);
        \question_engine::save_questions_usage_by_activity($quba);

        $count = $this->count_one($attempt);
        $this->assertFalse($count->pending);
        $this->assertSame(2, $count->correct);
    }

    public function test_count_usages_for_whole_quiz(): void {
        global $DB;
        $quiz = $this->make_frog_quiz(3);
        $one = $this->getDataGenerator()->create_and_enrol($this->course);
        $two = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->attempt($quiz, $one, $this->frogs(1, 3));
        $this->attempt($quiz, $two, $this->frogs(3, 3));
        $this->attempt($quiz, $two, $this->frogs(2, 3), false);

        $counts = counter::count_usages(counter::finished_attempts_of_quiz($quiz->id));

        $this->assertCount(2, $counts, 'The unfinished attempt is left out.');
        $finished = $DB->get_records_menu(
            'quiz_attempts',
            ['quiz' => $quiz->id, 'state' => 'finished'],
            '',
            'uniqueid, userid'
        );
        foreach ($counts as $usageid => $count) {
            $this->assertSame($finished[$usageid] == $one->id ? 1 : 3, $count->correct);
        }
    }
}
