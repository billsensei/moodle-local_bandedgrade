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

namespace local_bandedgrade\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider: the per-attempt counts and the last written scores, stored per quiz (module context).
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** @var string[] Our tables that hold data per student. */
    const USER_TABLES = ['local_bandedgrade_attempt', 'local_bandedgrade_written'];

    /**
     * Describe the personal data stored.
     *
     * @param collection $collection The collection to add to.
     * @return collection The collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_bandedgrade_attempt', [
            'attemptid' => 'privacy:metadata:attempt:attemptid',
            'userid' => 'privacy:metadata:attempt:userid',
            'correctcount' => 'privacy:metadata:attempt:correctcount',
            'pending' => 'privacy:metadata:attempt:pending',
            'timemodified' => 'privacy:metadata:attempt:timemodified',
        ], 'privacy:metadata:attempt');
        $collection->add_database_table('local_bandedgrade_written', [
            'userid' => 'privacy:metadata:written:userid',
            'score' => 'privacy:metadata:written:score',
            'timemodified' => 'privacy:metadata:written:timemodified',
        ], 'privacy:metadata:written');
        $collection->add_subsystem_link('core_grades', [], 'privacy:metadata:core_grades');
        return $collection;
    }

    /**
     * SQL that joins a quiz id column to its module context.
     *
     * @param string $quizfield The quiz id column, e.g. "t.quizid".
     * @return string SQL joins (needs param :modulename = 'quiz' and :contextlevel = CONTEXT_MODULE).
     */
    private static function context_join(string $quizfield): string {
        return "JOIN {modules} m ON m.name = :modulename
                JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = $quizfield
                JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel";
    }

    /**
     * The quiz id behind a module context, if it is a quiz.
     *
     * @param \context $context The context.
     * @return int|null The quiz id.
     */
    private static function quiz_id(\context $context): ?int {
        global $DB;
        if ($context->contextlevel != CONTEXT_MODULE) {
            return null;
        }
        $quizid = $DB->get_field_sql("SELECT cm.instance
                                        FROM {course_modules} cm
                                        JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                                       WHERE cm.id = ?", [$context->instanceid]);
        return $quizid ? (int)$quizid : null;
    }

    /**
     * Contexts (quizzes) where we hold data about a user.
     *
     * @param int $userid The user.
     * @return contextlist The contexts.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        foreach (self::USER_TABLES as $table) {
            $sql = "SELECT ctx.id
                      FROM {{$table}} t
                      " . self::context_join('t.quizid') . "
                     WHERE t.userid = :userid";
            $contextlist->add_from_sql(
                $sql,
                ['modulename' => 'quiz', 'contextlevel' => CONTEXT_MODULE, 'userid' => $userid]
            );
        }
        return $contextlist;
    }

    /**
     * Users we hold data about in a context.
     *
     * @param userlist $userlist The list to fill.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $quizid = self::quiz_id($userlist->get_context());
        if (!$quizid) {
            return;
        }
        foreach (self::USER_TABLES as $table) {
            $userlist->add_from_sql('userid', "SELECT userid FROM {{$table}} WHERE quizid = :quizid", ['quizid' => $quizid]);
        }
    }

    /**
     * Export a user's data.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $quizid = self::quiz_id($context);
            if (!$quizid) {
                continue;
            }
            $attempts = $DB->get_records_sql("SELECT b.attemptid, qa.attempt, b.correctcount, b.pending, b.timemodified
                                                FROM {local_bandedgrade_attempt} b
                                           LEFT JOIN {quiz_attempts} qa ON qa.id = b.attemptid
                                               WHERE b.quizid = :quizid AND b.userid = :userid
                                            ORDER BY qa.attempt", ['quizid' => $quizid, 'userid' => $userid]);
            $written = $DB->get_record('local_bandedgrade_written', ['quizid' => $quizid, 'userid' => $userid]);
            if (!$attempts && !$written) {
                continue;
            }
            $data = (object)[
                'attempts' => array_values(array_map(fn($row) => (object)[
                    'attempt' => $row->attempt,
                    'correctcount' => $row->correctcount,
                    'pending' => transform::yesno($row->pending),
                    'timemodified' => transform::datetime($row->timemodified),
                ], $attempts)),
                'lastscore' => $written ? $written->score : null,
                'lastscoretime' => $written ? transform::datetime($written->timemodified) : null,
            ];
            writer::with_context($context)->export_data([get_string('pluginname', 'local_bandedgrade')], $data);
        }
    }

    /**
     * Delete everyone's data in a context.
     *
     * @param \context $context The context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        $quizid = self::quiz_id($context);
        if (!$quizid) {
            return;
        }
        foreach (self::USER_TABLES as $table) {
            $DB->delete_records($table, ['quizid' => $quizid]);
        }
    }

    /**
     * Delete one user's data in the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $quizid = self::quiz_id($context);
            if (!$quizid) {
                continue;
            }
            foreach (self::USER_TABLES as $table) {
                $DB->delete_records($table, ['quizid' => $quizid, 'userid' => $userid]);
            }
        }
    }

    /**
     * Delete several users' data in one context.
     *
     * @param approved_userlist $userlist The approved users.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $quizid = self::quiz_id($userlist->get_context());
        $userids = $userlist->get_userids();
        if (!$quizid || !$userids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['quizid'] = $quizid;
        foreach (self::USER_TABLES as $table) {
            $DB->delete_records_select($table, "quizid = :quizid AND userid $insql", $params);
        }
    }
}
