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
 * Callbacks for local_bandedgrade (Moodle 5.0 has no hook replacement for these; RESEARCH-bandedgrade.md §7).
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add the "Grade by number correct" section to the quiz settings form.
 *
 * @param moodleform_mod $formwrapper The form.
 * @param MoodleQuickForm $mform The form elements.
 */
function local_bandedgrade_coursemodule_standard_elements($formwrapper, $mform) {
    \local_bandedgrade\local\form_section::add_elements($formwrapper, $mform);
}

/**
 * Check the "Grade by number correct" section.
 *
 * @param moodleform_mod $formwrapper The form.
 * @param array $data Submitted data.
 * @return array Errors keyed by element name.
 */
function local_bandedgrade_coursemodule_validation($formwrapper, $data) {
    return \local_bandedgrade\local\form_section::validate($formwrapper, (array)$data);
}

/**
 * Save the "Grade by number correct" section after the quiz is saved.
 *
 * @param stdClass $moduleinfo The saved module data.
 * @param stdClass $course The course.
 * @return stdClass The module data.
 */
function local_bandedgrade_coursemodule_edit_post_actions($moduleinfo, $course) {
    return \local_bandedgrade\local\form_section::save($moduleinfo, $course);
}

/**
 * Add "Recalculate scores" to the quiz's "More" menu when banded grading is on (RESEARCH-bandedgrade.md §6).
 *
 * @param settings_navigation $nav The settings navigation.
 * @param context $context The current context.
 */
function local_bandedgrade_extend_settings_navigation(settings_navigation $nav, context $context) {
    if ($context->contextlevel != CONTEXT_MODULE || !has_capability('local/bandedgrade:recalculate', $context)) {
        return;
    }
    $cm = get_coursemodule_from_id('quiz', $context->instanceid);
    if (!$cm || !\local_bandedgrade\local\quiz_config::get_enabled((int)$cm->instance)) {
        return;
    }
    $node = $nav->find('modulesettings', navigation_node::TYPE_SETTING);
    if ($node) {
        $node->add(
            get_string('recalculate', 'local_bandedgrade'),
            new moodle_url('/local/bandedgrade/recalculate.php', ['cmid' => $cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'local_bandedgrade_recalculate'
        );
    }
}
