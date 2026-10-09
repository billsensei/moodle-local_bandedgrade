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
use local_bandedgrade\local\form_section;
use local_bandedgrade\local\gradebook;
use local_bandedgrade\local\quiz_config;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/lib.php');

/**
 * Tests for giving scores as the words of a gradebook scale.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(bands::class)]
#[CoversClass(form_section::class)]
#[CoversClass(gradebook::class)]
final class scale_test extends \advanced_testcase {
    use \local_bandedgrade\tests\quiz_trait;

    /**
     * Make a course scale "Fail, Pass, Merit, Distinction".
     *
     * @param int|null $courseid The course, or 0 for a site scale (default: the test course).
     * @return \stdClass The scale.
     */
    private function make_scale(?int $courseid = null): \stdClass {
        return $this->getDataGenerator()->create_scale([
            'name' => 'Result',
            'scale' => 'Fail, Pass, Merit, Distinction',
            'courseid' => $courseid ?? $this->course->id,
        ]);
    }

    public function test_scale_position(): void {
        $items = ['Fail', 'Pass', 'Merit'];
        $this->assertSame(2, bands::scale_position('Pass', $items));
        $this->assertSame(2, bands::scale_position(' pass ', $items), 'Capitals and spaces do not matter.');
        $this->assertSame(3, bands::scale_position('3', $items));
        $this->assertSame(3, bands::scale_position('3.0', $items));
        $this->assertNull(bands::scale_position('0', $items));
        $this->assertNull(bands::scale_position('4', $items));
        $this->assertNull(bands::scale_position('Distinction', $items));
        $this->assertNull(bands::scale_position('', $items));
    }

    public function test_from_rows_with_a_scale(): void {
        $items = ['Fail', 'Pass', 'Merit'];
        [$result, $errors] = bands::from_rows(['0', '2', '4'], ['Fail', 'pass', '3'], bands::TYPE_COUNT, $items);
        $this->assertSame([], $errors);
        $this->assertSame([1.0, 2.0, 3.0], array_column($result, 'score'));

        [, $errors] = bands::from_rows(['0', '2'], ['Fail', 'Excellent'], bands::TYPE_COUNT, $items);
        $this->assertStringContainsString('Fail, Pass, Merit', $errors[1]);
        [, $errors] = bands::from_rows(['0', '2'], ['0', '1'], bands::TYPE_COUNT, $items);
        $this->assertArrayHasKey(0, $errors, 'A scale has no position 0.');
    }

    public function test_from_passfail_with_a_scale(): void {
        $items = ['Fail', 'Pass'];
        [$result, $errors] = bands::from_passfail('3', 'Pass', 'Fail', bands::TYPE_COUNT, $items);
        $this->assertSame([], $errors);
        $this->assertSame([['from' => 0, 'score' => 1.0], ['from' => 3, 'score' => 2.0]], $result);
        [, $errors] = bands::from_passfail('3', 'Merit', '0', bands::TYPE_COUNT, $items);
        $this->assertSame(['pass', 'fail'], array_keys($errors));
    }

    public function test_students_get_the_word_of_the_scale(): void {
        $this->setup_course();
        $scale = $this->make_scale();
        $quiz = $this->make_frog_quiz(4);
        $this->enable($quiz, [0 => 'Fail', 2 => 'Pass', 4 => 'Distinction'], true, true, 'bands', null, (int)$scale->id);

        $config = quiz_config::get($quiz->id);
        $this->assertEquals($scale->id, $config->scaleid);
        $item = $this->item($quiz);
        $this->assertEquals(GRADE_TYPE_SCALE, $item->gradetype);
        $this->assertEquals($scale->id, $item->scaleid);
        $this->assertEquals(1, $item->grademin);
        $this->assertEquals(4, $item->grademax, 'The scale has 4 words.');

        $pass = $this->getDataGenerator()->create_and_enrol($this->course);
        $top = $this->getDataGenerator()->create_and_enrol($this->course);
        $fail = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->attempt($quiz, $pass, $this->frogs(3, 4));
        $this->attempt($quiz, $top, $this->frogs(4, 4));
        $this->attempt($quiz, $fail, $this->frogs(1, 4));
        $this->assertEquals(2, $this->score($quiz, $pass->id));
        $this->assertEquals(4, $this->score($quiz, $top->id));
        $this->assertEquals(1, $this->score($quiz, $fail->id));
        $this->assertSame('Pass', gradebook::format_score($item, 2.0));
        $this->assertSame('Distinction', gradebook::format_score($item, 4.0));
    }

    public function test_switching_between_numbers_and_a_scale(): void {
        $this->setup_course();
        $scale = $this->make_scale();
        $quiz = $this->make_frog_quiz(4);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->enable($quiz, [0 => 0, 2 => 5, 4 => 10]);
        $this->attempt($quiz, $student, $this->frogs(3, 4));
        $this->assertEquals(5, $this->score($quiz, $student->id));
        $this->assertEquals(GRADE_TYPE_VALUE, $this->item($quiz)->gradetype);

        $this->enable($quiz, [0 => 'Fail', 2 => 'Merit'], true, true, 'bands', null, (int)$scale->id);
        $this->run_tasks();
        $this->assertEquals(GRADE_TYPE_SCALE, $this->item($quiz)->gradetype);
        $this->assertEquals(3, $this->score($quiz, $student->id), 'Merit is the third word.');

        $this->enable($quiz, [0 => 0, 2 => 5, 4 => 10]);
        $this->run_tasks();
        $item = $this->item($quiz);
        $this->assertEquals(GRADE_TYPE_VALUE, $item->gradetype);
        $this->assertEmpty($item->scaleid);
        $this->assertEquals(10, $item->grademax);
        $this->assertEquals(0, $item->grademin);
        $this->assertEquals(5, $this->score($quiz, $student->id));
    }

    public function test_pass_fail_with_a_scale_sets_the_grade_to_pass(): void {
        $this->setup_course();
        $scale = $this->make_scale();
        $quiz = $this->make_frog_quiz(4);
        $this->enable_passfail($quiz, '3', 'Merit', 'Fail', 'bands', null, (int)$scale->id);
        $this->assertEquals(3, $this->item($quiz)->gradepass, 'Merit is the third word.');
        $pass = $this->getDataGenerator()->create_and_enrol($this->course);
        $fail = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->attempt($quiz, $pass, $this->frogs(3, 4));
        $this->attempt($quiz, $fail, $this->frogs(2, 4));
        $this->assertEquals(3, $this->score($quiz, $pass->id));
        $this->assertEquals(1, $this->score($quiz, $fail->id));
    }

    public function test_a_site_scale_can_be_used(): void {
        $this->setup_course();
        $scale = $this->make_scale(0);
        $quiz = $this->make_frog_quiz(2);
        $this->enable($quiz, [0 => 'Fail', 1 => 'Pass'], true, true, 'bands', null, (int)$scale->id);
        $this->assertEquals($scale->id, quiz_config::get($quiz->id)->scaleid);
    }

    public function test_the_form_refuses_a_preset_or_a_scale_of_another_course(): void {
        $this->setup_course();
        $scale = $this->make_scale();
        $other = $this->getDataGenerator()->create_course();
        $foreign = $this->make_scale((int)$other->id);
        $formwrapper = $this->createStub(\moodleform_mod::class);
        $formwrapper->method('get_current')->willReturn((object)['modulename' => 'quiz']);
        $formwrapper->method('get_course')->willReturn($this->course);

        $data = ['bandedgrade_enabled' => 1, 'bandedgrade_scale' => $scale->id, 'bandedgrade_preset' => 'zerotothree10'];
        $this->assertSame(['bandedgrade_preset'], array_keys(form_section::validate($formwrapper, $data)));

        $data = ['bandedgrade_enabled' => 1, 'bandedgrade_scale' => $foreign->id, 'bandedgrade_preset' => ''];
        $this->assertSame(['bandedgrade_scale'], array_keys(form_section::validate($formwrapper, $data)));

        $data = ['bandedgrade_enabled' => 1, 'bandedgrade_scale' => $scale->id, 'bandedgrade_preset' => '',
            'bandedgrade_from' => ['0', '2'], 'bandedgrade_score' => ['Fail', 'Pass']];
        $this->assertSame([], form_section::validate($formwrapper, $data));
    }

    public function test_scale_options_and_preview_words(): void {
        $this->setup_course();
        $scale = $this->make_scale();
        $options = form_section::scale_options((int)$this->course->id);
        $this->assertSame(['Fail', 'Pass', 'Merit', 'Distinction'], $options[(int)$scale->id]['items']);
        $this->assertStringContainsString('Result (Fail, Pass, Merit, Distinction)', $options[(int)$scale->id]['label']);

        $html = form_section::preview_html(
            [['from' => 0, 'score' => 1], ['from' => 5, 'score' => 3]],
            10,
            'bands',
            $options[(int)$scale->id]['items']
        );
        $this->assertStringContainsString('0 to 4 correct → score Fail', $html);
        $this->assertStringContainsString('5 to 10 correct → score Merit', $html);
    }

    public function test_a_scale_that_was_deleted_leaves_numeric_scores(): void {
        $this->setup_course();
        $this->assertNull(gradebook::scale_items(null, (int)$this->course->id));
        $this->assertNull(gradebook::scale_items(999999, (int)$this->course->id));
    }

    public function test_words_of_a_scale_are_escaped_in_html(): void {
        $this->setup_course();
        $evil = '</option><script>alert(1)</script>';
        $scale = $this->getDataGenerator()->create_scale(['name' => 'Evil <b>', 'scale' => "Fail, $evil, Merit",
            'courseid' => $this->course->id]);
        $options = form_section::scale_options((int)$this->course->id);
        $items = $options[(int)$scale->id]['items'];
        $this->assertStringNotContainsString('<script>', $options[(int)$scale->id]['label']);

        $html = form_section::preview_html([['from' => 0, 'score' => 1], ['from' => 2, 'score' => 2]], 4, 'bands', $items);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);

        [, $errors] = bands::from_rows(['0'], ['Nonsense'], bands::TYPE_COUNT, $items);
        $this->assertStringNotContainsString('<script>', $errors[0]);
        $this->assertStringNotContainsString('<script>', gradebook::format_score($this->item_for_scale($scale), 2.0));
    }

    /**
     * A scale grade item (not saved) for formatting tests.
     *
     * @param \stdClass $scale The scale.
     * @return \grade_item The item.
     */
    private function item_for_scale(\stdClass $scale): \grade_item {
        return new \grade_item(['courseid' => $this->course->id, 'itemtype' => 'manual', 'itemname' => 'x',
            'gradetype' => GRADE_TYPE_SCALE, 'scaleid' => $scale->id], false);
    }
}
