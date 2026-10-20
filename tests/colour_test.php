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
use local_bandedgrade\local\colour;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the colour codes of the percentage of questions answered correctly.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(colour::class)]
#[CoversClass(bands::class)]
final class colour_test extends \advanced_testcase {
    /**
     * The edges of every colour.
     *
     * @return array[] Percentage, colour name.
     */
    public static function names_provider(): array {
        return [
            'zero' => [0.0, 'red'],
            'just under 60' => [59.9, 'red'],
            '60' => [60.0, 'yellow'],
            'just under 70' => [69.9, 'yellow'],
            '70' => [70.0, 'lightblue'],
            'just under 80' => [79.9, 'lightblue'],
            '80' => [80.0, 'blue'],
            'just under 90' => [89.9, 'blue'],
            '90' => [90.0, 'green'],
            'full marks' => [100.0, 'green'],
        ];
    }

    #[DataProvider('names_provider')]
    public function test_name(float $percent, string $expected): void {
        $this->assertSame($expected, colour::name($percent));
    }

    public function test_name_without_percentage(): void {
        $this->assertNull(colour::name(null));
    }

    public function test_percent(): void {
        $this->assertSame(75.0, colour::percent(3.0, 4));
        $this->assertSame(66.7, colour::percent(2.0, 3));
        $this->assertSame(0.0, colour::percent(0.0, 4));
        $this->assertSame(100.0, colour::percent(5.0, 4), 'Never above 100.');
        $this->assertNull(colour::percent(null, 4));
        $this->assertNull(colour::percent(1.0, 0), 'No question to take a percentage of.');
    }

    public function test_rounding_agrees_with_the_displayed_percentage(): void {
        // 59.96% is shown as 60.0%, so it gets the colour of 60%.
        $this->assertSame('yellow', colour::name(colour::percent(59.96, 100)));
        $this->assertSame('red', colour::name(colour::percent(59.94, 100)));
    }

    public function test_css_class_for_every_name(): void {
        foreach (colour::NAMES as $name) {
            $this->assertSame('local_bandedgrade-' . $name, colour::css_class($name));
        }
    }

    /**
     * Settings of a quiz with the colours the teacher chose.
     *
     * @param string $ruletype Rule type.
     * @return \stdClass Settings.
     */
    private function config(string $ruletype = bands::TYPE_COUNT): \stdClass {
        return (object)['ruletype' => $ruletype, 'bands' => bands::normalise([
            ['from' => 0, 'score' => 0, 'colour' => '#FF0000'],
            ['from' => 2, 'score' => 1],
            ['from' => 4, 'score' => 2, 'colour' => '#00ff00'],
        ])];
    }

    public function test_band_colour_follows_the_band_of_the_count(): void {
        $config = $this->config();
        $this->assertSame('#ff0000', colour::band_colour($config, 0.0, 4), 'Stored in lower case.');
        $this->assertSame('#ff0000', colour::band_colour($config, 1.0, 4));
        $this->assertNull(colour::band_colour($config, 3.0, 4), 'The band has no colour.');
        $this->assertSame('#00ff00', colour::band_colour($config, 4.0, 4));
        $this->assertSame('#ff0000', colour::band_colour($config, 1.5, 4), 'Between two bands: the lower one.');
        $this->assertNull(colour::band_colour($config, null, 4));
    }

    public function test_band_colour_with_percentage_bands(): void {
        $config = (object)['ruletype' => bands::TYPE_PERCENT, 'bands' => [
            ['from' => 0, 'score' => 0, 'colour' => '#ff0000'],
            ['from' => 50, 'score' => 1, 'colour' => '#00ff00'],
        ]];
        $this->assertSame('#ff0000', colour::band_colour($config, 1.0, 4));
        $this->assertSame('#00ff00', colour::band_colour($config, 2.0, 4));
        $this->assertNull(colour::band_colour($config, 2.0, 0), 'No question to take a percentage of.');
    }

