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

namespace local_bandedgrade\tests;

use local_bandedgrade\local\form_section;
use local_bandedgrade\local\gradebook;
use local_bandedgrade\local\quiz_config;

/**
 * Helpers for building quizzes, attempts and settings in tests.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait quiz_trait {
    /** @var \stdClass Test course. */
    protected $course;

    /** @var \stdClass Question category. */
    protected $category;

    /**
     * Load libraries and make a course and a question category.
     */
    protected function setup_course(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->libdir . '/gradelib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
        $this->category = $this->question_generator()->create_question_category();
    }

    /**
     * The question generator.
     *
     * @return \core_question_generator The generator.
     */
    protected function question_generator(): \core_question_generator {
        return $this->getDataGenerator()->get_plugin_generator('core_question');
    }

    /**
     * Make a quiz.
     *
     * @param array $options Quiz fields, e.g. grademethod, preferredbehaviour.
     * @return \stdClass The quiz.
     */
    protected function make_quiz(array $options = []): \stdClass {
        return $this->getDataGenerator()->create_module(
            'quiz',
            $options + ['course' => $this->course->id, 'grade' => 10, 'preferredbehaviour' => 'deferredfeedback']
        );
    }

    /**
     * Add a question to a quiz.
     *
     * @param \stdClass $quiz The quiz.
     * @param string $qtype Question type.
     * @param string|null $which Test question name.
     * @param float|null $maxmark Mark in the quiz (null = question default).
     * @param array $overrides Question field overrides.
     */
    protected function add_question(
        \stdClass $quiz,
        string $qtype = 'shortanswer',
        ?string $which = 'frogtoad',
        ?float $maxmark = 1.0,
        array $overrides = []
    ): void {
        $question = $this->question_generator()->create_question(
            $qtype,
            $which,
            $overrides + ['category' => $this->category->id]
        );
        quiz_add_quiz_question($question->id, $quiz, 0, $maxmark);
        \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
    }

    /**
     * Make a quiz with a number of 1-mark short-answer questions (right answer "frog").
     *
     * @param int $count Number of questions.
     * @param array $options Quiz fields.
     * @return \stdClass The quiz.
     */
    protected function make_frog_quiz(int $count, array $options = []): \stdClass {
        $quiz = $this->make_quiz($options);
        for ($i = 0; $i < $count; $i++) {
            $this->add_question($quiz);
        }
        return $quiz;
    }

    /**
     * Turn banded grading on through the same code the settings form uses.
     *
     * @param \stdClass $quiz The quiz.
     * @param array $bands from => score.
     * @param bool $zeroweight Count only the score in the course total.
     * @param bool $enabled On or off.
     */
    protected function enable(\stdClass $quiz, array $bands, bool $zeroweight = true, bool $enabled = true): void {
        $moduleinfo = (object)[
            'modulename' => 'quiz',
            'instance' => $quiz->id,
            'bandedgrade_enabled' => (int)$enabled,
            'bandedgrade_preset' => '',
            'bandedgrade_from' => array_map('strval', array_keys($bands)),
            'bandedgrade_score' => array_map('strval', array_values($bands)),
            'bandedgrade_zeroweight' => (int)$zeroweight,
        ];
        form_section::save($moduleinfo, $this->course);
    }

    /**
     * Start an attempt and submit answers, as the student.
     *
     * @param \stdClass $quiz The quiz.
     * @param \stdClass $user The student.
     * @param array $responses Slot => response summary, e.g. [1 => 'frog'].
     * @param bool $finish Submit (and mark) the attempt.
     * @return \stdClass The attempt row.
     */
    protected function attempt(\stdClass $quiz, \stdClass $user, array $responses, bool $finish = true): \stdClass {
        global $DB;
        $this->setUser($user);
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $attempt = $quizgenerator->create_attempt($quiz->id, $user->id);
        $quizgenerator->submit_responses($attempt->id, $responses, false, $finish);
        $this->setAdminUser();
        return $DB->get_record('quiz_attempts', ['id' => $attempt->id]);
    }

    /**
     * Answers for a frog quiz: the first $right questions "frog", the rest wrong.
     *
     * @param int $right Number of right answers.
     * @param int $count Number of questions.
     * @return array Responses.
     */
    protected function frogs(int $right, int $count): array {
        $responses = [];
        for ($slot = 1; $slot <= $count; $slot++) {
            $responses[$slot] = $slot <= $right ? 'frog' : 'cat';
        }
        return $responses;
    }

    /**
     * Run queued background tasks, keeping their log lines out of the test output.
     */
    protected function run_tasks(): void {
        ob_start();
        try {
            $this->runAdhocTasks();
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Our grade item for a quiz.
     *
     * @param \stdClass $quiz The quiz.
     * @return \grade_item|null The item.
     */
    protected function item(\stdClass $quiz): ?\grade_item {
        $config = quiz_config::get($quiz->id);
        return $config ? gradebook::get_item($config) : null;
    }

    /**
     * A student's score in our grade item.
     *
     * @param \stdClass $quiz The quiz.
     * @param int $userid The student.
     * @return float|null The score, or null if blank.
     */
    protected function score(\stdClass $quiz, int $userid): ?float {
        $grade = $this->item($quiz)->get_final($userid);
        return ($grade && $grade->finalgrade !== null) ? (float)$grade->finalgrade : null;
    }
}
