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
use local_bandedgrade\local\counter;
use local_bandedgrade\local\quiz_config;

/**
 * Hook callbacks for local_bandedgrade.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /** @var string[] Quiz pages that show the warning: the quiz page and the "Questions" page. */
    const WARNING_PAGES = ['mod-quiz-view', 'mod-quiz-edit'];

    /**
     * Warn the people who can edit a quiz when its questions no longer reach every band.
     *
     * This happens when questions are removed after the bands were set. The page header is printed after this
     * hook, so the notification appears on the same page.
     *
     * @param \core\hook\output\before_http_headers $hook The hook.
     */
    public static function before_http_headers(\core\hook\output\before_http_headers $hook): void {
        global $PAGE;
        if (during_initial_install() || !in_array($PAGE->pagetype, self::WARNING_PAGES, true) || !$PAGE->cm) {
            return;
        }
        $cm = $PAGE->cm;
        if ($cm->modname !== 'quiz' || !has_capability('moodle/course:manageactivities', $cm->context)) {
            return;
        }
        $config = quiz_config::get_enabled((int)$cm->instance);
        if (!$config) {
            return;
        }
        $total = counter::question_total((int)$cm->instance);
        $from = bands::first_unreachable($config->bands, $total);
        if ($from === null) {
            return;
        }
        $a = (object)[
            'total' => $total,
            'from' => $from,
            'url' => (new \moodle_url('/course/modedit.php', ['update' => $cm->id]))->out(),
        ];
        \core\notification::warning(get_string('unreachablewarning', 'local_bandedgrade', $a));
    }
}
