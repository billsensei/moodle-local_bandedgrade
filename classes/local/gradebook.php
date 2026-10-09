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
     * The words of a gradebook scale, if the course may use it.
     *
     * @param int|null $scaleid The scale id.
     * @param int $courseid The course (a scale belongs to the site or to one course).
     * @return string[]|null The words in order, or null when there is no such usable scale (or it has fewer than 2 words).
     */
    public static function scale_items(?int $scaleid, int $courseid): ?array {
        self::require_lib();
        if (!$scaleid) {
            return null;
        }
        $scale = \grade_scale::fetch(['id' => $scaleid]);
        if (!$scale || ($scale->courseid != 0 && $scale->courseid != $courseid)) {
            return null;
        }
        $items = $scale->load_items();
        return count($items) >= 2 ? array_values($items) : null;
    }

    /**
     * A score as shown to teachers: the word of the scale for a scale column, else the number.
     *
     * @param \grade_item|null $item Our grade item.
     * @param float $score The score (a position in the scale for a scale column).
     * @return string The text.
     */
    public static function format_score(?\grade_item $item, float $score): string {
        if ($item && (int)$item->gradetype === GRADE_TYPE_SCALE) {
            $item->load_scale();
            $position = (int)round($score);
            if ($item->scale && isset($item->scale->scale_items[$position - 1])) {
                return s($item->scale->scale_items[$position - 1]);
            }
        }
        return format_float($score, -1);
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
        global $DB;
        self::require_lib();
        $name = get_string('itemname', 'local_bandedgrade', $quiz->name);
        $scaleitems = self::scale_items($config->scaleid ?? null, (int)$quiz->course);
        $max = $scaleitems ? count($scaleitems) : bands::max_score($config->bands);
        $gradepass = self::pass_grade($config);

        $item = self::get_item($config);
        if (!$item) {
            $quizitem = self::quiz_item($quiz);
            $item = new \grade_item([
                'courseid' => $quiz->course,
                'itemtype' => 'manual',
                'itemname' => $name,
                'gradetype' => $scaleitems ? GRADE_TYPE_SCALE : GRADE_TYPE_VALUE,
                'scaleid' => $scaleitems ? (int)$config->scaleid : null,
                'grademin' => $scaleitems ? 1 : 0,
                'grademax' => $max,
                'gradepass' => $gradepass ?? 0,
                'iteminfo' => get_string('iteminfo', 'local_bandedgrade'),
                'hidden' => self::quiz_hidden($quizitem),
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
            // A new, empty column: what we wrote to an old one says nothing about it. Without this every student
            // would look "cleared by hand" (scorer::is_changed_by_hand()).
            $DB->delete_records('local_bandedgrade_written', ['quizid' => $quiz->id]);
            return $item;
        }

        // The grade to pass is ours only in the pass/fail scheme; with bands a teacher may set their own.
        $passchanged = $gradepass !== null && grade_floats_different($item->gradepass, $gradepass);
        $type = $scaleitems ? GRADE_TYPE_SCALE : GRADE_TYPE_VALUE;
        $scaleid = $scaleitems ? (int)$config->scaleid : null;
        $typechanged = (int)$item->gradetype !== $type || (int)$item->scaleid !== (int)$scaleid;
        if ($item->itemname !== $name || grade_floats_different($item->grademax, $max) || $passchanged || $typechanged) {
            $item->itemname = $name;
            $item->gradetype = $type;
            $item->scaleid = $scaleid;
            $item->grademin = $scaleitems ? 1 : 0;
            $item->grademax = $max;
            if ($passchanged) {
                $item->gradepass = $gradepass;
            }
            $item->update(self::SOURCE);
        }
        self::sync_hidden($item, $quiz);
        return $item;
    }

    /**
     * The "grade to pass" of our column in the pass/fail scheme: the pass score, so Moodle shows pass and fail and
     * "require passing grade" works.
     *
     * @param \stdClass $config The quiz settings.
     * @return float|null The grade to pass, 0 if a pass does not score more than a fail, or null with bands (not ours).
     */
    public static function pass_grade(\stdClass $config): ?float {
        $passfail = $config->scheme === bands::SCHEME_PASSFAIL ? bands::to_passfail($config->bands) : null;
        if (!$passfail) {
            return null;
        }
        return $passfail[1] > $passfail[2] ? (float)$passfail[1] : 0.0;
    }

    /**
     * Take back the grade to pass that the pass/fail scheme set (when a quiz goes back to bands).
     *
     * @param \stdClass $config The quiz settings.
     */
    public static function clear_pass_grade(\stdClass $config): void {
        $item = self::get_item($config);
        if ($item && grade_floats_different($item->gradepass, 0)) {
            $item->gradepass = 0;
            $item->update(self::SOURCE);
        }
    }

    /**
     * The hidden value our column should have: the same as the quiz's own grade.
     *
     * Core hides the quiz grade while marks may not be reviewed (a timestamp: hidden until the quiz closes) and while
     * the quiz is hidden (mod/quiz/lib.php quiz_grade_item_update()). Without a quiz grade item, stay hidden.
     *
     * @param \grade_item|null $quizitem The quiz's own grade item.
     * @return int 0 visible, 1 hidden, or a time until which it is hidden.
     */
    private static function quiz_hidden(?\grade_item $quizitem): int {
        return $quizitem ? (int)$quizitem->hidden : 1;
    }

    /**
     * Make our column hidden exactly when (and until when) the quiz's own grade is hidden.
     *
     * @param \grade_item $item Our column.
     * @param \stdClass $quiz The quiz row (needs id and course).
     */
    public static function sync_hidden(\grade_item $item, \stdClass $quiz): void {
        $hidden = self::quiz_hidden(self::quiz_item($quiz));
        if ((int)$item->hidden !== $hidden) {
            $item->set_hidden($hidden);
        }
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
