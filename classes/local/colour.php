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

namespace local_bandedgrade\local;

/**
 * Colour codes for the percentage of questions a student got fully right.
 *
 * 0% to 59.9% red, 60% to 69.9% yellow, 70% to 79.9% light blue, 80% to 89.9% blue, 90% to 100% green.
 * The percentage is rounded to one decimal first, so it always agrees with what the pages show.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class colour {
    /** @var string[] Colour names, lowest percentage first. */
    const NAMES = ['red', 'yellow', 'lightblue', 'blue', 'green'];

    /** @var int[] The percentage each colour after the first one starts at. */
    const STARTS = [60, 70, 80, 90];

    /**
     * The percentage of questions answered correctly.
     *
     * @param float|null $used The number correct that sets the score, or null if there is none.
     * @param int $total Number of questions that can be counted now.
     * @return float|null The percentage rounded to one decimal (0 to 100), or null with no count or no question.
     */
    public static function percent(?float $used, int $total): ?float {
        if ($used === null || $total <= 0) {
            return null;
        }
        return max(0.0, min(100.0, round($used / $total * 100, 1)));
    }

    /**
     * The colour name for a percentage.
     *
     * @param float|null $percent A percentage from percent().
     * @return string|null One of NAMES, or null for no percentage.
     */
    public static function name(?float $percent): ?string {
        if ($percent === null) {
            return null;
        }
        $index = 0;
        foreach (self::STARTS as $start) {
            if ($percent >= $start) {
                $index++;
            }
        }
        return self::NAMES[$index];
    }

    /**
     * The CSS class (see styles.css) for a colour name.
     *
     * @param string $name One of NAMES.
     * @return string The class.
     */
    public static function css_class(string $name): string {
        return 'local_bandedgrade-' . $name;
    }
}
