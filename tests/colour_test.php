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
}
