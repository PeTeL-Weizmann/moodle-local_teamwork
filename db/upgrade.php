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

/**
 * @param int $oldversion the version we are upgrading from
 * @return bool result
 */
function xmldb_local_teamwork_upgrade($oldversion) {

    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2019070815) {

        // Rename tables for petel.
        if ($dbman->table_exists('teamwork') && $dbman->table_exists('teamwork_groups') &&
                $dbman->table_exists('teamwork_members')) {

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

    if ($oldversion < 2020060303) {

        $activityenabled = $DB->get_records_sql("
                  SELECT tw.*,cm.instance
                  FROM {local_teamwork} tw
                  INNER JOIN {course_modules} cm on (cm.id=tw.moduleid)
                  WHERE tw.active = 1");
        foreach ($activityenabled as $activity) {
            $data = ['assignment' => $activity->instance, 'plugin' => 'teamwork', 'subtype' => 'assignsubmission',
                    'name' => 'enabled'];
            $teamworksubmission = $DB->get_record('assign_plugin_config', $data);

            if (empty($teamworksubmission)) {
                $data['value'] = 1;
                $DB->insert_record('assign_plugin_config', $data);
            } else if ($teamworksubmission->value == 0) {
                $teamworksubmission->value = 1;
                $DB->update_record_raw('assign_plugin_config', $teamworksubmission);
            }
        }
        // Teamwork savepoint reached.
        upgrade_plugin_savepoint(true, 2020060303, 'local', 'teamwork');
    }

    if ($oldversion < 2022080808) {

        // Set defaults fo all langs.
        $langs = [
                'en',
                'he',
                'ru',
                'he_kids',
        ];
        foreach ($langs as $lang) {

            $manager = get_string_manager();

            $langprefix = '_' . $lang;
            $name = 'voice_ok_tokens' . $langprefix;
            $default = $manager->get_string('voice_ok_tokens_default', "local_teamwork", null, $lang);
            set_config($name, $default, 'local_teamwork');
        }

        // Schemes.
        // Set defaults fo all langs.
        $langs = [
                'en',
                'he',
                'he_kids',
                'ru',
        ];
        foreach ($langs as $lang) {
            $manager = get_string_manager();
            $langprefix = '_' . $lang;
            $schemes = [
                    'add_new_teamcard',
                    'add_new_named_teamcard',
                    'create_numbers_teamcard',
                    'drag_student_card',
                    'delete_teamcard',
                    'read_users',
                    'read_teams',
                    'sing_a_song',
            ];
            $schemeprefix = 'scheme_';
            foreach ($schemes as $key => $scheme) {
                $name = $schemeprefix . $scheme . $langprefix;
                $default = $manager->get_string($schemeprefix . $scheme . '_default', "local_teamwork", null, $lang);
                set_config($name, $default, 'local_teamwork');
            }
        }

        // Teamwork savepoint reached.
        upgrade_plugin_savepoint(true, 2022080808, 'local', 'teamwork');
    }

    if ($oldversion < 2022080811) {
        $table = new xmldb_table('local_teamwork');
        $field = new xmldb_field('counter', XMLDB_TYPE_INTEGER, '5', null, null,
                null, 0, 'teamuserenddate');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Teamwork savepoint reached.
        upgrade_plugin_savepoint(true, 2022080811, 'local', 'teamwork');
    }

    return true;
}
