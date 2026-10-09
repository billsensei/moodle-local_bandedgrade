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

use mod_quiz\quiz_attempt;

/**
 * The scoring rules: which attempts count, which band applies, and when a score may be written.
 *
 * This is the only place these rules live (PLAN-bandedgrade.md §4). The observer, the ad-hoc task and the
 * recalculate page all call rescore_user() or rescore_quiz().
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scorer {
    /** @var float Two scores closer than this are the same. */
    const EPSILON = 0.00001;

    /** @var string Result: the score was written to the gradebook. */
    const RESULT_WRITTEN = 'written';

    /** @var string Result: the gradebook already had this score. */
    const RESULT_SAME = 'same';

    /** @var string Result: a teacher changed the score by hand, so it was left alone. */
    const RESULT_KEPT = 'kept';

    /** @var string Result: the grade or grade item is locked, so it was left alone. */
    const RESULT_LOCKED = 'locked';

    /** @var string Result: another rescore of the quiz was running, so a background rescore was queued instead. */
    const RESULT_QUEUED = 'queued';

    /** @var int Seconds a student's rescore waits for another rescore of the same quiz before handing over. */
    const LOCK_WAIT_USER = 5;

    /** @var int Seconds a whole-quiz rescore waits for another rescore of the same quiz. */
    const LOCK_WAIT_QUIZ = 120;

    /**
     * The score for a value (number or percentage correct): the band with the highest lower bound not above it.
     *
     * A value between two bands (possible with the "average" grading method) gets the lower band.
     *
     * @param array $bands Bands as from bands::normalise().
     * @param float|null $count Number or percentage correct, or null if there is none yet.
     * @return float|null The score, or null (blank).
     */
    public static function score_for_count(array $bands, ?float $count): ?float {
        if ($count === null) {
            return null;
        }
        $score = null;
        foreach (bands::normalise($bands) as $band) {
            if ($band['from'] > $count + self::EPSILON) {
                break;
            }
            $score = $band['score'];
        }
        return $score;
    }

    /**
     * The value the bands are compared with: the number correct, or the percentage correct.
     *
     * The percentage is of the questions the quiz has now (like the quiz's own grade, which divides by the quiz's
     * current total) and is rounded to 2 decimals, so 2 of 3 correct is 66.67% and meets a band starting at 66.67.
     *
     * @param string $ruletype bands::TYPE_COUNT or bands::TYPE_PERCENT.
     * @param float|null $count Number correct that counts, or null if there is none yet.
     * @param int $total Number of questions that can be counted now.
     * @return float|null The value, or null (blank) if there is no count, or no question to take a percentage of.
     */
    public static function band_value(string $ruletype, ?float $count, int $total): ?float {
        if ($count === null || $ruletype !== bands::TYPE_PERCENT) {
            return $count;
        }
        return $total > 0 ? round($count / $total * 100, 2) : null;
    }

    /**
     * The score for a student's finished attempts: pick the count, turn it into the band value, find the band.
     *
     * @param \stdClass $config The quiz settings (ruletype, bands).
     * @param int $grademethod The quiz's grading method.
     * @param \stdClass[] $counts The student's finished attempts in order; each has correct and pending.
     * @param int $total Number of questions that can be counted now.
     * @return float|null The score, or null (blank).
     */
    private static function score_for_attempts(\stdClass $config, int $grademethod, array $counts, int $total): ?float {
        $count = self::choose_count($grademethod, $counts);
        return self::score_for_count($config->bands, self::band_value($config->ruletype, $count, $total));
    }

    /**
     * Pick the number correct that counts, following the quiz's grading method.
     *
     * This mirrors grade_calculator::compute_final_grade_from_attempts() (RESEARCH-bandedgrade.md §3), using
     * counts instead of marks. An attempt that still waits for marking has no count yet: first/last give null
     * if the chosen attempt waits; highest/average leave waiting attempts out.
     *
     * @param int $grademethod One of QUIZ_GRADEHIGHEST, QUIZ_GRADEAVERAGE, QUIZ_ATTEMPTFIRST, QUIZ_ATTEMPTLAST.
     * @param \stdClass[] $counts Finished attempts in attempt order; each has correct, pending and (optional) missed.
     *        An attempt that missed a required question counts as 0 correct, which gives the lowest band.
     * @return float|null The count, or null when there is none.
     */
    public static function choose_count(int $grademethod, array $counts): ?float {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/lib.php');

        $counts = array_map(fn($count) => empty($count->missed) ? $count
            : (object)['correct' => 0, 'pending' => $count->pending, 'missed' => true], array_values($counts));
        if (!$counts) {
            return null;
        }
        switch ($grademethod) {
            case QUIZ_ATTEMPTFIRST:
                $chosen = reset($counts);
                return $chosen->pending ? null : (float)$chosen->correct;

            case QUIZ_ATTEMPTLAST:
                $chosen = end($counts);
                return $chosen->pending ? null : (float)$chosen->correct;

            case QUIZ_GRADEAVERAGE:
                $marked = array_filter($counts, fn($count) => !$count->pending);
                if (!$marked) {
                    return null;
                }
                return array_sum(array_column($marked, 'correct')) / count($marked);

            case QUIZ_GRADEHIGHEST:
            default:
                $marked = array_filter($counts, fn($count) => !$count->pending);
                if (!$marked) {
                    return null;
                }
                return (float)max(array_column($marked, 'correct'));
        }
    }

    /**
     * Recount one student's attempts and update their score.
     *
     * @param int $quizid The quiz id.
     * @param int $userid The student.
     * @param bool $overwrite True to replace a score changed by hand (recalculate page only).
     * @return string|null One of the RESULT_ constants, or null if banded grading is off for this quiz.
     */
    public static function rescore_user(int $quizid, int $userid, bool $overwrite = false): ?string {
        if (!quiz_config::get_enabled($quizid)) {
            return null;
        }
        $lock = self::get_lock($quizid, self::LOCK_WAIT_USER);
        if (!$lock) {
            // Another rescore of this quiz is running: let the background task catch this student up.
            \local_bandedgrade\task\rescore_quiz::queue($quizid);
            return self::RESULT_QUEUED;
        }
        try {
            return self::rescore_user_locked($quizid, $userid, $overwrite);
        } finally {
            $lock->release();
        }
    }

    /**
     * rescore_user() once the quiz lock is held.
     *
     * @param int $quizid The quiz id.
     * @param int $userid The student.
     * @param bool $overwrite True to replace a score changed by hand.
     * @return string|null One of the RESULT_ constants, or null if banded grading is off.
     */
    private static function rescore_user_locked(int $quizid, int $userid, bool $overwrite): ?string {
        global $DB;
        [$config, $quiz] = self::load($quizid);
        if (!$config) {
            return null;
        }

        $attempts = $DB->get_records(
            'quiz_attempts',
            ['quiz' => $quizid, 'userid' => $userid, 'preview' => 0, 'state' => quiz_attempt::FINISHED],
            'attempt ASC',
            'id, uniqueid, attempt, userid'
        );
        $counts = counter::count_attempts($attempts, null, counter::required_slot_numbers($quizid, $config->requiredslots));
        self::store_counts($quizid, $attempts, $counts, $userid);

        $item = gradebook::ensure_item($config, $quiz);
        $score = self::score_for_attempts($config, (int)$quiz->grademethod, $counts, counter::question_total($quizid));
        $written = $DB->get_record('local_bandedgrade_written', ['quizid' => $quizid, 'userid' => $userid]);
        $grade = $DB->get_record('grade_grades', ['itemid' => $item->id, 'userid' => $userid], 'id, finalgrade');

        return self::write($item, $quizid, $userid, $score, $overwrite, $written ?: null, $grade ?: null);
    }

    /**
     * Recount every attempt in a quiz and update every student's score.
     *
     * @param int $quizid The quiz id.
     * @param int[] $overwriteuserids Students whose score changed by hand is replaced (recalculate page only).
     * @return array<string, int> Number of students per RESULT_ constant (empty if banded grading is off).
     * @throws \moodle_exception If another rescore of the quiz holds the lock too long (the task then retries).
     */
    public static function rescore_quiz(int $quizid, array $overwriteuserids = []): array {
        if (!quiz_config::get_enabled($quizid)) {
            return [];
        }
        $lock = self::get_lock($quizid, self::LOCK_WAIT_QUIZ);
        if (!$lock) {
            throw new \moodle_exception('locktimeout', 'local_bandedgrade');
        }
        try {
            return self::rescore_quiz_locked($quizid, array_map('intval', $overwriteuserids));
        } finally {
            $lock->release();
        }
    }

    /**
     * rescore_quiz() once the quiz lock is held.
     *
     * @param int $quizid The quiz id.
     * @param int[] $overwriteuserids Students whose score changed by hand is replaced.
     * @return array<string, int> Number of students per RESULT_ constant.
     */
    private static function rescore_quiz_locked(int $quizid, array $overwriteuserids): array {
        global $DB;
        [$config, $quiz] = self::load($quizid);
        if (!$config) {
            return [];
        }

        $attempts = $DB->get_records(
            'quiz_attempts',
            ['quiz' => $quizid, 'preview' => 0, 'state' => quiz_attempt::FINISHED],
            'userid ASC, attempt ASC',
            'id, uniqueid, attempt, userid'
        );
        $counts = counter::count_attempts($attempts, $quizid, counter::required_slot_numbers($quizid, $config->requiredslots));
        self::store_counts($quizid, $attempts, $counts);

        $byuser = [];
        foreach ($attempts as $attempt) {
            $byuser[$attempt->userid][] = $counts[$attempt->id];
        }

        $item = gradebook::ensure_item($config, $quiz);
        $writtens = $DB->get_records('local_bandedgrade_written', ['quizid' => $quizid], '', 'userid, id, score');
        $grades = $DB->get_records('grade_grades', ['itemid' => $item->id], '', 'userid, id, finalgrade');

        $results = [];
        $total = counter::question_total($quizid);
        $userids = array_unique(array_merge(array_keys($byuser), array_keys($writtens)));
        foreach ($userids as $userid) {
            $score = self::score_for_attempts($config, (int)$quiz->grademethod, $byuser[$userid] ?? [], $total);
            $result = self::write(
                $item,
                $quizid,
                (int)$userid,
                $score,
                in_array((int)$userid, $overwriteuserids, true),
                $writtens[$userid] ?? null,
                $grades[$userid] ?? null
            );
            $results[$result] = ($results[$result] ?? 0) + 1;
        }
        return $results;
    }

    /**
     * Students whose score was changed by hand (what "overwrite" would replace).
     *
     * @param int $quizid The quiz id.
     * @return int[] User ids.
     */
    public static function changed_by_hand(int $quizid): array {
        global $DB;
        $config = quiz_config::get_enabled($quizid);
        $item = $config ? gradebook::get_item($config) : null;
        if (!$item) {
            return [];
        }
        $writtens = $DB->get_records('local_bandedgrade_written', ['quizid' => $quizid], '', 'userid, id, score');
        $grades = $DB->get_records('grade_grades', ['itemid' => $item->id], '', 'userid, id, finalgrade');
        $changed = [];
        foreach (array_unique(array_merge(array_keys($grades), array_keys($writtens))) as $userid) {
            $current = isset($grades[$userid]->finalgrade) ? (float)$grades[$userid]->finalgrade : null;
            if (self::is_changed_by_hand($writtens[$userid] ?? null, $current)) {
                $changed[] = (int)$userid;
            }
        }
        sort($changed);
        return $changed;
    }

    /**
     * Load the settings and quiz row, if banded grading is on.
     *
     * @param int $quizid The quiz id.
     * @return array [settings or null, quiz row or null].
     */
    private static function load(int $quizid): array {
        global $DB;
        $config = quiz_config::get_enabled($quizid);
        $quiz = $config ? $DB->get_record('quiz', ['id' => $quizid], 'id, course, name, grademethod') : null;
        return $quiz ? [$config, $quiz] : [null, null];
    }

    /**
     * Save the counts, replacing the old ones for this quiz (or one student in it).
     *
     * @param int $quizid The quiz id.
     * @param \stdClass[] $attempts Finished attempts.
     * @param \stdClass[] $counts Counts keyed by attempt id.
     * @param int|null $userid Only this student, or null for everyone.
     */
    private static function store_counts(int $quizid, array $attempts, array $counts, ?int $userid = null): void {
        global $DB;
        $conditions = ['quizid' => $quizid];
        if ($userid !== null) {
            $conditions['userid'] = $userid;
        }
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('local_bandedgrade_attempt', $conditions);
        $now = time();
        $rows = [];
        foreach ($attempts as $attempt) {
            $rows[] = [
                'attemptid' => $attempt->id,
                'quizid' => $quizid,
                'userid' => $attempt->userid,
                'correctcount' => $counts[$attempt->id]->correct,
                'missedrequired' => (int)$counts[$attempt->id]->missed,
                'pending' => (int)$counts[$attempt->id]->pending,
                'timemodified' => $now,
            ];
        }
        $DB->insert_records('local_bandedgrade_attempt', $rows);
        $transaction->allow_commit();
    }

    /**
     * Take the lock that keeps two rescores of the same quiz from running at once.
     *
     * @param int $quizid The quiz id.
     * @param int $wait Seconds to wait for it.
     * @return \core\lock\lock|false The lock, or false if it was not free in time.
     */
    private static function get_lock(int $quizid, int $wait) {
        return \core\lock\lock_config::get_lock_factory('local_bandedgrade')->get_lock('quiz' . $quizid, $wait);
    }

    /**
     * Whether a score in the gradebook was changed by hand (it is not the score we last wrote).
     *
     * A blank counts as a change when we had written a score: the teacher cleared it.
     *
     * @param \stdClass|null $written Our last written score row.
     * @param float|null $current The score now in the gradebook, or null if blank.
     * @return bool True if a teacher changed it.
     */
    private static function is_changed_by_hand(?\stdClass $written, ?float $current): bool {
        if ($current === null) {
            return $written !== null && $written->score !== null;
        }
        return !$written || $written->score === null || self::differ((float)$written->score, $current);
    }

    /**
     * Write a score unless a teacher changed it by hand or it is locked (PLAN-bandedgrade.md §4.6).
     *
     * @param \grade_item $item Our grade item.
     * @param int $quizid The quiz id.
     * @param int $userid The student.
     * @param float|null $score The new score (null = blank).
     * @param bool $overwrite True to replace a score changed by hand.
     * @param \stdClass|null $written Our last written score row.
     * @param \stdClass|null $grade The student's grade_grades row (finalgrade).
     * @return string One of the RESULT_ constants.
     */
    private static function write(
        \grade_item $item,
        int $quizid,
        int $userid,
        ?float $score,
        bool $overwrite,
        ?\stdClass $written,
        ?\stdClass $grade
    ): string {
        $current = ($grade && $grade->finalgrade !== null) ? (float)$grade->finalgrade : null;

        if (!$overwrite && self::is_changed_by_hand($written, $current)) {
            return self::RESULT_KEPT;
        }
        if (!self::differ($current, $score)) {
            if ($written || $score !== null) {
                self::remember($quizid, $userid, $score, $written);
            }
            return self::RESULT_SAME;
        }
        if (!$item->update_final_grade($userid, $score, gradebook::SOURCE)) {
            return self::RESULT_LOCKED;
        }
        self::remember($quizid, $userid, $score, $written);
        return self::RESULT_WRITTEN;
    }

    /**
     * Store the score we wrote.
     *
     * @param int $quizid The quiz id.
     * @param int $userid The student.
     * @param float|null $score The score written.
     * @param \stdClass|null $written The existing row, if any.
     */
    private static function remember(int $quizid, int $userid, ?float $score, ?\stdClass $written): void {
        global $DB;
        if ($written) {
            if ($written->score === null ? $score === null : !self::differ((float)$written->score, $score)) {
                return;
            }
            $DB->update_record(
                'local_bandedgrade_written',
                (object)['id' => $written->id, 'score' => $score, 'timemodified' => time()]
            );
        } else {
            $DB->insert_record(
                'local_bandedgrade_written',
                (object)['quizid' => $quizid, 'userid' => $userid, 'score' => $score, 'timemodified' => time()]
            );
        }
    }

    /**
     * Whether two scores differ (blank and a number differ; two blanks do not).
     *
     * @param float|null $a First score.
     * @param float|null $b Second score.
     * @return bool True if they differ.
     */
    private static function differ(?float $a, ?float $b): bool {
        if ($a === null || $b === null) {
            return $a !== $b;
        }
        return abs($a - $b) > self::EPSILON;
    }
}
