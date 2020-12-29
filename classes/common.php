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
 * Commom class
 *
 * @package    local_teamwork
 * @copyright  2016 onwards - Davidson institute (Weizmann institute)
 * @author     Devlion 2020 <info@devlion.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_teamwork;

/**
 * @copyright  2016 onwards - Davidson institute (Weizmann institute)
 * @author     Devlion 2020 <info@devlion.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class common {

    /**
     * function to get relevantt team by user
     *
     * @param int $instanceid instance id of assign
     * @param int $userid User id
     * @return user team name
     */
    public static function get_user_team($instanceid, $userid) {
        global $DB;
        $sql = "
                SELECT tg.name
                FROM {local_teamwork_members} as tm
                LEFT JOIN {local_teamwork_groups} as tg ON (tg.id=tm.teamworkgroupid)
                INNER JOIN {local_teamwork} as t ON (t.id=tg.teamworkid)
                WHERE t.moduleid = ? and tm.userid =? 
            ";
        $team = $DB->get_record_sql($sql, array($instanceid, $userid));
        if (!empty($team->name)) {
            return $team->name;
        }
        return '';
    }

    /**
     * function that will check if plugin submition is enabled
     *
     * @param int $instance instance id of assign
     * @return boolean
     */
    public static function is_submission_enable($instance) {
        global $DB;
        $sql = "
                SELECT id
                FROM {assign_plugin_config}
                WHERE assignment = ? and plugin = 'teamwork' and subtype = 'assignsubmission' and value = ?
            ";
        $teamworksubmission = $DB->get_record_sql($sql, array($instance, 1));
        if (!empty($teamworksubmission)) {
            return true;
        }
        return false;
    }
}
