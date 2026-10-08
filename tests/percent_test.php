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
use local_bandedgrade\local\quiz_config;
use local_bandedgrade\local\scorer;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/lib.php');

/**
 * Tests for bands based on the percentage of correct questions.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(scorer::class)]
#[CoversClass(bands::class)]
#[CoversClass(form_section::class)]
#[CoversClass(quiz_config::class)]
final class percent_test extends \advanced_testcase {
    use \local_bandedgrade\tests\quiz_trait;

    public function test_band_value(): void {
        $this->assertSame(5.0, scorer::band_value('bands', 5.0, 10));
        $this->assertSame(50.0, scorer::band_value('percent', 5.0, 10));
        $this->assertSame(66.67, scorer::band_value('percent', 2.0, 3));
        $this->assertSame(33.33, scorer::band_value('percent', 4 / 3, 4), 'An average count gives an average percentage.');
        $this->assertNull(scorer::band_value('percent', null, 10));
        $this->assertNull(scorer::band_value('percent', 0.0, 0), 'No questions: nothing to take a percentage of.');
    }

    public function test_from_rows_percent(): void {
        [$bands, $errors] = bands::from_rows(['0', '66,67', '100'], ['0', '1', '2'], bands::TYPE_PERCENT);
        $this->assertSame([], $errors);
        $this->assertSame([0, 66.67, 100], array_column($bands, 'from'));

        foreach (['101', '-1', 'x', '50.555', '1e2'] as $from) {
            [, $errors] = bands::from_rows(['0', $from], ['0', '1'], bands::TYPE_PERCENT);
            $this->assertArrayHasKey(1, $errors, "'$from' is not a percentage");
        }
        [, $errors] = bands::from_rows(['10', '50'], ['0', '1'], bands::TYPE_PERCENT);
        $this->assertStringContainsString('0%', $errors['all']);
        [, $errors] = bands::from_rows(['0', '50', '50.0'], ['0', '1', '2'], bands::TYPE_PERCENT);
        $this->assertArrayHasKey(2, $errors, 'Two bands may not start at the same percentage.');
        [, $errors] = bands::from_rows(['0', '50.5'], ['0', '1'], bands::TYPE_COUNT);
        $this->assertArrayHasKey(1, $errors, 'A number of questions is still a whole number.');
    }

    public function test_min_correct(): void {
        $this->assertSame(5, bands::min_correct(50, 10));
        $this->assertSame(2, bands::min_correct(66.67, 3));
        $this->assertSame(2, bands::min_correct(60, 3), '2 of 3 is 66.67%, 1 of 3 is 33.33%.');
        $this->assertSame(0, bands::min_correct(0, 4));
        $this->assertSame(3, bands::min_correct(100, 3));
    }

    public function test_site_preset_with_percent_signs(): void {
        $text = "By percent | 0%=0, 50%=1, 66.67 % = 2\nCount | 0=0, 5=1\nMixed | 0%=0, 5=1";
        [$presets, $errors] = bands::parse_site_presets($text);
        $this->assertCount(2, $presets);
        $this->assertSame(bands::TYPE_PERCENT, $presets[0]['ruletype']);
        $this->assertSame([0, 50, 66.67], array_column($presets[0]['bands'], 'from'));
        $this->assertSame(bands::TYPE_COUNT, $presets[1]['ruletype']);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Line 3:', $errors[0]);

        [, $errors] = bands::parse_site_presets('Too big | 0%=0, 101%=1');
        $this->assertCount(1, $errors);
    }

    public function test_preset_types(): void {
        $this->resetAfterTest();
        set_config('sitepresets', "Ours | 0%=0, 40%=1\nOther | 0=0, 3=1", 'local_bandedgrade');
        $this->assertSame('bands', bands::preset_ruletype('zerotothree10'));
        $this->assertSame('percent', bands::preset_ruletype('zerotothreepct'));
        $this->assertSame('percent', bands::preset_ruletype('site1'));
        $this->assertSame('bands', bands::preset_ruletype('site2'));
        foreach (bands::builtin_presets() as $key => $preset) {
            $this->assertTrue(bands::are_valid($preset, bands::preset_ruletype($key)), $key);
        }
        $bands = bands::presets()['site1'];
        $this->assertSame('site1', bands::matching_preset($bands, 'percent'));
        $this->assertSame('', bands::matching_preset($bands, 'bands'), 'Same numbers, other meaning.');
    }

    public function test_percentage_follows_the_number_of_questions(): void {
        $this->setup_course();
        $quiz = $this->make_frog_quiz(4);
        $this->enable($quiz, [0 => 0, 50 => 1, 75 => 2, 100 => 3], true, true, 'percent');
        $this->assertSame('percent', quiz_config::get($quiz->id)->ruletype);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);

        $this->attempt($quiz, $student, $this->frogs(3, 4));
        $this->assertEquals(2, $this->score($quiz, $student->id), '3 of 4 is 75%.');

        // Two more questions: the same attempt is now 3 of 6 = 50%, as the quiz's own grade would also fall.
        $this->add_question($quiz);
        $this->add_question($quiz);
        $this->run_tasks();
        $this->assertEquals(1, $this->score($quiz, $student->id), '3 of 6 is 50%.');
    }

    public function test_thirds_meet_a_rounded_band(): void {
        $this->setup_course();
        $quiz = $this->make_frog_quiz(3);
        $this->enable($quiz, [0 => 0, '66.67' => 1, 100 => 2], true, true, 'percent');
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->attempt($quiz, $student, $this->frogs(2, 3));
        $this->assertEquals(1, $this->score($quiz, $student->id), '2 of 3 is 66.67%.');
    }

    public function test_average_method_uses_average_percentage(): void {
        $this->setup_course();
        $quiz = $this->make_frog_quiz(4, ['grademethod' => QUIZ_GRADEAVERAGE]);
        $this->enable($quiz, [0 => 0, 50 => 1, 75 => 2], true, true, 'percent');
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->attempt($quiz, $student, $this->frogs(4, 4));
        $this->attempt($quiz, $student, $this->frogs(1, 4));
        $this->assertEquals(1, $this->score($quiz, $student->id), '(100% + 25%) / 2 = 62.5%.');
    }

    public function test_switching_the_rule_type_rescores(): void {
        $this->setup_course();
        $quiz = $this->make_frog_quiz(10);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->enable($quiz, [0 => 0, 5 => 1, 9 => 2]);
        $this->attempt($quiz, $student, $this->frogs(7, 10));
        $this->assertEquals(1, $this->score($quiz, $student->id));

        $this->enable($quiz, [0 => 0, 70 => 5, 90 => 6], true, true, 'percent');
        $this->run_tasks();
        $this->assertEquals(5, $this->score($quiz, $student->id), '70% now counts; the band starts at 70.');
        $this->assertEquals(6, $this->item($quiz)->grademax);
    }

    public function test_form_data_picks_the_rule_type(): void {
        $this->setup_course();
        $quiz = $this->make_frog_quiz(2);
        // A percentage preset sets the type even though the (disabled) rule type box is not posted.
        $moduleinfo = (object)['modulename' => 'quiz', 'instance' => $quiz->id, 'bandedgrade_enabled' => 1,
            'bandedgrade_preset' => 'zerotothreepct', 'bandedgrade_zeroweight' => 1];
        form_section::save($moduleinfo, $this->course);
        $config = quiz_config::get($quiz->id);
        $this->assertSame('percent', $config->ruletype);
        $this->assertSame([0, 10, 50, 90], array_column($config->bands, 'from'));

        $moduleinfo->bandedgrade_preset = 'passfail10';
        form_section::save($moduleinfo, $this->course);
        $this->assertSame('bands', quiz_config::get($quiz->id)->ruletype);
    }

    public function test_preview_for_percentages(): void {
        $html = form_section::preview_html([['from' => 0, 'score' => 0], ['from' => 50, 'score' => 1],
            ['from' => 66.67, 'score' => 2]], 3, 'percent');
        $this->assertStringContainsString('50% or more correct (2 of 3 questions or more) → score 1', $html);
        $this->assertStringContainsString('66.67% or more correct (2 of 3 questions or more) → score 2', $html);
        $html = form_section::preview_html([['from' => 0, 'score' => 0], ['from' => 50, 'score' => 1]], 0, 'percent');
        $this->assertStringContainsString('50% or more correct → score 1', $html);
        $this->assertStringNotContainsString('questions or more', $html);
    }
}
