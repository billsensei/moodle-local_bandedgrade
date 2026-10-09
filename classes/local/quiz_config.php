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
 * Reads and saves the banded grading settings of a quiz.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_config {
    /** @var string The settings table. */
    const TABLE = 'local_bandedgrade_quiz';

    /**
     * Get the settings of a quiz, with the bands decoded.
     *
     * @param int $quizid The quiz id.
     * @return \stdClass|null The settings row, with bands as an array of ['from' => int, 'score' => float], or null.
     */
    public static function get(int $quizid): ?\stdClass {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['quizid' => $quizid]);
        if (!$record) {
            return null;
        }
        $record->bands = bands::decode($record->bands);
        $record->requiredslots = self::decode_slots($record->requiredslots);
        return $record;
    }

    /**
     * Get the settings of a quiz only if banded grading is turned on and has bands.
     *
     * @param int $quizid The quiz id.
     * @return \stdClass|null The settings, or null when it is off.
     */
    public static function get_enabled(int $quizid): ?\stdClass {
        $config = self::get($quizid);
        if (!$config || !$config->enabled || !$config->bands) {
            return null;
        }
        return $config;
    }

    /**
     * Save the settings of a quiz.
     *
     * @param int $quizid The quiz id.
     * @param int $courseid The course id.
     * @param bool $enabled Whether banded grading is on.
     * @param array $bands Bands as from bands::normalise().
     * @param bool $zeroweight Whether the quiz's own grade should have weight 0.
     * @param string $ruletype bands::TYPE_COUNT or bands::TYPE_PERCENT: what the bands' lower bounds measure.
     * @param string $scheme bands::SCHEME_BANDS or bands::SCHEME_PASSFAIL: how the teacher set the bands up.
     * @param int[] $requiredslots Ids (quiz_slots) of the questions that must be correct.
     * @return \stdClass The saved settings (bands decoded).
     */
    public static function save(
        int $quizid,
        int $courseid,
        bool $enabled,
        array $bands,
        bool $zeroweight,
        string $ruletype = bands::TYPE_COUNT,
        string $scheme = bands::SCHEME_BANDS,
        array $requiredslots = []
    ): \stdClass {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['quizid' => $quizid]);
        $data = [
            'quizid' => $quizid,
            'courseid' => $courseid,
            'enabled' => (int)$enabled,
            'ruletype' => bands::is_ruletype($ruletype) ? $ruletype : bands::TYPE_COUNT,
            'scheme' => bands::is_scheme($scheme) ? $scheme : bands::SCHEME_BANDS,
            'bands' => bands::encode($bands),
            'requiredslots' => $requiredslots ? self::encode_slots($requiredslots) : null,
            'zeroweight' => (int)$zeroweight,
            'timemodified' => time(),
        ];
        if ($record) {
            $data['id'] = $record->id;
            $DB->update_record(self::TABLE, (object)$data);
        } else {
            $DB->insert_record(self::TABLE, (object)$data);
        }
        return self::get($quizid);
    }

    /**
     * Encode a list of slot ids for storage.
     *
     * @param int[] $slotids Slot ids.
     * @return string JSON, sorted and without duplicates.
     */
    public static function encode_slots(array $slotids): string {
        $slotids = array_values(array_unique(array_map('intval', $slotids)));
        sort($slotids);
        return json_encode($slotids);
    }

    /**
     * Decode stored slot ids.
     *
     * @param string|null $json JSON from the database.
     * @return int[] Slot ids (empty if none or broken).
     */
    public static function decode_slots(?string $json): array {
        $slotids = $json ? json_decode($json, true) : null;
        if (!is_array($slotids)) {
            return [];
        }
        $slotids = array_values(array_unique(array_filter(array_map('intval', $slotids), fn($id) => $id > 0)));
        sort($slotids);
        return $slotids;
    }

    /**
     * Remember which grade item holds the scores.
     *
     * @param int $quizid The quiz id.
     * @param int|null $gradeitemid The grade item id, or null.
     */
    public static function set_grade_item(int $quizid, ?int $gradeitemid): void {
        global $DB;
        $DB->set_field(self::TABLE, 'gradeitemid', $gradeitemid, ['quizid' => $quizid]);
    }

    /**
     * Delete everything the plugin stores for a quiz (settings, counts, last written scores).
     *
     * @param int $quizid The quiz id.
     */
    public static function delete_quiz_data(int $quizid): void {
        global $DB;
        $DB->delete_records('local_bandedgrade_attempt', ['quizid' => $quizid]);
        $DB->delete_records('local_bandedgrade_written', ['quizid' => $quizid]);
        $DB->delete_records(self::TABLE, ['quizid' => $quizid]);
    }
}
