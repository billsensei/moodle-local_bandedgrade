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

namespace local_bandedgrade\event;

/**
 * A teacher asked for the "number correct" scores of a quiz to be recalculated.
 *
 * other['overwrite']: 1 if scores changed by hand are replaced, 0 if they are kept.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scores_recalculated extends \core\event\base {
    /**
     * Set the basic event data.
     */
    protected function init() {
        $this->data['objecttable'] = 'quiz';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Event name.
     *
     * @return string Name.
     */
    public static function get_name() {
        return get_string('eventscoresrecalculated', 'local_bandedgrade');
    }

    /**
     * Event description for the logs.
     *
     * @return string Description.
     */
    public function get_description() {
        $a = (object)['userid' => $this->userid, 'quizid' => $this->objectid,
            'cmid' => $this->contextinstanceid];
        return get_string(
            $this->other['overwrite'] ? 'eventscoresrecalculated_overwrite' : 'eventscoresrecalculated_keep',
            'local_bandedgrade',
            $a
        );
    }

    /**
     * Link to the quiz.
     *
     * @return \moodle_url URL.
     */
    public function get_url() {
        return new \moodle_url('/mod/quiz/view.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * Check the event data.
     *
     * @throws \coding_exception If something is missing.
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['overwrite'])) {
            throw new \coding_exception('The \'overwrite\' value must be set in other.');
        }
        if ($this->contextlevel != CONTEXT_MODULE) {
            throw new \coding_exception('Context level must be CONTEXT_MODULE.');
        }
    }

    /**
     * Map the object id when restoring logs.
     *
     * @return array Mapping.
     */
    public static function get_objectid_mapping() {
        return ['db' => 'quiz', 'restore' => 'quiz'];
    }

    /**
     * Nothing in other needs mapping.
     *
     * @return bool False.
     */
    public static function get_other_mapping() {
        return false;
    }
}