    public function test_cell_uses_the_percentage_colours_until_colours_are_chosen(): void {
        $plain = (object)['ruletype' => bands::TYPE_COUNT, 'bands' => [
            ['from' => 0, 'score' => 0],
            ['from' => 2, 'score' => 1],
        ]];
        $this->assertSame(['local_bandedgrade-yellow', null, 60.0], colour::cell($plain, 3.0, 5));
        $this->assertNull(colour::cell($plain, null, 5));
        $this->assertNull(colour::cell($plain, 3.0, 0));
    }

    public function test_cell_with_band_colours(): void {
        $cell = colour::cell($this->config(), 4.0, 4);
        $this->assertSame('local_bandedgrade-custom', $cell[0]);
        $this->assertSame('background-color: #00ff00; color: #212529;', $cell[1]);
        $this->assertSame(100.0, $cell[2]);
        $this->assertNull(colour::cell($this->config(), 3.0, 4), 'No colour for that band: leave the cell alone.');
    }

    public function test_text_colour_is_readable(): void {
        $this->assertSame('#212529', colour::text_colour('#ffffff'));
        $this->assertSame('#212529', colour::text_colour('#ffe69c'));
        $this->assertSame('#212529', colour::text_colour('#ffff00'));
        $this->assertSame('#ffffff', colour::text_colour('#000000'));
        $this->assertSame('#ffffff', colour::text_colour('#0b3d91'));
    }

    public function test_suggested_colours_run_from_red_to_green(): void {
        $this->assertSame(colour::SUGGESTED[0], colour::suggested(0, 2));
        $this->assertSame(colour::SUGGESTED[4], colour::suggested(1, 2));
        $this->assertSame(colour::SUGGESTED[0], colour::suggested(0, 5));
        $this->assertSame(colour::SUGGESTED[2], colour::suggested(2, 5));
        $this->assertSame(colour::SUGGESTED[4], colour::suggested(4, 5));
        $this->assertSame(colour::SUGGESTED[4], colour::suggested(9, 10), 'Every number gives a colour.');
    }

    public function test_only_plain_hex_colours_are_kept(): void {
        $this->assertSame('#aabbcc', bands::clean_colour(' #AABBCC '));
        foreach (['', 'red', '#abc', '#12345g', 'url(x)', '#ff0000; x', null, 5] as $bad) {
            $this->assertNull(bands::clean_colour($bad));
        }
        $bands = bands::normalise([['from' => 0, 'score' => 1, 'colour' => 'javascript:alert(1)']]);
        $this->assertSame([['from' => 0, 'score' => 1.0]], $bands);
    }

    public function test_colours_in_rows_and_in_a_stored_copy(): void {
        [$bands, $errors] = bands::from_rows(
            ['4', '0', ''],
            ['2', '0', ''],
            bands::TYPE_COUNT,
            null,
            ['#00ff00', '#ff0000', '#123456']
        );
        $this->assertSame([], $errors);
        $this->assertSame([
            ['from' => 0, 'score' => 0.0, 'colour' => '#ff0000'],
            ['from' => 4, 'score' => 2.0, 'colour' => '#00ff00'],
        ], $bands, 'Each colour stays with its row when the rows are sorted; a blank row is dropped with its colour.');
        $this->assertSame($bands, bands::decode(bands::encode($bands)));
        $this->assertTrue(bands::has_colours($bands));
        $this->assertFalse(bands::has_colours(bands::without_colours($bands)));
        $this->assertTrue(bands::are_valid($bands), 'A colour does not change whether the bands are valid.');
    }

    public function test_with_colours_matches_colours_to_bands_in_order(): void {
        $bands = bands::with_colours(
            [['from' => 0, 'score' => 0.0], ['from' => 5, 'score' => 1.0], ['from' => 9, 'score' => 2.0]],
            ['#111111', 'nope']
        );
        $this->assertSame('#111111', $bands[0]['colour']);
        $this->assertArrayNotHasKey('colour', $bands[1]);
        $this->assertArrayNotHasKey('colour', $bands[2]);
    }

    public function test_colours_do_not_stop_a_preset_from_matching(): void {
        $this->resetAfterTest();
        $preset = bands::builtin_presets()['zerotothree10'];
        $coloured = bands::with_colours($preset, ['#ff0000', '#ffff00', '#00ff00', '#0000ff']);
        $this->assertSame('zerotothree10', bands::matching_preset($coloured));
    }
}
