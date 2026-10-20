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

    /** @var string[] Colours suggested for the bands, from red to green (used as the starting point in the settings). */
    const SUGGESTED = ['#f8c9cd', '#ffe69c', '#9fd8ee', '#9ec5fe', '#b6e3c6'];

    /**
     * The colour of the band a value falls in, when the teacher chose colours for the bands.
     *
     * @param \stdClass $config The quiz settings (ruletype, bands).
     * @param float|null $used The number correct that sets the score, or null if there is none.
     * @param int $total Number of questions that can be counted now.
     * @return string|null "#rrggbb", or null when there is no count or the band has no colour.
     */
    public static function band_colour(\stdClass $config, ?float $used, int $total): ?string {
        $value = scorer::band_value($config->ruletype, $used, $total);
        if ($value === null) {
            return null;
        }
        $colour = null;
        foreach (bands::normalise($config->bands) as $band) {
            if ($band['from'] > $value + scorer::EPSILON) {
                break;
            }
            $colour = $band['colour'] ?? null;
        }
        return $colour;
    }

    /**
     * A readable text colour for a background.
     *
     * @param string $background "#rrggbb".
     * @return string The dark text colour of the other colours, or white on a dark background.
     */
    public static function text_colour(string $background): string {
        [$r, $g, $b] = array_map(function ($hex) {
            $c = hexdec($hex) / 255;
            return $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
        }, str_split(ltrim($background, '#'), 2));
        $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
        // Dark text (#212529) has luminance about 0.018: it is the better choice above about 0.18.
        return $luminance > 0.18 ? '#212529' : '#ffffff';
    }

    /**
     * The inline style that colours a cell with a band colour.
     *
     * @param string $background "#rrggbb".
     * @return string CSS declarations.
     */
    public static function style(string $background): string {
        return 'background-color: ' . $background . '; color: ' . self::text_colour($background) . ';';
    }

    /**
     * The colour of band number $index out of $count, for a starting point in the settings.
     *
     * The suggested colours are spread from the first to the last, so two bands give red and green.
     *
     * @param int $index Band number, from 0.
     * @param int $count Number of bands.
     * @return string "#rrggbb".
     */
    public static function suggested(int $index, int $count): string {
        $last = count(self::SUGGESTED) - 1;
        $position = $count > 1 ? (int)round(min($index, $count - 1) * $last / ($count - 1)) : $last;
        return self::SUGGESTED[min($last, $position)];
    }

    /**
     * How to colour a count: the colour of its band when the teacher chose band colours, else the colour of its percentage.
     *
     * @param \stdClass $config The quiz settings (ruletype, bands).
     * @param float|null $used The number correct that sets the score, or null if there is none.
     * @param int $total Number of questions that can be counted now.
     * @return array|null [css class, inline style or null, percentage], or null for nothing to colour.
     */
    public static function cell(\stdClass $config, ?float $used, int $total): ?array {
        $percent = self::percent($used, $total);
        if ($percent === null) {
            return null;
        }
        if (bands::has_colours($config->bands)) {
            $background = self::band_colour($config, $used, $total);
            return $background === null ? null : ['local_bandedgrade-custom', self::style($background), $percent];
        }
        return [self::css_class(self::name($percent)), null, $percent];
    }

    /**
     * The colour key of a quiz: one swatch with a label for each band colour, or for each percentage colour.
     *
     * @param \stdClass $config The quiz settings (ruletype, bands).
     * @return array [intro string, HTML of the swatches separated by spaces].
     */
    public static function key(\stdClass $config): array {
        $swatches = [];
        if (bands::has_colours($config->bands)) {
            foreach (bands::normalise($config->bands) as $band) {
                if (isset($band['colour'])) {
                    $swatches[] = \html_writer::span(
                        get_string(
                            $config->ruletype === bands::TYPE_PERCENT ? 'colourkeypercent' : 'colourkeycount',
                            'local_bandedgrade',
                            format_float($band['from'], 2, true, true)
                        ),
                        'local_bandedgrade-custom',
                        ['style' => self::style($band['colour'])]
                    );
                }
            }
            return [get_string('reportcolourkeybands', 'local_bandedgrade'), implode(' ', $swatches)];
        }
        foreach (self::NAMES as $name) {
            $swatches[] = \html_writer::span(get_string('colour_' . $name, 'local_bandedgrade'), self::css_class($name));
        }
        return [get_string('reportcolourkey', 'local_bandedgrade'), implode(' ', $swatches)];
    }
}
