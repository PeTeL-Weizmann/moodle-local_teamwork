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
 * Glossary local post install hook
 *
 * @package     local_teamwork
 * @category    local
 * @copyright   2019 Devlion  <info@devlion.co
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_teamwork_install() {
    global $DB;
    $dbman = $DB->get_manager();

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

    if (!$dbman->table_exists('local_teamwork')) {
        $table = new xmldb_table('local_teamwork');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('creatorid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('moduleid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('type', XMLDB_TYPE_CHAR, '20', null, null, null, null);
        $table->add_field('studentediting', XMLDB_TYPE_INTEGER, '2', null, null, null, null);
        $table->add_field('teamnumbers', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('teamusernumbers', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('teamuserallowenddate', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('teamuserenddate', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('active', XMLDB_TYPE_INTEGER, '2', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '20', null, null, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '20', null, null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        $dbman->create_table($table);

        // Set indexes.
        $indexcreatorid = new xmldb_index('creatorid', XMLDB_INDEX_NOTUNIQUE, array('creatorid'));
        $dbman->add_index($table, $indexcreatorid);

        $indexmoduleid = new xmldb_index('moduleid', XMLDB_INDEX_NOTUNIQUE, array('moduleid'));
        $dbman->add_index($table, $indexmoduleid);
    }

    if (!$dbman->table_exists('local_teamwork_groups')) {
        $table = new xmldb_table('local_teamwork_groups');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('teamworkid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '20', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '20', null, null, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '20', null, null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        $dbman->create_table($table);

        // Set indexes.
        $indexteamworkid = new xmldb_index('teamworkid', XMLDB_INDEX_NOTUNIQUE, array('teamworkid'));
        $dbman->add_index($table, $indexteamworkid);
    }

    if (!$dbman->table_exists('local_teamwork_members')) {
        $table = new xmldb_table('local_teamwork_members');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('teamworkgroupid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '20', null, null, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '20', null, null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        $dbman->create_table($table);

        // Set indexes.
        $indexteamworkgroupid = new xmldb_index('teamworkgroupid', XMLDB_INDEX_NOTUNIQUE, array('teamworkgroupid'));
        $dbman->add_index($table, $indexteamworkgroupid);

        $indexuserid = new xmldb_index('userid', XMLDB_INDEX_NOTUNIQUE, array('userid'));
        $dbman->add_index($table, $indexuserid);
    }

}
