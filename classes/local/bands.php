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

    /** @var string Rule type: bands on the number of correct questions. */
    const TYPE_COUNT = 'bands';

    /** @var string Rule type: bands on the percentage of correct questions. */
    const TYPE_PERCENT = 'percent';

    /** @var string Scheme: the teacher fills in a list of bands. */
    const SCHEME_BANDS = 'bands';

    /** @var string Scheme: the teacher gives one pass mark (stored as two bands: fail below it, pass from it). */
    const SCHEME_PASSFAIL = 'passfail';

    /** @var string[] Built-in presets that use percentages. */
    const PERCENT_PRESETS = ['zerotothreepct', 'passfailpct'];

    /** @var int Most presets an administrator can add. */
    const MAX_SITE_PRESETS = 20;

    /** @var int Longest preset name. */
    const MAX_NAME = 100;

    /**
     * Built-in presets: key => bands. Names are the lang strings 'preset_<key>'.
     *
     * @return array<string, array> Presets.
     */
    public static function builtin_presets(): array {
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
            'zerotothreepct' => [
                ['from' => 0, 'score' => 0.0],
                ['from' => 10, 'score' => 1.0],
                ['from' => 50, 'score' => 2.0],
                ['from' => 90, 'score' => 3.0],
            ],
            'passfailpct' => [
                ['from' => 0, 'score' => 0.0],
                ['from' => 60, 'score' => 1.0],
            ],
        ];
    }

    /**
     * Whether a rule type is one the plugin knows.
     *
     * @param string $ruletype The rule type.
     * @return bool True for TYPE_COUNT and TYPE_PERCENT.
     */
    public static function is_ruletype(string $ruletype): bool {
        return in_array($ruletype, [self::TYPE_COUNT, self::TYPE_PERCENT], true);
    }

    /**
     * Whether a scheme is one the plugin knows.
     *
     * @param string $scheme The scheme.
     * @return bool True for SCHEME_BANDS and SCHEME_PASSFAIL.
     */
    public static function is_scheme(string $scheme): bool {
        return in_array($scheme, [self::SCHEME_BANDS, self::SCHEME_PASSFAIL], true);
    }

    /**
     * The position in a scale (1 = first word) that a teacher typed: the word itself or its number.
     *
     * @param string $text A word of the scale (any capitals) or a position such as 2.
     * @param string[] $items The scale's words, in order.
     * @return int|null The position, or null when the text is neither.
     */
    public static function scale_position(string $text, array $items): ?int {
        $text = trim($text);
        $items = array_values($items);
        if (preg_match('/^\d+(\.0+)?$/', $text)) {
            $position = (int)$text;
            return ($position >= 1 && $position <= count($items)) ? $position : null;
        }
        foreach ($items as $index => $item) {
            if ($text !== '' && \core_text::strtolower(trim($item)) === \core_text::strtolower($text)) {
                return $index + 1;
            }
        }
        return null;
    }

    /**
     * The message for a score that is not in the scale.
     *
     * @param string[] $items The scale's words.
     * @return string The message.
     */
    private static function scale_error(array $items): string {
        return get_string('error_scalescore', 'local_bandedgrade', (object)[
            'words' => s(implode(', ', $items)), // Form errors are shown as HTML.
            'max' => count($items),
        ]);
    }

    /**
     * Turn a pass mark into two bands, or report what is wrong in plain language.
     *
     * Fail gives the "fail" score from 0 up to the pass mark, pass gives the "pass" score from the pass mark.
     *
     * @param string $mark The pass mark: a whole number of questions, or a percentage (as in from_rows()).
     * @param string $pass The score for a pass.
     * @param string $fail The score for a fail.
     * @param string $ruletype TYPE_COUNT or TYPE_PERCENT.
     * @param string[]|null $scaleitems The words of the scale the scores are in (a word or its position), or null.
     * @return array [bands, errors]: errors keyed 'mark', 'pass' or 'fail'.
     */
    public static function from_passfail(
        string $mark,
        string $pass,
        string $fail,
        string $ruletype = self::TYPE_COUNT,
        ?array $scaleitems = null
    ): array {
        $percent = $ruletype === self::TYPE_PERCENT;
        $errors = [];

        $mark = str_replace(',', '.', trim($mark));
        if ($percent) {
            if (!preg_match('/^\d+(\.\d{1,2})?$/', $mark) || (float)$mark > 100) {
                $errors['mark'] = get_string('error_percent', 'local_bandedgrade');
            }
        } else if (!preg_match('/^\d+$/', $mark)) {
            $errors['mark'] = get_string('error_from', 'local_bandedgrade');
        } else if ((int)$mark > self::MAX_FROM) {
            $errors['mark'] = get_string('error_frommax', 'local_bandedgrade', self::MAX_FROM);
        }
        if (!isset($errors['mark']) && (float)$mark <= 0) {
            // A pass mark of 0 would pass everybody, and the fail band would be empty.
            $errors['mark'] = get_string($percent ? 'error_passmarkpct' : 'error_passmark', 'local_bandedgrade');
        }

        $scores = [];
        foreach (['pass' => $pass, 'fail' => $fail] as $key => $text) {
            if ($scaleitems !== null) {
                $position = self::scale_position($text, $scaleitems);
                if ($position === null) {
                    $errors[$key] = self::scale_error($scaleitems);
                    $scores[$key] = 0.0;
                    continue;
                }
                $text = (string)$position;
            }
            $text = str_replace(',', '.', trim($text));
            if (!preg_match('/^\d+(\.\d{1,5})?$/', $text)) {
                $errors[$key] = get_string('error_score', 'local_bandedgrade');
            } else if ((float)$text > self::MAX_SCORE) {
                $errors[$key] = get_string('error_scoremax', 'local_bandedgrade', self::MAX_SCORE);
            }
            $scores[$key] = (float)$text;
        }
        if (!$errors && max($scores) <= 0) {
            $errors['pass'] = get_string('error_topzero', 'local_bandedgrade');
        }
        if ($errors) {
            return [[], $errors];
        }
        $bands = [['from' => 0, 'score' => $scores['fail']], ['from' => (float)$mark, 'score' => $scores['pass']]];
        return [self::normalise($bands), []];
    }

    /**
     * The pass mark, pass score and fail score of bands stored by the pass/fail scheme.
     *
     * @param array $bands Bands.
     * @return array|null [mark, pass, fail], or null when the bands are not a pass mark with two bands.
     */
    public static function to_passfail(array $bands): ?array {
        $bands = self::normalise($bands);
        if (count($bands) !== 2 || $bands[0]['from'] != 0) {
            return null;
        }
        return [$bands[1]['from'], $bands[1]['score'], $bands[0]['score']];
    }

    /**
     * The rule type of a preset from presets().
     *
     * @param string $key Preset key.
     * @return string TYPE_COUNT or TYPE_PERCENT.
     */
    public static function preset_ruletype(string $key): string {
        if (in_array($key, self::PERCENT_PRESETS, true)) {
            return self::TYPE_PERCENT;
        }
        if (preg_match('/^site(\d+)$/', $key, $m)) {
            return self::site_presets()[(int)$m[1] - 1]['ruletype'] ?? self::TYPE_COUNT;
        }
        return self::TYPE_COUNT;
    }

    /**
     * The presets teachers can pick: the built-in ones (unless an administrator turned them off), then the site's own.
     *
     * Quizzes store a copy of their bands, so changing or removing a preset never changes a quiz.
     *
     * @return array<string, array> Key => bands. Site presets have the keys site1, site2, ...
     */
    public static function presets(): array {
        $presets = get_config('local_bandedgrade', 'builtinpresets') === '0' ? [] : self::builtin_presets();
        foreach (self::site_presets() as $i => $preset) {
            $presets['site' . ($i + 1)] = $preset['bands'];
        }
        return $presets;
    }

    /**
     * The names of the presets from presets(), ready to show.
     *
     * @return array<string, string> Key => name.
     */
    public static function preset_names(): array {
        $names = [];
        if (get_config('local_bandedgrade', 'builtinpresets') !== '0') {
            foreach (array_keys(self::builtin_presets()) as $key) {
                $names[$key] = get_string('preset_' . $key, 'local_bandedgrade');
            }
        }
        foreach (self::site_presets() as $i => $preset) {
            $names['site' . ($i + 1)] = format_string($preset['name'], true, ['context' => \context_system::instance()]);
        }
        return $names;
    }

    /**
     * The presets an administrator added (setting local_bandedgrade/sitepresets). Broken lines are left out.
     *
     * @return array List of ['name' => string, 'bands' => array, 'ruletype' => string].
     */
    public static function site_presets(): array {
        [$presets] = self::parse_site_presets((string)get_config('local_bandedgrade', 'sitepresets'));
        return $presets;
    }

    /**
     * Read the site presets text: one preset per line, "Name | from=score, from=score, ...".
     *
     * "→" or "->" can be used instead of "=", and ";" instead of ",". Empty lines are ignored.
     *
     * @param string $text The setting text.
     * @return array [presets, errors]: presets as in site_presets(); errors are full sentences naming the line.
     */
    public static function parse_site_presets(string $text): array {
        $presets = [];
        $errors = [];
        foreach (preg_split('/\R/u', $text) as $index => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $a = (object)['line' => $index + 1, 'error' => ''];
            $ruletype = self::TYPE_COUNT;
            $parts = explode('|', $line);
            $name = trim($parts[0]);
            if (count($parts) !== 2 || $name === '' || trim($parts[1]) === '') {
                $a->error = get_string('error_presetformat', 'local_bandedgrade');
            } else if (\core_text::strlen($name) > self::MAX_NAME) {
                $a->error = get_string('error_presetname', 'local_bandedgrade', self::MAX_NAME);
            } else {
                $a->error = self::parse_preset_bands($parts[1], $bands, $ruletype);
            }
            if ($a->error === '' && count($presets) >= self::MAX_SITE_PRESETS) {
                $a->error = get_string('error_presetcount', 'local_bandedgrade', self::MAX_SITE_PRESETS);
            }
            if ($a->error !== '') {
                $errors[] = get_string('error_presetline', 'local_bandedgrade', $a);
                continue;
            }
            $presets[] = ['name' => $name, 'bands' => $bands, 'ruletype' => $ruletype];
        }
        return [$presets, $errors];
    }

    /**
     * Read the bands part of a site preset line, with the same rules as the quiz settings form.
     *
     * @param string $text For example "0=0, 1=1, 5=2, 9=3".
     * @param array|null $bands Set to the bands when the text is fine.
     * @param string $ruletype Set to TYPE_PERCENT when every band start ends with %, else TYPE_COUNT.
     * @return string An error message, or '' when the text is fine.
     */
    private static function parse_preset_bands(string $text, ?array &$bands, string &$ruletype): string {
        $bands = null;
        $ruletype = self::TYPE_COUNT;
        $pairs = array_values(array_filter(array_map('trim', preg_split('/[,;]/', $text)), fn($pair) => $pair !== ''));
        if (count($pairs) > self::MAX_ROWS) {
            return get_string('error_presetrows', 'local_bandedgrade', self::MAX_ROWS);
        }
        $froms = [];
        $scores = [];
        foreach ($pairs as $pair) {
            $sides = preg_split('/\s*(?:=|->|→)\s*/u', $pair);
            if (count($sides) !== 2) {
                return get_string('error_presetpair', 'local_bandedgrade', $pair);
            }
            $froms[] = $sides[0];
            $scores[] = $sides[1];
        }
        $percents = count(array_filter($froms, fn($from) => str_ends_with($from, '%')));
        if ($percents && $percents !== count($froms)) {
            return get_string('error_presetmixed', 'local_bandedgrade');
        }
        $type = $percents ? self::TYPE_PERCENT : self::TYPE_COUNT;
        if ($percents) {
            $froms = array_map(fn($from) => trim(substr($from, 0, -1)), $froms);
        }
        [$result, $errors] = self::from_rows($froms, $scores, $type);
        if ($errors) {
            return reset($errors);
        }
        $bands = $result;
        $ruletype = $type;
        return '';
    }

    /**
     * Turn form rows into bands, or report what is wrong in plain language.
     *
     * Empty rows (both boxes blank) are ignored.
     *
     * @param array $froms Row index => "from" text.
     * @param array $scores Row index => "score" text.
     * @param string $ruletype TYPE_COUNT (whole numbers of questions) or TYPE_PERCENT (0 to 100, up to 2 decimals).
     * @param string[]|null $scaleitems The words of the scale the scores are in (each score is a word or its position),
     *        or null for numeric scores.
     * @return array [bands, errors]: bands sorted by from; errors keyed by row index ('all' for the whole table).
     */
    public static function from_rows(
        array $froms,
        array $scores,
        string $ruletype = self::TYPE_COUNT,
        ?array $scaleitems = null
    ): array {
        $percent = $ruletype === self::TYPE_PERCENT;
        $bands = [];
        $errors = [];
        $seen = [];
        for ($i = 0; $i < self::MAX_ROWS; $i++) {
            $from = trim((string)($froms[$i] ?? ''));
            $score = trim((string)($scores[$i] ?? ''));
            if ($from === '' && $score === '') {
                continue;
            }
            if ($percent) {
                $from = str_replace(',', '.', $from);
                if (!preg_match('/^\d+(\.\d{1,2})?$/', $from) || (float)$from > 100) {
                    $errors[$i] = get_string('error_percent', 'local_bandedgrade');
                    continue;
                }
                $from = (float)$from;
            } else if ($from === '' || !preg_match('/^\d+$/', $from)) {
                $errors[$i] = get_string('error_from', 'local_bandedgrade');
                continue;
            } else if ((int)$from > self::MAX_FROM) {
                $errors[$i] = get_string('error_frommax', 'local_bandedgrade', self::MAX_FROM);
                continue;
            } else {
                $from = (int)$from;
            }
            if ($scaleitems !== null) {
                $position = self::scale_position($score, $scaleitems);
                if ($position === null) {
                    $errors[$i] = self::scale_error($scaleitems);
                    continue;
                }
                $score = (string)$position;
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
            if (isset($seen[(string)$from])) {
                $errors[$i] = get_string('error_duplicate', 'local_bandedgrade', $from);
                continue;
            }
            $seen[(string)$from] = true;
            $bands[] = ['from' => $from, 'score' => (float)$score];
        }
        if ($errors) {
            return [[], $errors];
        }
        $bands = self::normalise($bands);
        if (count($bands) < 2) {
            $errors['all'] = get_string('error_toofew', 'local_bandedgrade');
        } else if ($bands[0]['from'] != 0) {
            $errors['all'] = get_string($percent ? 'error_nozeropct' : 'error_nozero', 'local_bandedgrade', $bands[0]['from']);
        } else if (self::max_score($bands) <= 0) {
            $errors['all'] = get_string('error_topzero', 'local_bandedgrade');
        }
        return [$errors ? [] : $bands, $errors];
    }

    /**
     * Check bands that did not come from the form (for example from a backup file) with the same rules.
     *
     * @param array $bands Bands.
     * @param string $ruletype TYPE_COUNT or TYPE_PERCENT.
     * @param string[]|null $scaleitems The words of the scale the scores are positions in, or null.
     * @return bool True if the form would accept them.
     */
    public static function are_valid(array $bands, string $ruletype = self::TYPE_COUNT, ?array $scaleitems = null): bool {
        $froms = [];
        $scores = [];
        foreach (array_values($bands) as $band) {
            if (!is_array($band) || !isset($band['from'], $band['score']) || count($froms) >= self::MAX_ROWS) {
                return false;
            }
            $froms[] = (string)$band['from'];
            $scores[] = sprintf('%.5F', (float)$band['score']);
        }
        [, $errors] = self::from_rows($froms, $scores, $ruletype, $scaleitems);
        return !$errors;
    }

    /**
     * Sort bands by lower bound and fix their types (a whole "from" is an int, such as 5 or 50; 66.67 stays a float).
     *
     * @param array $bands Bands.
     * @return array Sorted bands.
     */
    public static function normalise(array $bands): array {
        $bands = array_map(fn($band) => [
            'from' => (float)$band['from'] == (int)$band['from'] ? (int)$band['from'] : (float)$band['from'],
            'score' => (float)$band['score'],
        ], $bands);
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
     * The lowest band start that the quiz's questions cannot reach, if any.
     *
     * @param array $bands Bands.
     * @param int $total Number of questions that can be counted (0 if none yet).
     * @return int|null The "from" of the first band no student can reach, or null if all can be reached.
     */
    public static function first_unreachable(array $bands, int $total): ?int {
        if ($total <= 0) {
            return null;
        }
        foreach (self::normalise($bands) as $band) {
            if ($band['from'] > $total) {
                return $band['from'];
            }
        }
        return null;
    }

    /**
     * The fewest correct questions that reach a percentage, out of a number of questions.
     *
     * Compares the same way the scorer does (percentage rounded to 2 decimals).
     *
     * @param float $percent The lower bound of a percentage band.
     * @param int $total Number of questions that can be counted (above 0).
     * @return int|null The number of questions, or null if even all of them do not reach it.
     */
    public static function min_correct(float $percent, int $total): ?int {
        for ($correct = 0; $correct <= $total; $correct++) {
            if (round($correct / $total * 100, 2) >= $percent - 0.00001) {
                return $correct;
            }
        }
        return null;
    }

    /**
     * The key of the preset that matches these bands exactly, or '' for custom bands.
     *
     * @param array $bands Bands.
     * @param string $ruletype TYPE_COUNT or TYPE_PERCENT.
     * @return string Preset key or ''.
     */
    public static function matching_preset(array $bands, string $ruletype = self::TYPE_COUNT): string {
        foreach (self::presets() as $key => $preset) {
            if (self::preset_ruletype($key) === $ruletype && self::normalise($preset) == self::normalise($bands)) {
                return $key;
            }
        }
        return '';
    }
}
