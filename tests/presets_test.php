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

use local_bandedgrade\admin\setting_presets;
use local_bandedgrade\local\bands;
use local_bandedgrade\local\quiz_config;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the presets an administrator sets up.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(bands::class)]
#[CoversClass(setting_presets::class)]
#[CoversClass(local\form_section::class)]
final class presets_test extends \advanced_testcase {
    use \local_bandedgrade\tests\quiz_trait;

    protected function setUp(): void {
        parent::setUp();
        $this->setup_course();
    }

    public function test_parse_lines_in_several_spellings(): void {
        $text = "Scores 0 to 3 | 0=0, 1=1, 5=2, 9=3\n\n  Pass at 6 | 6 → 1; 0 -> 0  \r\nHalf points | 0=0, 3=0.5, 6=1.25";
        [$presets, $errors] = bands::parse_site_presets($text);
        $this->assertSame([], $errors);
        $this->assertSame(['Scores 0 to 3', 'Pass at 6', 'Half points'], array_column($presets, 'name'));
        $this->assertSame([['from' => 0, 'score' => 0.0], ['from' => 6, 'score' => 1.0]], $presets[1]['bands']);
        $this->assertSame(1.25, $presets[2]['bands'][2]['score']);
    }

    public function test_parse_errors_name_the_line(): void {
        $text = "Good | 0=0, 5=1\nNo bar 0=0, 5=1\nComma | 0=0, 5=2,5\nGap | 1=1, 5=2\nToo many | " .
            implode(', ', array_map(fn($i) => "$i=$i", range(0, 10))) . "\n" . str_repeat('x', 101) . " | 0=0, 1=1";
        [$presets, $errors] = bands::parse_site_presets($text);
        $this->assertSame(['Good'], array_column($presets, 'name'), 'Good lines are still read.');
        $this->assertCount(5, $errors);
        $this->assertStringStartsWith('Line 2: Write the name, then a | sign', $errors[0]);
        $this->assertStringStartsWith('Line 3: "5" is not a band.', $errors[1]);
        $this->assertStringStartsWith('Line 4: The first band must start at 0 correct.', $errors[2]);
        $this->assertSame('Line 5: Use at most 10 bands in one set.', $errors[3]);
        $this->assertSame('Line 6: Use a name of at most 100 characters.', $errors[4]);
    }

    public function test_at_most_twenty_presets(): void {
        $text = implode("\n", array_map(fn($i) => "Set $i | 0=0, $i=1", range(1, 21)));
        [$presets, $errors] = bands::parse_site_presets($text);
        $this->assertCount(20, $presets);
        $this->assertSame(['Line 21: There can be at most 20 sets of bands. Remove a line.'], $errors);
    }

    public function test_setting_refuses_broken_text(): void {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
        $setting = new setting_presets('local_bandedgrade/sitepresets', 'Sets', '', '');
        $this->assertTrue($setting->validate("Fine | 0=0, 4=1\n"));
        $this->assertTrue($setting->validate(''));
        $this->assertStringContainsString('Line 1:', $setting->validate('Broken | 0=zero'));
    }

    public function test_presets_offered_to_teachers(): void {
        set_config('sitepresets', "Our 0 to 2 | 0=0, 3=1, 7=2\nBroken line", 'local_bandedgrade');
        $this->assertSame(['zerotothree10', 'passfail10', 'site1'], array_keys(bands::presets()));
        $this->assertSame('Our 0 to 2', bands::preset_names()['site1']);
        $this->assertSame('site1', bands::matching_preset([['from' => 7, 'score' => 2], ['from' => 0, 'score' => 0],
            ['from' => 3, 'score' => 1]]));

        set_config('builtinpresets', 0, 'local_bandedgrade');
        $this->assertSame(['site1'], array_keys(bands::presets()));
        $this->assertSame(['site1'], array_keys(bands::preset_names()));
        $this->assertSame('', bands::matching_preset(bands::builtin_presets()['zerotothree10']));
    }

    public function test_quiz_keeps_its_copy_of_a_site_preset(): void {
        set_config('sitepresets', 'Ours | 0=0, 2=1', 'local_bandedgrade');
        $quiz = $this->make_frog_quiz(3);
        local\form_section::save((object)[
            'modulename' => 'quiz',
            'instance' => $quiz->id,
            'bandedgrade_enabled' => 1,
            'bandedgrade_preset' => 'site1',
            'bandedgrade_zeroweight' => 1,
        ], $this->course);
        $expected = [['from' => 0, 'score' => 0.0], ['from' => 2, 'score' => 1.0]];
        $this->assertSame($expected, quiz_config::get($quiz->id)->bands);

        set_config('sitepresets', 'Ours | 0=0, 1=5', 'local_bandedgrade');
        $this->assertSame($expected, quiz_config::get($quiz->id)->bands, 'Editing the preset does not touch the quiz.');
        $this->assertSame('', bands::matching_preset($expected), 'The quiz now shows its bands as its own.');
    }
}
