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

namespace local_bandedgrade\admin;

use local_bandedgrade\local\bands;

/**
 * The site presets box: one preset per line, checked with the same rules as the quiz settings form.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_presets extends \admin_setting_configtextarea {
    /**
     * Make the box.
     *
     * @param string $name Setting name.
     * @param string $visiblename Label.
     * @param string $description Help text under the box.
     * @param string $defaultsetting Default text.
     */
    public function __construct($name, $visiblename, $description, $defaultsetting) {
        parent::__construct($name, $visiblename, $description, $defaultsetting, PARAM_RAW, 80, 8);
    }

    /**
     * Refuse the text if any line is wrong, saying which line and why.
     *
     * @param string $data The text.
     * @return true|string True if fine, otherwise the messages.
     */
    public function validate($data) {
        [, $errors] = bands::parse_site_presets((string)$data);
        return $errors ? implode(' ', $errors) : true;
    }
}
