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

use local_bandedgrade\event\scores_recalculated;
use local_bandedgrade\task\rescore_quiz;

/**
 * The "Recalculate scores" request: queue the rescore and log it.
 *
 * Permission checks are done by recalculate.php before calling this.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recalculate {
    /**
     * Queue a rescore of the quiz and log the request.
     *
     * @param \stdClass $quiz The quiz row.
     * @param \context_module $context The quiz context.
     * @param bool $overwrite True to replace scores changed by hand.
     */
    public static function request(\stdClass $quiz, \context_module $context, bool $overwrite): void {
        rescore_quiz::queue((int)$quiz->id, $overwrite);
        scores_recalculated::create([
            'objectid' => $quiz->id,
            'context' => $context,
            'other' => ['overwrite' => (int)$overwrite],
        ])->trigger();
    }

    /**
     * Names of the students whose score was changed by hand, for the page.
     *
     * @param int $quizid The quiz id.
     * @return string[] Full names, sorted.
     */
    public static function changed_by_hand_names(int $quizid): array {
        global $DB;
        $userids = scorer::changed_by_hand($quizid);
        if (!$userids) {
            return [];
        }
        $names = array_map(fn($user) => fullname($user), $DB->get_records_list('user', 'id', $userids));
        sort($names);
        return $names;
    }
}
