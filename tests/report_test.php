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

use local_bandedgrade\local\report;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the "Number correct" report.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(report::class)]
#[CoversClass(event\report_viewed::class)]
final class report_test extends \advanced_testcase {
    use \local_bandedgrade\tests\quiz_trait;

    protected function setUp(): void {
        parent::setUp();
        $this->setup_course();
    }

    /**
     * The report rows for a quiz, as the current user.
     *
     * @param \stdClass $quiz The quiz.
     * @param int $groupid Group filter.
     * @return \stdClass[] Rows.
     */
    private function rows(\stdClass $quiz, int $groupid = 0): array {
        $cm = get_fast_modinfo($this->course)->get_cm($quiz->cmid);
        return report::rows($quiz, $cm, \context_module::instance($cm->id), $groupid);
    }

    public function test_counts_per_attempt_number_used_and_score(): void {
        $quiz = $this->make_frog_quiz(4, ['grademethod' => QUIZ_GRADEAVERAGE]);
        $this->enable($quiz, [0 => 0, 2 => 1, 4 => 2]);
        $ann = $this->getDataGenerator()->create_and_enrol($this->course, 'student', ['firstname' => 'Ann', 'lastname' => 'A']);
        $bob = $this->getDataGenerator()->create_and_enrol($this->course, 'student', ['firstname' => 'Bob', 'lastname' => 'B']);
        $this->getDataGenerator()->create_and_enrol($this->course, 'student', ['firstname' => 'Cy', 'lastname' => 'C']);
        $this->attempt($quiz, $bob, $this->frogs(4, 4));
        $this->attempt($quiz, $ann, $this->frogs(1, 4));
        $this->attempt($quiz, $ann, $this->frogs(4, 4));

        $rows = $this->rows($quiz);
        $this->assertSame(['Ann A', 'Bob B'], array_column($rows, 'name'), 'Sorted by name; no row without attempts.');
        $this->assertSame([1, 4], array_column($rows[0]->attempts, 'correct'), 'In attempt order.');
        $this->assertEquals(2.5, $rows[0]->used, 'Average of 1 and 4.');
        $this->assertEquals(1, $rows[0]->score);
        $this->assertFalse($rows[0]->byhand);
        $this->assertEquals(4, $rows[1]->used);
        $this->assertEquals(2, $rows[1]->score);

        $this->item($quiz)->update_final_grade($bob->id, 0.5, 'gradebook');
        $this->assertTrue($this->rows($quiz)[1]->byhand);
    }

    public function test_attempt_waiting_for_marking(): void {
        $quiz = $this->make_frog_quiz(1);
        $this->add_question($quiz, 'essay', 'plain');
        $this->enable($quiz, [0 => 0, 1 => 1]);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->attempt($quiz, $student, [1 => 'frog', 2 => 'My essay']);

        [$row] = $this->rows($quiz);
        $this->assertTrue($row->attempts[0]->pending);
        $this->assertNull($row->used);
        $this->assertNull($row->score);
    }

    public function test_groups(): void {
        global $DB;
        $quiz = $this->make_frog_quiz(1, ['groupmode' => SEPARATEGROUPS]);
        $this->enable($quiz, [0 => 0, 1 => 1]);
        $group1 = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $group2 = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $one = $this->getDataGenerator()->create_and_enrol($this->course, 'student', ['firstname' => 'One']);
        $two = $this->getDataGenerator()->create_and_enrol($this->course, 'student', ['firstname' => 'Two']);
        $this->getDataGenerator()->create_group_member(['groupid' => $group1->id, 'userid' => $teacher->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group1->id, 'userid' => $one->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group2->id, 'userid' => $two->id]);
        $this->attempt($quiz, $one, $this->frogs(1, 1));
        $this->attempt($quiz, $two, $this->frogs(1, 1));

        $this->assertCount(2, $this->rows($quiz), 'Admin, all groups.');
        $this->assertSame([(int)$two->id], array_column($this->rows($quiz, (int)$group2->id), 'userid'));

        // A non-editing teacher without "access all groups" sees only their own group.
        $role = $DB->get_field('role', 'id', ['shortname' => 'teacher']);
        unassign_capability('moodle/site:accessallgroups', $role);
        $this->setUser($teacher);
        $this->assertSame([], $this->rows($quiz), 'No group chosen: nobody.');
        $this->assertSame([(int)$one->id], array_column($this->rows($quiz, (int)$group1->id), 'userid'));
    }

    public function test_quiz_without_banded_grading(): void {
        $quiz = $this->make_frog_quiz(1);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->attempt($quiz, $student, $this->frogs(1, 1));
        $this->assertSame([], $this->rows($quiz));
    }

    public function test_viewed_event(): void {
        $quiz = $this->make_frog_quiz(1);
        $context = \context_module::instance($quiz->cmid);
        $sink = $this->redirectEvents();
        event\report_viewed::create(['objectid' => $quiz->id, 'context' => $context])->trigger();
        [$event] = $sink->get_events();
        $this->assertInstanceOf(event\report_viewed::class, $event);
        $this->assertSame($context->id, (int)$event->contextid);
        $this->assertStringContainsString((string)$quiz->cmid, $event->get_description());
        $this->assertEquals(new \moodle_url('/local/bandedgrade/report.php', ['cmid' => $quiz->cmid]), $event->get_url());
    }
}
