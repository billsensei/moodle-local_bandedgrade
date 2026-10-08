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

/**
 * Uninstall clean-up for local_bandedgrade.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Give every quiz its own grade weight back and delete the "number correct" score columns.
 *
 * Without this, course totals would keep ignoring the quiz grades and the columns would stay behind with nothing
 * keeping them up to date.
 *
 * @return bool Always true.
 */
function xmldb_local_bandedgrade_uninstall() {
    global $DB;
    foreach ($DB->get_records('local_bandedgrade_quiz') as $record) {
        $config = \local_bandedgrade\local\quiz_config::get((int)$record->quizid);
        $quiz = $DB->get_record('quiz', ['id' => $record->quizid], 'id, course');
        if ($quiz && $config->enabled && $config->zeroweight) {
            \local_bandedgrade\local\gradebook::set_quiz_weight($quiz, false);
        }
        \local_bandedgrade\local\gradebook::delete_item($config);
    }
    return true;
}
