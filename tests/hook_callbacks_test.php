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
}
