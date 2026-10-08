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
 * The "Recalculate scores" request: who may be overwritten, queue the rescore and log it.
 *
 * Permission checks (login, capability, sesskey) are done by recalculate.php before calling this.
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
     * @param int[] $overwriteuserids Students whose score changed by hand is replaced; empty to keep all such scores.
     */
    public static function request(\stdClass $quiz, \context_module $context, array $overwriteuserids = []): void {
        rescore_quiz::queue((int)$quiz->id, $overwriteuserids);
        scores_recalculated::create([
            'objectid' => $quiz->id,
            'context' => $context,
            'other' => ['overwrite' => $overwriteuserids ? 1 : 0],
        ])->trigger();
    }

    /**
     * Students whose score was changed by hand and whom the current user may see, with their names.
     *
     * In separate groups mode, without moodle/site:accessallgroups, only members of the user's own groups are
     * listed, so only they can be overwritten.
     *
     * @param int $quizid The quiz id.
     * @param \cm_info $cm The quiz course module.
     * @param \context_module $context The quiz context.
     * @return array<int, string> User id => full name (not escaped), sorted by name.
     */
    public static function changed_by_hand_users(int $quizid, \cm_info $cm, \context_module $context): array {
        global $DB;
        $userids = scorer::changed_by_hand($quizid);
        if (
            $userids && groups_get_activity_groupmode($cm) == SEPARATEGROUPS
                && !has_capability('moodle/site:accessallgroups', $context)
        ) {
            $members = [];
            foreach (array_keys(groups_get_activity_allowed_groups($cm)) as $groupid) {
                $members += groups_get_members($groupid, 'u.id');
            }
            $userids = array_values(array_intersect($userids, array_map('intval', array_keys($members))));
        }
        if (!$userids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($userids);
        $fields = 'id, ' . implode(', ', \core_user\fields::get_name_fields());
        $users = $DB->get_records_select('user', "id $insql AND deleted = 0", $params, '', $fields);
        $names = array_map(fn($user) => fullname($user), $users);
        asort($names);
        return $names;
    }
}
