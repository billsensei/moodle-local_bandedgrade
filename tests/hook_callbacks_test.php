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
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the warning on the quiz page when the questions no longer reach every band.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(hook_callbacks::class)]
#[CoversClass(bands::class)]
final class hook_callbacks_test extends \advanced_testcase {
    use \local_bandedgrade\tests\quiz_trait;

    protected function setUp(): void {
        parent::setUp();
        $this->setup_course();
    }

    /**
     * Run the hook as if a quiz page were being printed, and return the notifications it added.
     *
     * @param \stdClass $quiz The quiz.
     * @param string $pagetype The page type.
     * @return string[] Notification messages.
     */
    private function warnings_on(\stdClass $quiz, string $pagetype = 'mod-quiz-view'): array {
        global $PAGE;
        $PAGE = new \moodle_page();
        $PAGE->set_cm(get_fast_modinfo($this->course)->get_cm($quiz->cmid));
        $PAGE->set_pagetype($pagetype);
        \core\notification::fetch(); // Start empty.
        hook_callbacks::before_http_headers(new \core\hook\output\before_http_headers($PAGE->get_renderer('core')));
        return array_map(fn($notification) => $notification->get_message(), \core\notification::fetch());
    }

    /**
     * Remove the last question from a quiz.
     *
     * @param \stdClass $quiz The quiz.
     */
    private function remove_last_question(\stdClass $quiz): void {
        $structure = \mod_quiz\quiz_settings::create($quiz->id)->get_structure();
        $structure->remove_slot($structure->get_last_slot()->slot);
    }

    public function test_first_unreachable(): void {
        $bands = [['from' => 0, 'score' => 0], ['from' => 5, 'score' => 1], ['from' => 8, 'score' => 2]];
        $this->assertNull(bands::first_unreachable($bands, 8));
        $this->assertSame(8, bands::first_unreachable($bands, 7));
        $this->assertSame(5, bands::first_unreachable($bands, 4));
        $this->assertNull(bands::first_unreachable($bands, 0), 'No questions yet: nothing to warn about.');
    }

    public function test_warning_after_questions_are_removed(): void {
        $quiz = $this->make_frog_quiz(3);
        $this->enable($quiz, [0 => 0, 2 => 1, 3 => 2]);
        $this->assertSame([], $this->warnings_on($quiz));

        $this->remove_last_question($quiz);
        [$message] = $this->warnings_on($quiz);
        $this->assertStringContainsString('this quiz now has 2 questions', $message);
        $this->assertStringContainsString('the band that starts at 3 correct', $message);
        $this->assertStringContainsString('course/modedit.php?update=' . $quiz->cmid, $message);
        $this->assertCount(1, $this->warnings_on($quiz, 'mod-quiz-edit'), 'Also on the Questions page.');
        $this->assertSame([], $this->warnings_on($quiz, 'mod-quiz-attempt'), 'Not while students take the quiz.');
    }

    public function test_only_people_who_can_edit_the_quiz_see_it(): void {
        $quiz = $this->make_frog_quiz(1);
        $this->enable($quiz, [0 => 0, 1 => 1, 2 => 2]);
        $this->assertCount(1, $this->warnings_on($quiz));
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'student'));
        $this->assertSame([], $this->warnings_on($quiz));
    }

    public function test_no_warning_when_turned_off(): void {
        $quiz = $this->make_frog_quiz(1);
        $this->enable($quiz, [0 => 0, 1 => 1, 2 => 2], true, false);
        $this->assertSame([], $this->warnings_on($quiz));
    }

    /**
     * Run the grader report footer hook and return the colour data it adds.
     *
     * @return array Item id => user id => [css class, title].
     */
    private function grader_colours(): array {
        global $PAGE;
        $PAGE = new \moodle_page();
        $PAGE->set_course($this->course);
        $PAGE->set_pagetype('grade-report-grader-index');
        $hook = new \core\hook\output\before_standard_footer_html_generation($PAGE->get_renderer('core'));
        hook_callbacks::before_standard_footer_html_generation($hook);
        if (!preg_match('/data-colours="([^"]*)"/', $hook->get_output(), $matches)) {
            return [];
        }
        return json_decode(html_entity_decode($matches[1]), true);
    }

    public function test_grader_report_colours_need_the_quiz_report_capability(): void {
        $quiz = $this->make_frog_quiz(5);
        $this->enable($quiz, [0 => 0, 1 => 1, 3 => 2]);
        $student = $this->getDataGenerator()->create_and_enrol($this->course);
        $this->attempt($quiz, $student, $this->frogs(4, 5));

        // A grader who can see all grades but not the quiz reports.
        $roleid = $this->getDataGenerator()->create_role();
        $coursecontext = \context_course::instance($this->course->id);
        assign_capability('moodle/grade:viewall', CAP_ALLOW, $roleid, $coursecontext->id);
        assign_capability('mod/quiz:view', CAP_ALLOW, $roleid, $coursecontext->id);
        $grader = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($grader->id, $this->course->id, $roleid);
        $this->setUser($grader);
        $this->assertSame([], $this->grader_colours(), 'No colours without mod/quiz:viewreports.');

        // The same person with the capability sees the student (4 of 5 correct = 80%).
        assign_capability('mod/quiz:viewreports', CAP_ALLOW, $roleid, $coursecontext->id);
        accesslib_clear_all_caches_for_unit_testing();
        $colours = $this->grader_colours();
        $this->assertCount(1, $colours);
        $this->assertArrayHasKey($student->id, reset($colours));
        $this->assertStringContainsString('80', reset($colours)[$student->id][1]);
    }

    public function test_grader_report_colours_follow_the_course_group_mode(): void {
        global $DB;
        $quiz = $this->make_frog_quiz(5); // The quiz itself has no groups.
        $this->enable($quiz, [0 => 0, 1 => 1, 3 => 2]);
        $DB->set_field('course', 'groupmode', SEPARATEGROUPS, ['id' => $this->course->id]);
        rebuild_course_cache($this->course->id, true);
        $this->course = get_course($this->course->id);

        $generator = $this->getDataGenerator();
        $mine = $generator->create_and_enrol($this->course);
        $other = $generator->create_and_enrol($this->course);
        $this->attempt($quiz, $mine, $this->frogs(4, 5));
        $this->attempt($quiz, $other, $this->frogs(5, 5));
        $groupa = $generator->create_group(['courseid' => $this->course->id]);
        $groupb = $generator->create_group(['courseid' => $this->course->id]);
        $generator->create_group_member(['groupid' => $groupa->id, 'userid' => $mine->id]);
        $generator->create_group_member(['groupid' => $groupb->id, 'userid' => $other->id]);

        // A non-editing teacher who may see the quiz reports but not every group.
        $teacher = $generator->create_and_enrol($this->course, 'teacher');
        $role = $DB->get_record('role', ['shortname' => 'teacher'], '*', MUST_EXIST);
        $coursecontext = \context_course::instance($this->course->id);
        assign_capability('mod/quiz:viewreports', CAP_ALLOW, $role->id, $coursecontext->id);
        assign_capability('moodle/site:accessallgroups', CAP_PREVENT, $role->id, $coursecontext->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($teacher);
        $this->assertSame([], $this->grader_colours(), 'In no group: nobody.');

        $generator->create_group_member(['groupid' => $groupa->id, 'userid' => $teacher->id]);
        $all = $this->grader_colours();
        $colours = reset($all);
        $this->assertSame([(int)$mine->id], array_map('intval', array_keys($colours)), 'Only the teacher\'s own group.');
    }
}
