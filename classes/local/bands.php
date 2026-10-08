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
 * Bands: checking what a teacher typed, storing them, and the built-in presets.
 *
 * A band is a lower bound ("from this many correct") and a score. Bands are kept sorted by lower bound,
 * the first one starts at 0, so there can be no gaps or overlaps. The scoring itself is in scorer.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bands {
    /** @var int Number of band rows shown in the settings form. */
    const MAX_ROWS = 10;

    /** @var int Highest "from" allowed (no quiz has more questions than this). */
    const MAX_FROM = 10000;

    /** @var float Highest score: gradebook grades are NUMBER(10,5) (lib/db/install.xml grade_items.grademax). */
    const MAX_SCORE = 99999;

    /**
     * Built-in presets: key => bands. Names are the lang strings 'preset_<key>'.
     *
     * @return array<string, array> Presets.
     */
    public static function presets(): array {
        return [
            'zerotothree10' => [
                ['from' => 0, 'score' => 0.0],
                ['from' => 1, 'score' => 1.0],
                ['from' => 5, 'score' => 2.0],
                ['from' => 9, 'score' => 3.0],
            ],
            'passfail10' => [
                ['from' => 0, 'score' => 0.0],
                ['from' => 6, 'score' => 1.0],
            ],
        ];
    }

    /**
     * Turn form rows into bands, or report what is wrong in plain language.
     *
     * Empty rows (both boxes blank) are ignored.
     *
     * @param array $froms Row index => "from" text.
     * @param array $scores Row index => "score" text.
     * @return array [bands, errors]: bands sorted by from; errors keyed by row index ('all' for the whole table).
     */
    public static function from_rows(array $froms, array $scores): array {
        $bands = [];
        $errors = [];
        $seen = [];
        for ($i = 0; $i < self::MAX_ROWS; $i++) {
            $from = trim((string)($froms[$i] ?? ''));
            $score = trim((string)($scores[$i] ?? ''));
            if ($from === '' && $score === '') {
                continue;
            }
            if ($from === '' || !preg_match('/^\d+$/', $from)) {
                $errors[$i] = get_string('error_from', 'local_bandedgrade');
                continue;
            }
            if ((int)$from > self::MAX_FROM) {
                $errors[$i] = get_string('error_frommax', 'local_bandedgrade', self::MAX_FROM);
                continue;
            }
            $score = str_replace(',', '.', $score);
            if ($score === '' || !preg_match('/^\d+(\.\d{1,5})?$/', $score)) {
                $errors[$i] = get_string('error_score', 'local_bandedgrade');
                continue;
            }
            if ((float)$score > self::MAX_SCORE) {
                $errors[$i] = get_string('error_scoremax', 'local_bandedgrade', self::MAX_SCORE);
                continue;
            }
            if (isset($seen[(int)$from])) {
                $errors[$i] = get_string('error_duplicate', 'local_bandedgrade', (int)$from);
                continue;
            }
            $seen[(int)$from] = true;
            $bands[] = ['from' => (int)$from, 'score' => (float)$score];
        }
        if ($errors) {
            return [[], $errors];
        }
        $bands = self::normalise($bands);
        if (count($bands) < 2) {
            $errors['all'] = get_string('error_toofew', 'local_bandedgrade');
        } else if ($bands[0]['from'] !== 0) {
            $errors['all'] = get_string('error_nozero', 'local_bandedgrade', $bands[0]['from']);
        } else if (self::max_score($bands) <= 0) {
            $errors['all'] = get_string('error_topzero', 'local_bandedgrade');
        }
        return [$errors ? [] : $bands, $errors];
    }

    /**
     * Check bands that did not come from the form (for example from a backup file) with the same rules.
     *
     * @param array $bands Bands.
     * @return bool True if the form would accept them.
     */
    public static function are_valid(array $bands): bool {
        $froms = [];
        $scores = [];
        foreach (array_values($bands) as $band) {
            if (!is_array($band) || !isset($band['from'], $band['score']) || count($froms) >= self::MAX_ROWS) {
                return false;
            }
            $froms[] = (string)$band['from'];
            $scores[] = sprintf('%.5F', (float)$band['score']);
        }
        [, $errors] = self::from_rows($froms, $scores);
        return !$errors;
    }

    /**
     * Sort bands by lower bound and fix their types.
     *
     * @param array $bands Bands.
     * @return array Sorted bands.
     */
    public static function normalise(array $bands): array {
        $bands = array_map(fn($band) => ['from' => (int)$band['from'], 'score' => (float)$band['score']], $bands);
        usort($bands, fn($a, $b) => $a['from'] <=> $b['from']);
        return array_values($bands);
    }

    /**
     * Encode bands for storage.
     *
     * @param array $bands Bands.
     * @return string JSON.
     */
    public static function encode(array $bands): string {
        return json_encode(self::normalise($bands));
    }

    /**
     * Decode stored bands.
     *
     * @param string|null $json JSON from the database.
     * @return array Bands (empty if none or broken).
     */
    public static function decode(?string $json): array {
        $bands = $json ? json_decode($json, true) : null;
        return is_array($bands) ? self::normalise($bands) : [];
    }

    /**
     * The highest score any band gives (the gradebook maximum).
     *
     * @param array $bands Bands.
     * @return float Highest score.
     */
    public static function max_score(array $bands): float {
        return $bands ? max(array_column($bands, 'score')) : 0.0;
    }

    /**
     * The key of the preset that matches these bands exactly, or '' for custom bands.
     *
     * @param array $bands Bands.
     * @return string Preset key or ''.
     */
    public static function matching_preset(array $bands): string {
        foreach (self::presets() as $key => $preset) {
            if (self::normalise($preset) == self::normalise($bands)) {
                return $key;
            }
        }
        return '';
    }
}
