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
use local_bandedgrade\local\gradebook;
use local_bandedgrade\local\quiz_config;
use local_bandedgrade\local\recalculate;
use local_bandedgrade\local\scorer;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/bandedgrade/db/uninstall.php');

/**
 * Regression tests for the fixes from the 2026-10-08 security audit and code review.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(gradebook::class)]
#[CoversClass(scorer::class)]
#[CoversClass(recalculate::class)]
#[CoversClass(observer::class)]
#[CoversClass(local\form_section::class)]
#[CoversClass(bands::class)]
final class hardening_test extends \advanced_testcase {
    use \local_bandedgrade\tests\quiz_trait;

    protected function setUp(): void {
        parent::setUp();
        $this->setup_course();
    }

    public function test_column_hidden_when_marks_are_not_reviewable(): void {
        // No "Marks" review option at all: core hides the quiz grade (quiz_grade_item_update()).
        $quiz = $this->make_frog_quiz(1, ['marksduring' => 0, 'marksimmediately' => 0, 'marksopen' => 0,
            'marksclosed' => 0]);
        $this->assertEquals(1, gradebook::quiz_item($quiz)->hidden);
        $this->enable($quiz, [0 => 0, 1 => 1]);
        $this->assertEquals(1, $this->item($quiz)->hidden);
    }

    public function test_column_follows_quiz_visibility(): void {
        $quiz = $this->make_frog_quiz(1);
        $this->enable($quiz, [0 => 0, 1 => 1]);
        $this->assertEquals(0, $this->item($quiz)->hidden);

        // What the course page does: hide the activity, then fire course_module_updated.
        set_coursemodule_visible($quiz->cmid, 0);
        \core\event\course_module_updated::create_from_cm(get_coursemodule_from_id('quiz', $quiz->cmid))->trigger();
        $this->assertEquals(1, $this->item($quiz)->hidden);

        set_coursemodule_visible($quiz->cmid, 1);
        \core\event\course_module_updated::create_from_cm(get_coursemodule_from_id('quiz', $quiz->cmid))->trigger();
        $this->assertEquals(0, $this->item($quiz)->hidden);
    }

    public function test_settings_need_gradebook_rights(): void {
        global $DB;
        $quiz = $this->make_frog_quiz(1);
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $role = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('moodle/grade:manage', CAP_PROHIBIT, $role, \context_course::instance($this->course->id));

        $this->setUser($teacher);
        $this->enable($quiz, [0 => 0, 1 => 1]);
        $this->setAdminUser();

        $this->assertNull(quiz_config::get($quiz->id), 'Nothing saved without moodle/grade:manage.');
        $this->assertEquals(0, gradebook::quiz_item($quiz)->weightoverride, 'Weights untouched.');
    }

    public function test_names_only_from_visible_groups_and_not_deleted(): void {
        $quiz = $this->make_frog_quiz(1, ['groupmode' => SEPARATEGROUPS]);
        $this->enable($quiz, [0 => 0, 1 => 1]);
        $ingroup = $this->getDataGenerator()->create_and_enrol($this->course, 'student', ['firstname' => 'In']);
        $outgroup = $this->getDataGenerator()->create_and_enrol($this->course, 'student', ['firstname' => 'Out']);
        $gone = $this->getDataGenerator()->create_and_enrol($this->course, 'student', ['firstname' => 'Gone']);
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher'); // No accessallgroups.
        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $ingroup->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $teacher->id]);
        foreach ([$ingroup, $outgroup, $gone] as $student) {
            $this->item($quiz)->update_final_grade($student->id, 0.5, 'gradebook');
        }
        delete_user($gone);
        $cm = get_fast_modinfo($this->course)->get_cm($quiz->cmid);
        $context = \context_module::instance($quiz->cmid);

        $this->assertSame(['In', 'Out'], array_map(
            fn($name) => explode(' ', $name)[0],
            array_values(recalculate::changed_by_hand_users($quiz->id, $cm, $context))
        ));
        $this->setUser($teacher);
        $this->assertSame([(int)$ingroup->id], array_keys(recalculate::changed_by_hand_users($quiz->id, $cm, $context)));
    }

    public function test_overwrite_only_the_listed_students(): void {
        $quiz = $this->make_frog_quiz(2);
        $this->enable($quiz, [0 => 0, 1 => 1, 2 => 2]);
        $one = $this->getDataGenerator()->create_and_enrol($this->course);
        $two = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->attempt($quiz, $one, $this->frogs(2, 2));
        $this->attempt($quiz, $two, $this->frogs(2, 2));
        $this->item($quiz)->update_final_grade($one->id, 0.5, 'gradebook');
        $this->item($quiz)->update_final_grade($two->id, 0.5, 'gradebook');

        $results = scorer::rescore_quiz($quiz->id, [(int)$one->id]);

        $this->assertSame([scorer::RESULT_WRITTEN => 1, scorer::RESULT_KEPT => 1], $results);
        $this->assertEquals(2, $this->score($quiz, $one->id));
        $this->assertEquals(0.5, $this->score($quiz, $two->id));
    }

    public function test_score_and_count_limits(): void {
        foreach (['100000', '1e3', '1.123456', '-1', '2,5,1'] as $score) {
            [, $errors] = bands::from_rows(['0', '1'], ['0', $score]);
            $this->assertArrayHasKey(1, $errors, "Score $score");
        }
        [$ok, $errors] = bands::from_rows(['0', '1'], ['0', '99999']);
        $this->assertSame([], $errors);
        [, $errors] = bands::from_rows(['0', '10001'], ['0', '1']);
        $this->assertArrayHasKey(1, $errors);

        $this->assertTrue(bands::are_valid($ok));
        $this->assertFalse(bands::are_valid([['from' => 3, 'score' => 1], ['from' => 5, 'score' => 2]]));
        $this->assertFalse(bands::are_valid([['from' => 0, 'score' => 0], ['from' => 1, 'score' => 1e9]]));
        $this->assertFalse(bands::are_valid([['nonsense' => 1]]));
    }

    public function test_score_cleared_by_hand_is_kept(): void {
        $quiz = $this->make_frog_quiz(2);
        $this->enable($quiz, [0 => 0, 1 => 1, 2 => 2]);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->attempt($quiz, $student, $this->frogs(1, 2));
        $this->assertEquals(1, $this->score($quiz, $student->id));

        $this->item($quiz)->update_final_grade($student->id, null, 'gradebook');
        $this->assertSame([(int)$student->id], scorer::changed_by_hand($quiz->id));

        $this->attempt($quiz, $student, $this->frogs(2, 2));
        $this->assertNull($this->score($quiz, $student->id), 'The blank stays.');
        $this->assertSame(scorer::RESULT_WRITTEN, scorer::rescore_user($quiz->id, $student->id, true));
        $this->assertEquals(2, $this->score($quiz, $student->id));
    }

    public function test_busy_quiz_hands_over_to_the_task(): void {
        global $CFG, $DB;
        // MySQL/MariaDB GET_LOCK() is re-entrant on one connection, and the test shares a connection with the code
        // it tests, so use the lock_db table to see a held lock as busy.
        $CFG->lock_factory = '\\core\\lock\\db_record_lock_factory';
        $quiz = $this->make_frog_quiz(1);
        $this->enable($quiz, [0 => 0, 1 => 1]);
        $this->run_tasks();
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $lock = \core\lock\lock_config::get_lock_factory('local_bandedgrade')->get_lock('quiz' . $quiz->id, 0);
        $this->assertNotFalse($lock);
        try {
            $this->assertSame(scorer::RESULT_QUEUED, scorer::rescore_user($quiz->id, $student->id));
            $this->assertSame(1, $DB->count_records(
                'task_adhoc',
                ['classname' => '\\local_bandedgrade\\task\\rescore_quiz']
            ));
            try {
                scorer::rescore_quiz($quiz->id);
                $this->fail('A whole-quiz rescore must not run alongside another one.');
            } catch (\moodle_exception $e) {
                $this->assertSame('locktimeout', $e->errorcode);
            }
        } finally {
            $lock->release();
        }
    }

    public function test_reset_of_attempts_only_blanks_the_scores(): void {
        $quiz = $this->make_frog_quiz(2);
        $this->enable($quiz, [0 => 0, 1 => 1, 2 => 2]);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->attempt($quiz, $student, $this->frogs(2, 2));
        $this->assertEquals(2, $this->score($quiz, $student->id));

        reset_course_userdata((object)['id' => $this->course->id, 'reset_quiz_attempts' => 1]);

        $this->assertNull($this->score($quiz, $student->id), 'Last term\'s score is gone with its attempts.');
        $this->assertSame([], scorer::changed_by_hand($quiz->id));
        $this->attempt($quiz, $student, $this->frogs(1, 2));
        $this->assertEquals(1, $this->score($quiz, $student->id), 'The new attempt is scored, not "kept".');
    }

    public function test_reset_of_gradebook_items_reapplies_weight(): void {
        $quiz = $this->make_frog_quiz(2);
        $this->enable($quiz, [0 => 0, 1 => 1, 2 => 2]);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->attempt($quiz, $student, $this->frogs(2, 2));
        $this->run_tasks();

        reset_course_userdata((object)['id' => $this->course->id, 'reset_gradebook_items' => 1]);

        $this->assertNotNull($this->item($quiz), 'A new column right away.');
        $this->assertEquals(1, gradebook::quiz_item($quiz)->weightoverride);
        $this->assertEquals(0, gradebook::quiz_item($quiz)->aggregationcoef2);
        $this->run_tasks();
        $this->assertEquals(2, $this->score($quiz, $student->id), 'Filled again from the attempts.');
    }

    public function test_new_column_forgets_old_written_scores(): void {
        $quiz = $this->make_frog_quiz(1);
        $this->enable($quiz, [0 => 0, 1 => 1]);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->attempt($quiz, $student, $this->frogs(1, 1));

        // A teacher deletes the column in the gradebook; the next rescore makes a new one.
        $this->item($quiz)->delete('gradebook');
        $this->assertSame(scorer::RESULT_WRITTEN, scorer::rescore_user($quiz->id, $student->id));
        $this->assertEquals(1, $this->score($quiz, $student->id));
    }

    public function test_uninstall_restores_weights_and_removes_columns(): void {
        global $DB;
        $quiz = $this->make_frog_quiz(1);
        $this->enable($quiz, [0 => 0, 1 => 1]);
        $itemid = quiz_config::get($quiz->id)->gradeitemid;
        $this->assertEquals(1, gradebook::quiz_item($quiz)->weightoverride);

        $this->assertTrue(xmldb_local_bandedgrade_uninstall());

        $this->assertFalse($DB->record_exists('grade_items', ['id' => $itemid]));
        $this->assertEquals(0, gradebook::quiz_item($quiz)->weightoverride);
    }
}
