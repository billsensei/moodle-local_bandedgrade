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

use local_bandedgrade\local\gradebook;
use local_bandedgrade\local\recalculate;
use local_bandedgrade\local\scorer;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the recalculate request and the quiz weight.
 *
 * The page itself (capability, sesskey, confirmation) is covered by tests/behat/bandedgrade.feature.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(recalculate::class)]
#[CoversClass(gradebook::class)]
#[CoversClass(event\scores_recalculated::class)]
final class recalculate_test extends \advanced_testcase {
    use \local_bandedgrade\tests\quiz_trait;

    protected function setUp(): void {
        parent::setUp();
        $this->setup_course();
    }

    public function test_request_keep_and_overwrite(): void {
        $quiz = $this->make_frog_quiz(2);
        $this->enable($quiz, [0 => 0, 1 => 1, 2 => 2]);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->attempt($quiz, $student, $this->frogs(2, 2));
        $this->run_tasks();
        $this->item($quiz)->update_final_grade($student->id, 0.5, 'gradebook');
        $this->assertSame([fullname($student)], recalculate::changed_by_hand_names($quiz->id));
        $context = \context_module::instance($quiz->cmid);

        $sink = $this->redirectEvents();
        recalculate::request($quiz, $context, false);
        $events = $sink->get_events();
        $this->assertInstanceOf(event\scores_recalculated::class, $events[0]);
        $this->assertSame(0, $events[0]->other['overwrite']);
        $this->assertEquals($quiz->id, $events[0]->objectid);
        $sink->close();
        $this->run_tasks();
        $this->assertEquals(0.5, $this->score($quiz, $student->id), 'Keep: the change by hand stays.');

        $sink = $this->redirectEvents();
        recalculate::request($quiz, $context, true);
        $this->assertSame(1, $sink->get_events()[0]->other['overwrite']);
        $sink->close();
        $this->run_tasks();
        $this->assertEquals(2, $this->score($quiz, $student->id), 'Overwrite: back to the band score.');
        $this->assertSame([], scorer::changed_by_hand($quiz->id));
    }

    public function test_weight_natural(): void {
        $quiz = $this->make_frog_quiz(1);
        $this->enable($quiz, [0 => 0, 1 => 1]);
        $quizitem = gradebook::quiz_item($quiz);
        $this->assertEquals(1, $quizitem->weightoverride);
        $this->assertEquals(0, $quizitem->aggregationcoef2);

        $this->enable($quiz, [0 => 0, 1 => 1], false);
        $this->assertEquals(0, gradebook::quiz_item($quiz)->weightoverride, 'Turning it off undoes the weight 0.');
    }

    public function test_weight_left_alone_when_teacher_set_their_own(): void {
        $quiz = $this->make_frog_quiz(1);
        $quizitem = gradebook::quiz_item($quiz);
        $quizitem->weightoverride = 1;
        $quizitem->aggregationcoef2 = 0.4;
        $quizitem->update();

        $this->assertSame(gradebook::WEIGHT_DONE, gradebook::set_quiz_weight($quiz, false));
        $this->assertEquals(0.4, gradebook::quiz_item($quiz)->aggregationcoef2);
    }

    public function test_weight_weighted_mean_and_unsupported(): void {
        $quiz = $this->make_frog_quiz(1);
        $category = gradebook::quiz_item($quiz)->get_parent_category();
        $category->aggregation = GRADE_AGGREGATE_WEIGHTED_MEAN;
        $category->update();
        $this->assertSame(gradebook::WEIGHT_DONE, gradebook::set_quiz_weight($quiz, true));
        $this->assertEquals(0, gradebook::quiz_item($quiz)->aggregationcoef);
        gradebook::set_quiz_weight($quiz, false);
        $this->assertEquals(1, gradebook::quiz_item($quiz)->aggregationcoef);

        $category->aggregation = GRADE_AGGREGATE_MEAN;
        $category->update();
        $this->assertSame(gradebook::WEIGHT_UNSUPPORTED, gradebook::set_quiz_weight($quiz, true));
    }

    public function test_two_quizzes_with_the_same_name_get_their_own_columns(): void {
        $first = $this->make_frog_quiz(1, ['name' => 'Weekly quiz']);
        $second = $this->make_frog_quiz(1, ['name' => 'Weekly quiz']);
        $this->enable($first, [0 => 0, 1 => 1]);
        $this->enable($second, [0 => 0, 1 => 1]);

        $this->assertNotEquals($this->item($first)->id, $this->item($second)->id);
    }

    public function test_item_follows_quiz_name_and_top_score(): void {
        global $DB;
        $quiz = $this->make_frog_quiz(1, ['name' => 'Week 1']);
        $this->enable($quiz, [0 => 0, 1 => 4]);
        $this->assertSame('Week 1 – score', $this->item($quiz)->itemname);
        $this->assertEquals(4, $this->item($quiz)->grademax);

        $DB->set_field('quiz', 'name', 'Week one', ['id' => $quiz->id]);
        $quiz->name = 'Week one';
        $this->enable($quiz, [0 => 0, 1 => 2.5]);
        $this->assertSame('Week one – score', $this->item($quiz)->itemname);
        $this->assertEquals(2.5, $this->item($quiz)->grademax);
    }
}
