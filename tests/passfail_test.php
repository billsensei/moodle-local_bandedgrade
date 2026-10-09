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
use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/lib.php');

/**
 * Tests for the pass/fail scheme: one pass mark, stored as two bands.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(bands::class)]
#[CoversClass(form_section::class)]
#[CoversClass(gradebook::class)]
final class passfail_test extends \advanced_testcase {
    use \local_bandedgrade\tests\quiz_trait;

    public function test_from_passfail_makes_two_bands(): void {
        [$bands, $errors] = bands::from_passfail('6', '1', '0');
        $this->assertSame([], $errors);
        $this->assertSame([['from' => 0, 'score' => 0.0], ['from' => 6, 'score' => 1.0]], $bands);

        [$bands, $errors] = bands::from_passfail('66,67', '2,5', '0.5', bands::TYPE_PERCENT);
        $this->assertSame([], $errors);
        $this->assertSame([['from' => 0, 'score' => 0.5], ['from' => 66.67, 'score' => 2.5]], $bands);
        $this->assertSame([66.67, 2.5, 0.5], bands::to_passfail($bands));
    }

    /**
     * Pass marks and scores that are refused, with the field that gets the error.
     *
     * @param string $mark Pass mark.
     * @param string $pass Pass score.
     * @param string $fail Fail score.
     * @param string $ruletype Rule type.
     * @param string $field The field that must get the error.
     */
    #[DataProvider('bad_input_provider')]
    public function test_from_passfail_errors(string $mark, string $pass, string $fail, string $ruletype, string $field): void {
        [$bands, $errors] = bands::from_passfail($mark, $pass, $fail, $ruletype);
        $this->assertSame([], $bands);
        $this->assertArrayHasKey($field, $errors);
    }

    /**
     * Pass marks and scores that must be refused.
     *
     * @return array[] Mark, pass, fail, rule type, field with the error.
     */
    public static function bad_input_provider(): array {
        return [
            'blank mark' => ['', '1', '0', 'bands', 'mark'],
            'mark 0 passes everyone' => ['0', '1', '0', 'bands', 'mark'],
            'percent 0 passes everyone' => ['0', '1', '0', 'percent', 'mark'],
            'part of a question' => ['2.5', '1', '0', 'bands', 'mark'],
            'over 100 percent' => ['101', '1', '0', 'percent', 'mark'],
            'negative' => ['-1', '1', '0', 'bands', 'mark'],
            'too many questions' => ['10001', '1', '0', 'bands', 'mark'],
            'pass score text' => ['5', 'yes', '0', 'bands', 'pass'],
            'fail score negative' => ['5', '1', '-1', 'bands', 'fail'],
            'score too big' => ['5', '100000', '0', 'bands', 'pass'],
            'both scores zero' => ['5', '0', '0', 'bands', 'pass'],
        ];
    }

    public function test_to_passfail_only_for_two_bands_from_zero(): void {
        $three = [['from' => 0, 'score' => 0], ['from' => 5, 'score' => 1], ['from' => 8, 'score' => 2]];
        $this->assertNull(bands::to_passfail($three));
        $this->assertNull(bands::to_passfail([['from' => 1, 'score' => 0], ['from' => 5, 'score' => 1]]));
        $this->assertNull(bands::to_passfail([]));
    }

    public function test_students_pass_or_fail(): void {
        $this->setup_course();
        $quiz = $this->make_frog_quiz(10);
        $this->enable_passfail($quiz, '6');
        $config = quiz_config::get($quiz->id);
        $this->assertSame('passfail', $config->scheme);
        $this->assertSame('bands', $config->ruletype);
        $this->assertEquals(1, $this->item($quiz)->grademax);

        $pass = $this->getDataGenerator()->create_and_enrol($this->course);
        $fail = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->attempt($quiz, $pass, $this->frogs(6, 10));
        $this->attempt($quiz, $fail, $this->frogs(5, 10));
        $this->assertEquals(1, $this->score($quiz, $pass->id));
        $this->assertEquals(0, $this->score($quiz, $fail->id));
    }

    public function test_pass_by_percentage_with_own_scores(): void {
        $this->setup_course();
        $quiz = $this->make_frog_quiz(4);
        $this->enable_passfail($quiz, '75', '10', '2', 'percent');
        $this->assertSame('percent', quiz_config::get($quiz->id)->ruletype);
        $this->assertEquals(10, $this->item($quiz)->grademax);

        $pass = $this->getDataGenerator()->create_and_enrol($this->course);
        $fail = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->attempt($quiz, $pass, $this->frogs(3, 4));
        $this->attempt($quiz, $fail, $this->frogs(2, 4));
        $this->assertEquals(10, $this->score($quiz, $pass->id));
        $this->assertEquals(2, $this->score($quiz, $fail->id));
    }

    public function test_grade_to_pass_is_set_and_follows_the_scheme(): void {
        $this->setup_course();
        $quiz = $this->make_frog_quiz(4);
        $this->enable_passfail($quiz, '2', '5', '1');
        $this->assertEquals(5, $this->item($quiz)->gradepass);

        // Another pass score moves the grade to pass.
        $this->enable_passfail($quiz, '2', '8', '1');
        $this->assertEquals(8, $this->item($quiz)->gradepass);

        // A pass that scores no more than a fail gives no grade to pass.
        $this->enable_passfail($quiz, '2', '1', '3');
        $this->assertEquals(0, $this->item($quiz)->gradepass);

        // Back to bands: the grade to pass we set is taken back.
        $this->enable_passfail($quiz, '2', '8', '1');
        $this->enable($quiz, [0 => 0, 2 => 1, 4 => 2]);
        $this->assertSame('bands', quiz_config::get($quiz->id)->scheme);
        $this->assertEquals(0, $this->item($quiz)->gradepass);
    }

    public function test_grade_to_pass_set_by_a_teacher_is_left_alone_with_bands(): void {
        $this->setup_course();
        $quiz = $this->make_frog_quiz(4);
        $this->enable($quiz, [0 => 0, 2 => 1, 4 => 2]);
        $item = $this->item($quiz);
        $item->gradepass = 1;
        $item->update('test');
        $this->enable($quiz, [0 => 0, 3 => 1, 4 => 2]);
        $this->assertEquals(1, $this->item($quiz)->gradepass);
    }

    public function test_bands_stay_the_default_scheme(): void {
        $this->setup_course();
        $quiz = $this->make_frog_quiz(4);
        $this->enable($quiz, [0 => 0, 2 => 1]);
        $this->assertSame('bands', quiz_config::get($quiz->id)->scheme);
        $this->assertEquals(0, $this->item($quiz)->gradepass);
    }

    public function test_form_validation_names_the_pass_fail_fields(): void {
        $this->resetAfterTest();
        $formwrapper = $this->createStub(\moodleform_mod::class);
        $formwrapper->method('get_current')->willReturn((object)['modulename' => 'quiz']);
        $formwrapper->method('get_course')->willReturn($this->getDataGenerator()->create_course());
        $data = ['bandedgrade_enabled' => 1, 'bandedgrade_scheme' => 'passfail', 'bandedgrade_pf_ruletype' => 'bands',
            'bandedgrade_pf_mark' => '0', 'bandedgrade_pf_pass' => 'x', 'bandedgrade_pf_fail' => '0'];
        $errors = form_section::validate($formwrapper, $data);
        $this->assertSame(['bandedgrade_pf_mark', 'bandedgrade_pf_pass'], array_keys($errors));

        $data['bandedgrade_pf_mark'] = '3';
        $data['bandedgrade_pf_pass'] = '1';
        $this->assertSame([], form_section::validate($formwrapper, $data));
    }

    public function test_turning_off_keeps_the_scheme(): void {
        $this->setup_course();
        $quiz = $this->make_frog_quiz(4);
        $this->enable_passfail($quiz, '3');
        // The form posts no scheme or pass/fail fields once the section is turned off.
        form_section::save((object)['modulename' => 'quiz', 'instance' => $quiz->id, 'bandedgrade_enabled' => 0,
            'bandedgrade_zeroweight' => 1], $this->course);
        $config = quiz_config::get($quiz->id);
        $this->assertEquals(0, $config->enabled);
        $this->assertSame('passfail', $config->scheme);
        $this->assertSame([3, 1.0, 0.0], bands::to_passfail($config->bands));
    }
}
