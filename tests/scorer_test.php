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
use local_bandedgrade\local\scorer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/lib.php');

/**
 * Tests for the scoring rules that need no quiz: bands, and picking the count.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(scorer::class)]
#[CoversClass(bands::class)]
final class scorer_test extends \advanced_testcase {
    /** @var array The 0-3 example: 0 → 0, 1-4 → 1, 5-8 → 2, 9-10 → 3. */
    const EXAMPLE = [['from' => 0, 'score' => 0], ['from' => 1, 'score' => 1], ['from' => 5, 'score' => 2],
        ['from' => 9, 'score' => 3]];

    public function test_score_for_count_example(): void {
        $expected = [0 => 0, 1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 2, 6 => 2, 7 => 2, 8 => 2, 9 => 3, 10 => 3];
        foreach ($expected as $count => $score) {
            $this->assertEquals($score, scorer::score_for_count(self::EXAMPLE, $count), "$count correct");
        }
        $this->assertNull(scorer::score_for_count(self::EXAMPLE, null));
        $this->assertEquals(1, scorer::score_for_count(self::EXAMPLE, 4.5), 'Between bands gets the lower band.');
        $this->assertEquals(2, scorer::score_for_count(self::EXAMPLE, 4.999999999), 'Rounding noise is ignored.');
    }

    public function test_score_for_count_unsorted_and_decreasing(): void {
        $bands = [['from' => 5, 'score' => 1], ['from' => 0, 'score' => 3], ['from' => 8, 'score' => 0.5]];
        $this->assertEquals(3, scorer::score_for_count($bands, 4));
        $this->assertEquals(1, scorer::score_for_count($bands, 5));
        $this->assertEquals(0.5, scorer::score_for_count($bands, 20));
    }

    /**
     * Data for test_choose_count.
     *
     * @return array Cases.
     */
    public static function choose_count_provider(): array {
        // Attempts in order: 2 correct, 6 correct, waiting for marking (3 so far), 4 correct.
        $attempts = [[2, false], [6, false], [3, true], [4, false]];
        return [
            'highest' => [QUIZ_GRADEHIGHEST, $attempts, 6.0],
            'average skips waiting' => [QUIZ_GRADEAVERAGE, $attempts, 4.0],
            'first' => [QUIZ_ATTEMPTFIRST, $attempts, 2.0],
            'last' => [QUIZ_ATTEMPTLAST, $attempts, 4.0],
            'last is waiting' => [QUIZ_ATTEMPTLAST, [[2, false], [3, true]], null],
            'first is waiting' => [QUIZ_ATTEMPTFIRST, [[3, true], [5, false]], null],
            'highest all waiting' => [QUIZ_GRADEHIGHEST, [[3, true]], null],
            'average between' => [QUIZ_GRADEAVERAGE, [[4, false], [5, false]], 4.5],
            'no attempts' => [QUIZ_GRADEHIGHEST, [], null],
        ];
    }

    /**
     * The quiz grading method picks the count.
     *
     * @param string $method Grading method (the QUIZ_ constants are strings).
     * @param array $attempts [correct, pending] per attempt.
     * @param float|null $expected Chosen count.
     */
    #[DataProvider('choose_count_provider')]
    public function test_choose_count(string $method, array $attempts, ?float $expected): void {
        $counts = array_map(fn($a) => (object)['correct' => $a[0], 'pending' => $a[1]], $attempts);
        $this->assertSame($expected, scorer::choose_count((int)$method, $counts));
    }

    public function test_bands_from_rows(): void {
        $this->resetAfterTest();
        [$bands, $errors] = bands::from_rows(['9', '', '0', '5', '1'], ['3', '', '0', '2', '1,5']);
        $this->assertSame([], $errors);
        $this->assertSame([0, 1, 5, 9], array_column($bands, 'from'));
        $this->assertSame(1.5, $bands[1]['score']);

        [, $errors] = bands::from_rows(['1', '5'], ['1', '2']);
        $this->assertArrayHasKey('all', $errors, 'Must start at 0.');
        [, $errors] = bands::from_rows(['0', '0'], ['1', '2']);
        $this->assertArrayHasKey(1, $errors, 'Same start twice.');
        [, $errors] = bands::from_rows(['0', 'x', '3'], ['0', '1', '-1']);
        $this->assertArrayHasKey(1, $errors);
        $this->assertArrayHasKey(2, $errors);
        [, $errors] = bands::from_rows(['0'], ['1']);
        $this->assertArrayHasKey('all', $errors, 'At least two bands.');
        [, $errors] = bands::from_rows(['0', '3'], ['0', '0']);
        $this->assertArrayHasKey('all', $errors, 'Some score above 0.');
    }
}
