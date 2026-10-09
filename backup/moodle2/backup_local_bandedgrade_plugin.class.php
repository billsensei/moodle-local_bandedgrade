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
 * Backup of the "Grade by number correct" settings of a quiz.
 *
 * Stored in the quiz's module.xml. The score column itself is a manual grade item, so it travels in the course
 * gradebook (full course backups only). The per-attempt counts are not backed up: they are recounted after restore.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_local_bandedgrade_plugin extends backup_local_plugin {
    /**
     * Add the settings (and, with user data, the last written scores) to a quiz's module.xml.
     *
     * @return backup_plugin_element|null The plugin element, or null for other activities.
     */
    protected function define_module_plugin_structure() {
        if ($this->task->get_modulename() !== 'quiz') {
            return null;
        }

        $plugin = $this->get_plugin_element();
        $wrapper = new backup_nested_element($this->get_recommended_name());
        $config = new backup_nested_element(
            'bandedgrade',
            ['id'],
            ['enabled', 'ruletype', 'scheme', 'scaleid', 'bands', 'requiredslots', 'zeroweight', 'gradeitemid', 'timemodified']
        );
        $writtens = new backup_nested_element('writtens');
        $written = new backup_nested_element('written', ['id'], ['userid', 'score', 'timemodified']);

        $plugin->add_child($wrapper);
        $wrapper->add_child($config);
        $config->add_child($writtens);
        $writtens->add_child($written);

        $config->set_source_table('local_bandedgrade_quiz', ['quizid' => backup::VAR_ACTIVITYID]);
        if ($this->get_setting_value('userinfo')) {
            $written->set_source_table('local_bandedgrade_written', ['quizid' => backup::VAR_ACTIVITYID]);
            $written->annotate_ids('user', 'userid');
        }
        return $plugin;
    }
}
