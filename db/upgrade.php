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
 * Local plugin "Team Work" - Upgrade plugin tasks
 *
 * @package     local_teamwork
 * @category    local
 * @copyright   2019 Devlion  <info@devlion.co
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion the version we are upgrading from
 * @return bool result
 */
function xmldb_local_teamwork_upgrade($oldversion) {

    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2019070815) {

        // Rename tables for petel.
        if ($dbman->table_exists('teamwork') && $dbman->table_exists('teamwork_groups') && $dbman->table_exists('teamwork_members')) {

            $table = new xmldb_table('teamwork');
            $dbman->rename_table($table, 'local_teamwork');

            $table = new xmldb_table('teamwork_groups');
            $dbman->rename_table($table, 'local_teamwork_groups');

            $table = new xmldb_table('teamwork_members');
            $dbman->rename_table($table, 'local_teamwork_members');

            return true;
        }

        // Teamwork savepoint reached.
        upgrade_plugin_savepoint(true, 2019070815, 'local', 'teamwork');
    }

    return true;
}
