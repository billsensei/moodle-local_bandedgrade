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
 * Upgrade steps for local_bandedgrade.
 *
 * @package    local_bandedgrade
 * @copyright  2026 Site administrators
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade local_bandedgrade.
 *
 * @param int $oldversion The version being upgraded from.
 * @return bool True.
 */
function xmldb_local_bandedgrade_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026100901) {
        // 0.5.0: the "scheme" of a quiz's rule (bands, or one pass mark). Existing quizzes keep their bands.
        $table = new xmldb_table('local_bandedgrade_quiz');
        $field = new xmldb_field('scheme', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'bands', 'ruletype');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026100901, 'local', 'bandedgrade');
    }

    return true;
}
