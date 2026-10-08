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

use local_bandedgrade\local\bands;
use local_bandedgrade\local\gradebook;
use local_bandedgrade\local\quiz_config;
use local_bandedgrade\task\rescore_quiz;

/**
 * Restore of the "Grade by number correct" settings of a quiz.
 *
 * module.xml is read before the new quiz exists, so the data is only collected while processing and saved in
 * after_restore_module(). That runs after the whole restore, including the gradebook, so the restored score column
 * (full course restore) can be found through the 'grade_item' mapping. Without it (duplicate, import, activity
 * backup) a new column is created. Scores are then recounted by the background task.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_local_bandedgrade_plugin extends restore_local_plugin {
    /** @var stdClass|null The settings from the backup. */
    protected $config = null;

    /** @var stdClass[] The last written scores from the backup. */
    protected $writtens = [];

    /**
     * Paths to read in a quiz's module.xml.
     *
     * @return restore_path_element[] Paths.
     */
    protected function define_module_plugin_structure() {
        if ($this->task->get_modulename() !== 'quiz') {
            return [];
        }
        $paths = [new restore_path_element('bandedgrade_config', $this->get_pathfor('/bandedgrade'))];
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('bandedgrade_written', $this->get_pathfor('/bandedgrade/writtens/written'));
        }
        return $paths;
    }

    /**
     * Keep the settings until after_restore_module().
     *
     * @param array $data The settings.
     */
    public function process_bandedgrade_config($data) {
        $this->config = (object)$data;
    }

    /**
     * Keep a last written score until after_restore_module().
     *
     * @param array $data The row.
     */
    public function process_bandedgrade_written($data) {
        $this->writtens[] = (object)$data;
    }

    /**
     * Save the settings for the new quiz, link or create its score column, and queue a rescore.
     */
    public function after_restore_module() {
        global $DB;
        if (!$this->config) {
            return;
        }
        $quizid = (int)$this->task->get_activityid();
        $quiz = $DB->get_record('quiz', ['id' => $quizid], 'id, course, name, grademethod');
        if (!$quiz || $DB->record_exists(quiz_config::TABLE, ['quizid' => $quizid])) {
            return;
        }

        $config = quiz_config::save(
            $quizid,
            (int)$quiz->course,
            (bool)$this->config->enabled,
            bands::decode($this->config->bands),
            (bool)$this->config->zeroweight
        );

        // The score column comes back only in a full course restore. In a same-course restore (duplicate, import
        // into the same course) core maps the course's existing grade items to themselves, so a mapping to the old id
        // is the original quiz's column, which must not be shared.
        $olditemid = (int)$this->config->gradeitemid;
        $itemid = $olditemid ? (int)$this->get_mappingid('grade_item', $olditemid) : 0;
        if (
            $itemid && ($itemid === $olditemid || $DB->record_exists(quiz_config::TABLE, ['gradeitemid' => $itemid])
                || !$DB->record_exists('grade_items', ['id' => $itemid, 'courseid' => $quiz->course, 'itemtype' => 'manual']))
        ) {
            $itemid = 0;
        }
        if ($itemid) {
            quiz_config::set_grade_item($quizid, $itemid);
            $config->gradeitemid = $itemid;
            // Last written scores only make sense next to the restored grades.
            foreach ($this->writtens as $written) {
                $userid = $this->get_mappingid('user', $written->userid);
                if ($userid) {
                    $DB->insert_record('local_bandedgrade_written', (object)['quizid' => $quizid, 'userid' => $userid,
                        'score' => $written->score, 'timemodified' => $written->timemodified]);
                }
            }
        }

        if (!$config->enabled) {
            return;
        }
        gradebook::ensure_item($config, $quiz);
        if ($config->zeroweight) {
            gradebook::set_quiz_weight($quiz, true);
        }
        rescore_quiz::queue($quizid);
    }
}
