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
 * Looks after the plugin's own gradebook item and the weight of the quiz's own grade.
 *
 * Our item is a manual grade item (RESEARCH-bandedgrade.md §4). Core does not delete it with the quiz,
 * so delete_item() is called when the quiz is deleted.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradebook {
    /** @var string Source name written to the grade history. */
    const SOURCE = 'local_bandedgrade';

    /** @var string Weight result: done (or nothing to do). */
    const WEIGHT_DONE = 'done';

    /** @var string Weight result: the quiz has no grade item. */
    const WEIGHT_NOITEM = 'noitem';

    /** @var string Weight result: the category's aggregation has no per-item weight. */
    const WEIGHT_UNSUPPORTED = 'unsupported';

    /**
     * Load the gradebook library.
     */
    private static function require_lib(): void {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
    }

    /**
     * The quiz's own grade item.
     *
     * @param \stdClass $quiz The quiz row (needs id and course).
     * @return \grade_item|null The item, or null if the quiz has no grade.
     */
    public static function quiz_item(\stdClass $quiz): ?\grade_item {
        self::require_lib();
        $item = \grade_item::fetch(['courseid' => $quiz->course, 'itemtype' => 'mod', 'itemmodule' => 'quiz',
            'iteminstance' => $quiz->id, 'itemnumber' => 0]);
        return $item ?: null;
    }

    /**
     * Our grade item, if it still exists.
     *
     * @param \stdClass $config The quiz settings.
     * @return \grade_item|null The item.
     */
    public static function get_item(\stdClass $config): ?\grade_item {
        self::require_lib();
        if (empty($config->gradeitemid)) {
            return null;
        }
        $item = \grade_item::fetch(['id' => $config->gradeitemid, 'courseid' => $config->courseid]);
        return $item ?: null;
    }

    /**
     * Create our grade item, or bring its name and maximum up to date.
     *
     * The item is recreated if it was removed (for example by a course reset).
     *
     * @param \stdClass $config The quiz settings (gradeitemid is updated).
     * @param \stdClass $quiz The quiz row (needs id, course and name).
     * @return \grade_item The item.
     */
    public static function ensure_item(\stdClass $config, \stdClass $quiz): \grade_item {
        self::require_lib();
        $name = get_string('itemname', 'local_bandedgrade', $quiz->name);
        $max = bands::max_score($config->bands);

        $item = self::get_item($config);
        if (!$item) {
            $quizitem = self::quiz_item($quiz);
            $item = new \grade_item([
                'courseid' => $quiz->course,
                'itemtype' => 'manual',
                'itemname' => $name,
                'gradetype' => GRADE_TYPE_VALUE,
                'grademin' => 0,
                'grademax' => $max,
                'iteminfo' => get_string('iteminfo', 'local_bandedgrade'),
            ], false); // False: always a new item, even if another quiz has the same name.
            if ($quizitem) {
                $item->categoryid = $quizitem->categoryid;
            }
            $item->insert(self::SOURCE);
            if ($quizitem) {
                $item->move_after_sortorder($quizitem->sortorder);
            }
            quiz_config::set_grade_item($quiz->id, $item->id);
            $config->gradeitemid = $item->id;
            return $item;
        }

        if ($item->itemname !== $name || grade_floats_different($item->grademax, $max)) {
            $item->itemname = $name;
            $item->grademax = $max;
            $item->update(self::SOURCE);
        }
        return $item;
    }

    /**
     * Delete our grade item and its grades.
     *
     * @param \stdClass $config The quiz settings.
     */
    public static function delete_item(\stdClass $config): void {
        $item = self::get_item($config);
        if ($item) {
            $item->delete(self::SOURCE);
        }
    }

    /**
     * Set the quiz's own grade weight to 0, or undo that.
     *
     * Only Natural and Weighted mean have a weight per item (RESEARCH-bandedgrade.md §5). When undoing, only a
     * weight of exactly 0 that we could have set is changed back; a teacher's own weights are left alone.
     *
     * @param \stdClass $quiz The quiz row (needs id and course).
     * @param bool $zero True to set weight 0, false to undo it.
     * @return string One of the WEIGHT_ constants.
     */
    public static function set_quiz_weight(\stdClass $quiz, bool $zero): string {
        $quizitem = self::quiz_item($quiz);
        if (!$quizitem) {
            return self::WEIGHT_NOITEM;
        }
        $category = $quizitem->get_parent_category();
        $changed = false;

        if ($category->aggregation == GRADE_AGGREGATE_SUM) {
            $iszero = $quizitem->weightoverride && !grade_floats_different($quizitem->aggregationcoef2, 0);
            if ($zero && !$iszero) {
                $quizitem->weightoverride = 1;
                $quizitem->aggregationcoef2 = 0;
                $changed = true;
            } else if (!$zero && $iszero) {
                $quizitem->weightoverride = 0;
                $changed = true;
            }
        } else if ($category->aggregation == GRADE_AGGREGATE_WEIGHTED_MEAN) {
            $iszero = !grade_floats_different($quizitem->aggregationcoef, 0);
            if ($zero && !$iszero) {
                $quizitem->aggregationcoef = 0;
                $changed = true;
            } else if (!$zero && $iszero) {
                $quizitem->aggregationcoef = 1;
                $changed = true;
            }
        } else {
            return $zero ? self::WEIGHT_UNSUPPORTED : self::WEIGHT_DONE;
        }

        if ($changed) {
            $quizitem->update(self::SOURCE);
        }
        return self::WEIGHT_DONE;
    }
}
