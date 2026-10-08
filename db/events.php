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
 * Event observers for local_bandedgrade (see RESEARCH-bandedgrade.md §1).
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [];

// Attempt changes that can change a student's score. 'internal' => false runs them after the transaction commits.
foreach (
    ['attempt_graded', 'attempt_regraded', 'attempt_reopened', 'attempt_deleted',
        'attempt_manual_grading_completed'] as $name
) {
    $observers[] = [
        'eventname' => '\\mod_quiz\\event\\' . $name,
        'callback' => '\\local_bandedgrade\\observer::attempt_changed',
        'internal' => false,
    ];
}

$observers[] = [
    'eventname' => '\\mod_quiz\\event\\question_manually_graded',
    'callback' => '\\local_bandedgrade\\observer::question_manually_graded',
    'internal' => false,
];

foreach (['slot_created', 'slot_deleted', 'slot_mark_updated'] as $name) {
    $observers[] = [
        'eventname' => '\\mod_quiz\\event\\' . $name,
        'callback' => '\\local_bandedgrade\\observer::slots_changed',
        'internal' => false,
    ];
}

$observers[] = [
    'eventname' => '\\core\\event\\course_module_updated',
    'callback' => '\\local_bandedgrade\\observer::course_module_updated',
];
$observers[] = [
    'eventname' => '\\core\\event\\course_module_deleted',
    'callback' => '\\local_bandedgrade\\observer::course_module_deleted',
];
$observers[] = [
    'eventname' => '\\core\\event\\course_reset_ended',
    'callback' => '\\local_bandedgrade\\observer::course_reset_ended',
];
$observers[] = [
    'eventname' => '\\core\\event\\course_deleted',
    'callback' => '\\local_bandedgrade\\observer::course_deleted',
];
