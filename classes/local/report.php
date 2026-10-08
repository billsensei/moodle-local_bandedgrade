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
 * The "Number correct" report: for each student, the number correct in each attempt, the number used and the score.
 *
 * Permission checks (login, capability) are done by report.php before calling this.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report {
    /**
     * The report rows for a quiz, limited to what the current user may see.
     *
     * Uses the counts the plugin stored when it last scored each student, so the numbers match the scores.
     * Students whose score was changed by hand are listed even without counted attempts.
     *
     * @param \stdClass $quiz The quiz row (id, grademethod).
     * @param \cm_info $cm The quiz course module.
     * @param \context_module $context The quiz context.
     * @param int $groupid Only members of this group, or 0 for everyone the user may see.
     * @return \stdClass[] Sorted by name; each has userid, name (not escaped), attempts (list of objects with correct
     *         and pending), used (float|null, the number that sets the score), score (float|null) and byhand (bool).
     */
    public static function rows(\stdClass $quiz, \cm_info $cm, \context_module $context, int $groupid): array {
        global $DB;
        $config = quiz_config::get_enabled((int)$quiz->id);
        if (!$config) {
            return [];
        }
        $counts = $DB->get_records_sql(
            'SELECT bga.id, bga.userid, bga.correctcount, bga.pending
               FROM {local_bandedgrade_attempt} bga
               JOIN {quiz_attempts} qa ON qa.id = bga.attemptid
              WHERE bga.quizid = :quizid
           ORDER BY qa.attempt ASC',
            ['quizid' => $quiz->id]
        );
        $byuser = [];
        foreach ($counts as $count) {
            $byuser[(int)$count->userid][] = (object)['correct' => (int)$count->correctcount, 'pending' => (bool)$count->pending];
        }
        $byhand = scorer::changed_by_hand((int)$quiz->id);
        $userids = array_unique(array_merge(array_keys($byuser), $byhand));

        $allowed = self::allowed_users($cm, $context, $groupid);
        if ($allowed !== null) {
            $userids = array_values(array_intersect($userids, $allowed));
        }
        if (!$userids) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($userids);
        $fields = 'id, ' . implode(', ', \core_user\fields::get_name_fields());
        $users = $DB->get_records_select('user', "id $insql AND deleted = 0", $params, '', $fields);
        $item = gradebook::get_item($config);
        $grades = $item ? $DB->get_records('grade_grades', ['itemid' => $item->id], '', 'userid, finalgrade') : [];

        $rows = [];
        foreach ($users as $user) {
            $attempts = $byuser[(int)$user->id] ?? [];
            $grade = $grades[$user->id] ?? null;
            $rows[] = (object)[
                'userid' => (int)$user->id,
                'name' => fullname($user),
                'attempts' => $attempts,
                'used' => scorer::choose_count((int)$quiz->grademethod, $attempts),
                'score' => ($grade && $grade->finalgrade !== null) ? (float)$grade->finalgrade : null,
                'byhand' => in_array((int)$user->id, $byhand, true),
            ];
        }
        \core_collator::asort_objects_by_property($rows, 'name', \core_collator::SORT_NATURAL);
        return array_values($rows);
    }

    /**
     * The users the current user may see in the report, following the quiz's group mode.
     *
     * @param \cm_info $cm The quiz course module.
     * @param \context_module $context The quiz context.
     * @param int $groupid The chosen group, or 0 for all.
     * @return int[]|null User ids, or null for no limit.
     */
    private static function allowed_users(\cm_info $cm, \context_module $context, int $groupid): ?array {
        if ($groupid) {
            return array_map('intval', array_keys(groups_get_members($groupid, 'u.id')));
        }
        if (groups_get_activity_groupmode($cm) == SEPARATEGROUPS && !has_capability('moodle/site:accessallgroups', $context)) {
            return []; // Not in any group: like core quiz reports, show nobody.
        }
        return null;
    }
}
