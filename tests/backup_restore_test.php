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

use backup;
use backup_controller;
use local_bandedgrade\local\bands;
use local_bandedgrade\local\gradebook;
use local_bandedgrade\local\quiz_config;
use restore_controller;
use restore_dbops;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once(__DIR__ . '/../backup/moodle2/backup_local_bandedgrade_plugin.class.php');
require_once(__DIR__ . '/../backup/moodle2/restore_local_bandedgrade_plugin.class.php');

/**
 * Backup and restore of the "Grade by number correct" settings.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\backup_local_bandedgrade_plugin::class)]
#[CoversClass(\restore_local_bandedgrade_plugin::class)]
final class backup_restore_test extends \advanced_testcase {
    use \local_bandedgrade\tests\quiz_trait;

    protected function setUp(): void {
        parent::setUp();
        $this->setup_course();
    }

    /**
     * Our score columns in a course.
     *
     * @param int $courseid Course id.
     * @return \stdClass[] Grade item rows.
     */
    private function score_items(int $courseid): array {
        global $DB;
        return $DB->get_records_select('grade_items', "courseid = ? AND itemtype = 'manual' AND " .
            $DB->sql_like('itemname', '?'), [$courseid, '% – score']);
    }

    /**
     * A 4-question quiz with bands 0 → 0, 2 → 1, 4 → 2, a student with 4 correct (score 2) and a student with
     * 2 correct whose score a teacher changed by hand to 0.5.
     *
     * @return array [quiz, first student, second student].
     */
    private function quiz_with_two_scores(): array {
        $quiz = $this->make_frog_quiz(4);
        $this->enable($quiz, [0 => 0, 2 => 1, 4 => 2]);
        $one = $this->getDataGenerator()->create_and_enrol($this->course);
        $two = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->attempt($quiz, $one, $this->frogs(4, 4));
        $this->attempt($quiz, $two, $this->frogs(2, 4));
        $this->run_tasks();
        $this->item($quiz)->update_final_grade($two->id, 0.5, 'gradebook');
        return [$quiz, $one, $two];
    }

    /**
     * The only quiz in a course.
     *
     * @param int $courseid Course id.
     * @return \stdClass The quiz row (with cmid).
     */
    private function only_quiz(int $courseid): \stdClass {
        global $DB;
        $quiz = $DB->get_record('quiz', ['course' => $courseid], '*', MUST_EXIST);
        $quiz->cmid = get_coursemodule_from_instance('quiz', $quiz->id)->id;
        return $quiz;
    }

    public function test_full_course_backup_and_restore_with_users(): void {
        global $CFG, $USER;
        [$quiz, $one, $two] = $this->quiz_with_two_scores();

        $CFG->keeptempdirectoriesonbackup = true;
        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $this->course->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value(true);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        $newcourseid = restore_dbops::create_new_course('Restored', 'R1', $this->course->category);
        $rc = new restore_controller(
            $backupid,
            $newcourseid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_NEW_COURSE
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        $newquiz = $this->only_quiz($newcourseid);
        $config = quiz_config::get($newquiz->id);
        $this->assertNotNull($config);
        $this->assertEquals(1, $config->enabled);
        $this->assertSame([0, 2, 4], array_column($config->bands, 'from'));

        // The restored score column is reused: one column, the one the gradebook restore made.
        $items = $this->score_items($newcourseid);
        $this->assertCount(1, $items);
        $this->assertEquals(reset($items)->id, $config->gradeitemid);

        // After the rescore, the band score is there and the change by hand is still kept.
        $this->run_tasks();
        $this->assertEquals(2, $this->score($newquiz, $one->id));
        $this->assertEquals(0.5, $this->score($newquiz, $two->id));
        $this->assertEquals(0, gradebook::quiz_item($newquiz)->aggregationcoef2);
    }

    /**
     * Copy the course with the "Copy course" feature (backup::MODE_COPY), the way course/copy.php does.
     *
     * @param bool $userdata Include student data (and keep the student role).
     * @return int The new course id.
     */
    private function copy_course(bool $userdata): int {
        global $DB;
        $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        $formdata = (object)[
            'courseid' => $this->course->id,
            'fullname' => 'Copy',
            'shortname' => 'COPY1',
            'category' => $this->course->category,
            'visible' => 1,
            'startdate' => $this->course->startdate,
            'enddate' => 0,
            'idnumber' => '',
            'userdata' => (int)$userdata,
            'role_' . $studentrole => $studentrole,
        ];
        $result = \copy_helper::create_copy(\copy_helper::process_formdata($formdata));
        $newcourseid = \restore_controller::load_controller($result['restoreid'])->get_courseid();
        $this->run_tasks(); // The copy task, which queues our rescore.
        $this->run_tasks(); // Our rescore.
        return (int)$newcourseid;
    }

    public function test_course_copy_with_user_data(): void {
        [$quiz, $one, $two] = $this->quiz_with_two_scores();

        $newcourseid = $this->copy_course(true);

        $copy = $this->only_quiz($newcourseid);
        $config = quiz_config::get($copy->id);
        $this->assertEquals(1, $config->enabled);
        $this->assertSame([0, 2, 4], array_column($config->bands, 'from'));
        $items = $this->score_items($newcourseid);
        $this->assertCount(1, $items, 'The copied column is reused, not doubled.');
        $this->assertEquals(reset($items)->id, $config->gradeitemid);
        $this->assertEquals(2, $this->score($copy, $one->id));
        $this->assertEquals(0.5, $this->score($copy, $two->id), 'The change by hand is still kept.');
        $this->assertEquals(0, gradebook::quiz_item($copy)->aggregationcoef2);
        $this->assertCount(1, $this->score_items($this->course->id), 'The original course is untouched.');
        $this->assertEquals(2, $this->score($quiz, $one->id));
    }

    public function test_course_copy_without_user_data(): void {
        global $DB;
        $quiz = $this->make_frog_quiz(2);
        $this->enable($quiz, [0 => 0, 1 => 1, 2 => 2]);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->attempt($quiz, $student, $this->frogs(2, 2));

        $newcourseid = $this->copy_course(false);

        $copy = $this->only_quiz($newcourseid);
        $config = quiz_config::get($copy->id);
        $this->assertEquals(1, $config->enabled);
        $this->assertCount(1, $this->score_items($newcourseid));
        $this->assertSame(0, $DB->count_records('quiz_attempts', ['quiz' => $copy->id]));
        $this->assertSame(0, $DB->count_records('local_bandedgrade_written', ['quizid' => $copy->id]));
        $this->assertSame(0, $DB->count_records_select(
            'grade_grades',
            'itemid = ? AND finalgrade IS NOT NULL',
            [$config->gradeitemid]
        ), 'No scores without student data.');
    }

    public function test_course_copy_keeps_the_pass_fail_scheme(): void {
        $quiz = $this->make_frog_quiz(4);
        $this->enable_passfail($quiz, '3', '5', '1');

        $newcourseid = $this->copy_course(false);

        $copy = $this->only_quiz($newcourseid);
        $config = quiz_config::get($copy->id);
        $this->assertSame('passfail', $config->scheme);
        $this->assertSame([3, 5.0, 1.0], bands::to_passfail($config->bands));
        $this->assertEquals(5, gradebook::get_item($config)->gradepass, 'The grade to pass comes with the column.');
    }

    public function test_course_copy_maps_the_required_questions(): void {
        global $DB;
        $quiz = $this->make_frog_quiz(3);
        $this->enable($quiz, [0 => 0, 2 => 1], true, true, 'bands', [2]);
        $oldids = quiz_config::get($quiz->id)->requiredslots;

        $newcourseid = $this->copy_course(false);

        $copy = $this->only_quiz($newcourseid);
        $required = quiz_config::get($copy->id)->requiredslots;
        $this->assertCount(1, $required);
        $this->assertNotEquals($oldids, $required, 'The copy has its own slots.');
        $this->assertEquals(2, $DB->get_field('quiz_slots', 'slot', ['id' => $required[0], 'quizid' => $copy->id]));
    }

    public function test_duplicate_quiz_gets_its_own_column(): void {
        $quiz = $this->make_frog_quiz(2);
        $this->enable($quiz, [0 => 0, 1 => 1, 2 => 2]);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->attempt($quiz, $student, $this->frogs(2, 2));

        $newcm = duplicate_module($this->course, get_fast_modinfo($this->course)->get_cm($quiz->cmid));

        $copy = (object)['id' => $newcm->instance, 'course' => $this->course->id, 'name' => $newcm->name,
            'cmid' => $newcm->id];
        $config = quiz_config::get($copy->id);
        $this->assertNotNull($config);
        $this->assertSame([0, 1, 2], array_column($config->bands, 'from'));
        $this->assertNotEquals(quiz_config::get($quiz->id)->gradeitemid, $config->gradeitemid);
        $this->assertCount(2, $this->score_items($this->course->id), 'One column per quiz.');
        $this->assertEquals(1, gradebook::quiz_item($copy)->weightoverride, 'The copy\'s own grade has weight 0 too.');

        // The copy has no attempts, so nobody gets a score in it.
        $this->run_tasks();
        $this->assertNull($this->score($copy, $student->id));
        $this->assertEquals(2, $this->score($quiz, $student->id));
    }

    public function test_import_quiz_into_another_course(): void {
        global $USER;
        $quiz = $this->make_frog_quiz(2);
        $this->enable($quiz, [0 => 0, 2 => 5]);
        $othercourse = $this->getDataGenerator()->create_course();

        $bc = new backup_controller(
            backup::TYPE_1ACTIVITY,
            $quiz->cmid,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();
        $rc = new restore_controller(
            $backupid,
            $othercourse->id,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id,
            backup::TARGET_CURRENT_ADDING
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        $copy = $this->only_quiz($othercourse->id);
        $config = quiz_config::get($copy->id);
        $this->assertEquals(5, $config->bands[1]['score']);
        $items = $this->score_items($othercourse->id);
        $this->assertCount(1, $items, 'A new column in the other course.');
        $this->assertEquals(reset($items)->id, $config->gradeitemid);
        $this->assertCount(1, $this->score_items($this->course->id), 'The original column is untouched.');
    }

    public function test_restore_into_course_with_grade_categories(): void {
        global $CFG, $DB, $USER;
        [$quiz, $one, $two] = $this->quiz_with_two_scores();

        // A course that already has its own grade category: core then skips the gradebook part of the restore
        // (restore_gradebook_structure_step::execute_condition()), so our column is not in what gets restored.
        $target = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_grade_category(['courseid' => $target->id, 'fullname' => 'Tests']);
        $this->assertSame(2, $DB->count_records('grade_categories', ['courseid' => $target->id]));

        $CFG->keeptempdirectoriesonbackup = true;
        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $this->course->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value(true);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();
        $rc = new restore_controller(
            $backupid,
            $target->id,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_EXISTING_ADDING
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        $copy = $this->only_quiz($target->id);
        $config = quiz_config::get($copy->id);
        $this->assertEquals(1, $config->enabled);
        $this->assertSame([0, 2, 4], array_column($config->bands, 'from'));
        $items = $this->score_items($target->id);
        $this->assertCount(1, $items, 'One new column, nothing left over.');
        $this->assertEquals(reset($items)->id, $config->gradeitemid);
        $this->assertSame(
            0,
            $DB->count_records('local_bandedgrade_written', ['quizid' => $copy->id]),
            'No last written scores without the restored grades.'
        );
        $this->assertEquals(0, gradebook::quiz_item($copy)->aggregationcoef2);

        // Scores come back from the restored attempts. The change made by hand is not carried over:
        // the gradebook that held it was not restored.
        $this->run_tasks();
        $this->assertEquals(2, $this->score($copy, $one->id));
        $this->assertEquals(1, $this->score($copy, $two->id));
        $this->assertCount(1, $this->score_items($this->course->id), 'The original course is untouched.');
        $this->assertEquals(0.5, $this->score($quiz, $two->id));
    }

    public function test_quiz_without_banded_grading_and_other_activities_are_untouched(): void {
        global $DB;
        $quiz = $this->make_frog_quiz(1);
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id]);

        duplicate_module($this->course, get_fast_modinfo($this->course)->get_cm($quiz->cmid));
        duplicate_module($this->course, get_fast_modinfo($this->course)->get_cm($forum->cmid));

        $this->assertSame(0, $DB->count_records('local_bandedgrade_quiz'));
        $this->assertCount(0, $this->score_items($this->course->id));
    }
}
