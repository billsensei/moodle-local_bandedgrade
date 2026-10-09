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
 * Counts the fully correct questions in quiz attempts.
 *
 * A question counts when its latest state is_correct() (right, or marked right by hand) and its mark in the
 * quiz is above 0. Description items and 0-mark questions are left out. See RESEARCH-bandedgrade.md §2.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class counter {
    /**
     * Count the fully correct questions in each usage.
     *
     * @param \qubaid_condition $qubaids The question usages (attempts) to look at.
     * @param int[] $requiredslots Slot numbers of the questions that must be correct (see required_slot_numbers()).
     * @return \stdClass[] Keyed by usage id; each has int correct, bool pending (a question waits for marking) and
     *         bool missed (a required question is not fully correct, or is not in the attempt).
     */
    public static function count_usages(\qubaid_condition $qubaids, array $requiredslots = []): array {
        global $CFG;
        require_once($CFG->dirroot . '/question/engine/lib.php');

        $mapper = new \question_engine_data_mapper();
        $steps = $mapper->load_questions_usages_latest_steps(
            $qubaids,
            null,
            'qas.id, qa.questionusageid, qa.slot, qa.maxmark, qas.state'
        );

        $result = [];
        $correctslots = [];
        foreach ($steps as $step) {
            $usageid = (int)$step->questionusageid;
            if (!isset($result[$usageid])) {
                $result[$usageid] = (object)['correct' => 0, 'pending' => false, 'missed' => false];
            }
            if ((float)$step->maxmark <= 0) {
                continue;
            }
            $state = \question_state::get($step->state);
            if ($state === \question_state::$needsgrading) {
                $result[$usageid]->pending = true;
            } else if ($state !== null && $state->is_correct()) {
                $result[$usageid]->correct++;
                $correctslots[$usageid][(int)$step->slot] = true;
            }
        }
        foreach ($result as $usageid => $count) {
            // A required question that is not fully correct, or that the attempt does not have, is missed.
            $count->missed = (bool)array_diff($requiredslots, array_keys($correctslots[$usageid] ?? []));
        }
        return $result;
    }

    /**
     * All finished, non-preview attempts of a quiz, for count_usages().
     *
     * Not \mod_quiz\question\qubaids_for_quiz: with "only finished" its WHERE uses unqualified preview and state
     * columns, which are ambiguous next to question_attempt_steps.state in load_questions_usages_latest_steps().
     *
     * @param int $quizid The quiz id.
     * @return \qubaid_condition The usages.
     */
    public static function finished_attempts_of_quiz(int $quizid): \qubaid_condition {
        global $CFG;
        require_once($CFG->dirroot . '/question/engine/lib.php');
        return new \qubaid_join(
            '{quiz_attempts} bgquiza',
            'bgquiza.uniqueid',
            'bgquiza.quiz = :bgquizid AND bgquiza.preview = 0 AND bgquiza.state = :bgstate',
            ['bgquizid' => $quizid, 'bgstate' => \mod_quiz\quiz_attempt::FINISHED]
        );
    }

    /**
     * Count the fully correct questions in a list of attempts.
     *
     * @param \stdClass[] $attempts Rows from quiz_attempts (need id and uniqueid).
     * @param int|null $quizid Set when $attempts are all finished attempts of this quiz: one join instead of a long list.
     * @param int[] $requiredslots Slot numbers of the questions that must be correct.
     * @return \stdClass[] Keyed by attempt id; each has int correct, bool pending and bool missed.
     */
    public static function count_attempts(array $attempts, ?int $quizid = null, array $requiredslots = []): array {
        global $CFG;
        require_once($CFG->dirroot . '/question/engine/lib.php');

        if (!$attempts) {
            return [];
        }
        if ($quizid !== null) {
            $byusage = self::count_usages(self::finished_attempts_of_quiz($quizid), $requiredslots);
        } else {
            $usageids = array_map(fn($attempt) => (int)$attempt->uniqueid, $attempts);
            $byusage = self::count_usages(new \qubaid_list(array_values($usageids)), $requiredslots);
        }

        $result = [];
        foreach ($attempts as $attempt) {
            $result[$attempt->id] = $byusage[(int)$attempt->uniqueid]
                ?? (object)['correct' => 0, 'pending' => false, 'missed' => (bool)$requiredslots];
        }
        return $result;
    }

    /**
     * The number of questions that can be counted: quiz slots with a mark above 0.
     *
     * @param int $quizid The quiz id.
     * @return int Number of questions.
     */
    public static function question_total(int $quizid): int {
        global $DB;
        return $DB->count_records_select('quiz_slots', 'quizid = :quizid AND maxmark > 0', ['quizid' => $quizid]);
    }

    /**
     * The slot numbers of the required questions that the quiz still has.
     *
     * Required questions are stored by slot id, which stays the same when questions are moved. A slot that was
     * deleted, or whose mark is now 0 (so it is not counted), is left out.
     *
     * @param int $quizid The quiz id.
     * @param int[] $slotids Ids from quiz_slots.
     * @return int[] Slot numbers, sorted.
     */
    public static function required_slot_numbers(int $quizid, array $slotids): array {
        global $DB;
        if (!$slotids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_map('intval', $slotids), SQL_PARAMS_NAMED);
        $numbers = $DB->get_fieldset_select(
            'quiz_slots',
            'slot',
            "quizid = :quizid AND maxmark > 0 AND id $insql",
            ['quizid' => $quizid] + $params
        );
        $numbers = array_map('intval', $numbers);
        sort($numbers);
        return $numbers;
    }
}
