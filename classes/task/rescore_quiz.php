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

namespace local_bandedgrade\task;

use local_bandedgrade\local\scorer;

/**
 * Background task: recount and rescore every student in one quiz.
 *
 * Queued when the bands change, when questions are added or removed, and from the recalculate page.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rescore_quiz extends \core\task\adhoc_task {
    /**
     * Queue a rescore of a quiz (once: a matching task already in the queue is reused).
     *
     * @param int $quizid The quiz id.
     * @param bool $overwrite True to replace scores changed by hand.
     */
    public static function queue(int $quizid, bool $overwrite = false): void {
        $task = new self();
        $task->set_custom_data(['quizid' => $quizid, 'overwrite' => $overwrite]);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Name shown in the task logs.
     *
     * @return string Name.
     */
    public function get_name(): string {
        return get_string('taskrescore', 'local_bandedgrade');
    }

    /**
     * Run the rescore.
     */
    public function execute(): void {
        $data = $this->get_custom_data();
        $results = scorer::rescore_quiz((int)$data->quizid, !empty($data->overwrite));
        mtrace('local_bandedgrade: quiz ' . (int)$data->quizid . ' ' . json_encode($results));
    }
}
