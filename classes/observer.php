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

use local_bandedgrade\local\gradebook;
use local_bandedgrade\local\quiz_config;
use local_bandedgrade\local\scorer;
use local_bandedgrade\task\rescore_quiz;

/**
 * Event observers: keep scores up to date when attempts, questions or courses change.
 *
 * Which events and why: RESEARCH-bandedgrade.md §1.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * An attempt was marked, regraded, reopened or deleted: rescore that student.
     *
     * @param \core\event\base $event attempt_graded, attempt_regraded, attempt_reopened, attempt_deleted or
     *     attempt_manual_grading_completed.
     */
    public static function attempt_changed(\core\event\base $event): void {
        $quizid = (int)($event->other['quizid'] ?? 0);
        if ($quizid && $event->relateduserid && quiz_config::get_enabled($quizid)) {
            scorer::rescore_user($quizid, (int)$event->relateduserid);
        }
    }

    /**
     * A teacher marked one question by hand: rescore that student.
     *
     * @param \mod_quiz\event\question_manually_graded $event The event (attempt id is in other).
     */
    public static function question_manually_graded(\mod_quiz\event\question_manually_graded $event): void {
        global $DB;
        $quizid = (int)$event->other['quizid'];
        if (!quiz_config::get_enabled($quizid)) {
            return;
        }
        $userid = $DB->get_field('quiz_attempts', 'userid', ['id' => $event->other['attemptid']]);
        if ($userid) {
            scorer::rescore_user($quizid, (int)$userid);
        }
    }

    /**
     * Questions were added or removed, or a mark changed to or from 0: rescore the quiz in the background.
     *
     * @param \core\event\base $event slot_created, slot_deleted or slot_mark_updated.
     */
    public static function slots_changed(\core\event\base $event): void {
        $quizid = (int)($event->other['quizid'] ?? 0);
        if ($event instanceof \mod_quiz\event\slot_mark_updated) {
            $before = (float)$event->other['previousmaxmark'] > 0;
            $after = (float)$event->other['newmaxmark'] > 0;
            if ($before === $after) {
                return;
            }
        }
        if ($quizid && quiz_config::get_enabled($quizid)) {
            rescore_quiz::queue($quizid);
        }
    }

    /**
     * A quiz was deleted: remove our grade item and our data. Core does not remove a manual grade item.
     *
     * @param \core\event\course_module_deleted $event The event.
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event): void {
        if ($event->other['modulename'] !== 'quiz') {
            return;
        }
        $quizid = (int)$event->other['instanceid'];
        $config = quiz_config::get($quizid);
        if ($config) {
            gradebook::delete_item($config);
            quiz_config::delete_quiz_data($quizid);
        }
    }

    /**
     * A course was reset: forget counts and written scores that the reset removed.
     *
     * @param \core\event\course_reset_ended $event The event (reset options are in other).
     */
    public static function course_reset_ended(\core\event\course_reset_ended $event): void {
        global $DB;
        $options = (array)($event->other['reset_options'] ?? []);
        $quizids = $DB->get_fieldset_select(quiz_config::TABLE, 'quizid', 'courseid = ?', [$event->courseid]);
        if (!$quizids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($quizids);
        if (!empty($options['reset_quiz_attempts'])) {
            $DB->delete_records_select('local_bandedgrade_attempt', "quizid $insql", $params);
            $DB->delete_records_select('local_bandedgrade_written', "quizid $insql", $params);
        }
        if (!empty($options['reset_gradebook_grades']) || !empty($options['reset_gradebook_items'])) {
            $DB->delete_records_select('local_bandedgrade_written', "quizid $insql", $params);
        }
        if (!empty($options['reset_gradebook_items'])) {
            $DB->set_field_select(quiz_config::TABLE, 'gradeitemid', null, "quizid $insql", $params);
        }
    }

    /**
     * A course was deleted: remove our data for its quizzes (core removes the grade items).
     *
     * @param \core\event\course_deleted $event The event.
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;
        $quizids = $DB->get_fieldset_select(quiz_config::TABLE, 'quizid', 'courseid = ?', [$event->courseid]);
        foreach ($quizids as $quizid) {
            quiz_config::delete_quiz_data((int)$quizid);
        }
    }
}
