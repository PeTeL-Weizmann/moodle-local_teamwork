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
 * @copyright  2018 Devlion <info@devlion.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_teamwork;

/**
 * @package    local_teamwork
 * @copyright  2018 Devlion <info@devlion.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class common {

    /**
     * function to get relevantt team by user
     *
     * @param int $instanceid instance id of assign
     * @param int $userid User id
     * @return string team name
     */
    public static function get_user_team($instanceid, $userid) {
        global $DB;

        $cm = $DB->get_record('course_modules', ['id' => $instanceid]);

        $sql = "
                SELECT tg.name
                FROM {local_teamwork_members} tm
                LEFT JOIN {local_teamwork_groups} tg ON (tg.id=tm.teamworkgroupid)
                INNER JOIN {local_teamwork} t ON (t.id=tg.teamworkid)                
                LEFT JOIN {user} u ON tm.userid = u.id 
                JOIN {user_enrolments} ue_f ON ue_f.userid = u.id 
                JOIN {enrol} ej4_e ON (ej4_e.id = ue_f.enrolid AND ej4_e.courseid = ?)               
                WHERE u.suspended = 0 AND ue_f.status = 0 AND t.moduleid = ? and tm.userid =? 
                AND ( 
                        (ue_f.timestart = '0' AND ue_f.timeend = '0') OR 
                        (ue_f.timestart = '0' AND ue_f.timeend > UNIX_TIMESTAMP()) OR 
                        (ue_f.timeend = '0' AND ue_f.timestart < UNIX_TIMESTAMP()) OR
                        (ue_f.timeend > UNIX_TIMESTAMP() AND ue_f.timestart < UNIX_TIMESTAMP())
                    )
                LIMIT 1
            ";
        $team = $DB->get_record_sql($sql, [$cm->course, $instanceid, $userid]);

        if (!empty($team->name)) {
            return $team->name;
        }

        return '';
    }

    /**
     * function to get relevantt team by user
     *
     * @param int $instanceid instance id of assign
     * @param int $userid User id
     * @return array team name
     */
    public static function get_all_users_in_cm($cmid) {
        global $DB;

        $cm = $DB->get_record('course_modules', array('id' => $cmid));

        $sql = "
                SELECT tm.userid as userid, tg.id as groupid
                FROM {local_teamwork_members} tm
                LEFT JOIN {local_teamwork_groups} tg ON (tg.id=tm.teamworkgroupid)
                INNER JOIN {local_teamwork} t ON (t.id=tg.teamworkid)
                LEFT JOIN {user} u ON tm.userid = u.id 
                JOIN {user_enrolments} ue_f ON ue_f.userid = u.id 
                JOIN {enrol} ej4_e ON (ej4_e.id = ue_f.enrolid AND ej4_e.courseid = ?)                                       
                WHERE u.suspended = 0 AND ue_f.status = 0 AND t.moduleid = ?
                AND ( 
                        (ue_f.timestart = '0' AND ue_f.timeend = '0') OR 
                        (ue_f.timestart = '0' AND ue_f.timeend > UNIX_TIMESTAMP()) OR 
                        (ue_f.timeend = '0' AND ue_f.timestart < UNIX_TIMESTAMP()) OR
                        (ue_f.timeend > UNIX_TIMESTAMP() AND ue_f.timestart < UNIX_TIMESTAMP())
                    )
            ";
        $result = $DB->get_records_sql($sql, [$cm->course, $cmid]);

        return $result;
    }

    /**
     * function that will check if plugin submition is enabled
     *
     * @param int $instance instance id of assign
     * @return boolean
     */
    public static function is_assign_submission_enable($instance) {
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

    public static function is_quiz_enable($cmid) {
        $lqsoptions = \local_quiz_summary_option\funcs::get_quiz_config($cmid);

        return ($lqsoptions->summary_teamwork == 1) ? false : true;
    }
}
