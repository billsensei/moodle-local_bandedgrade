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
 * Site settings for local_bandedgrade: the presets teachers can pick.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_bandedgrade', get_string('pluginname', 'local_bandedgrade'));
    $ADMIN->add('localplugins', $settings);

    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_heading(
            'local_bandedgrade/presetsheading',
            get_string('presetsheading', 'local_bandedgrade'),
            get_string('presetsheading_desc', 'local_bandedgrade')
        ));
        $settings->add(new \local_bandedgrade\admin\setting_presets(
            'local_bandedgrade/sitepresets',
            get_string('sitepresets', 'local_bandedgrade'),
            get_string('sitepresets_desc', 'local_bandedgrade'),
            ''
        ));
        $settings->add(new admin_setting_configcheckbox(
            'local_bandedgrade/builtinpresets',
            get_string('builtinpresets', 'local_bandedgrade'),
            get_string('builtinpresets_desc', 'local_bandedgrade'),
            1
        ));
    }
}
