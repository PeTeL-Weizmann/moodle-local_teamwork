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
 * External interface library for customfields component
 *
 * @package   local_teamwork
 * @copyright 2018 Devlion <info@devlion.co>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . "/externallib.php");
require_once($CFG->dirroot . '/local/teamwork/locallib.php');

/**
 * Class local_teamwork_external
 *
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_teamwork_external extends external_api {

    // Render_block_html_page.

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function render_block_html_page_parameters() {
        return new external_function_parameters(
                array(
                        'courseid' => new external_value(PARAM_INT, 'course id int', VALUE_DEFAULT, null),
                        'activityid' => new external_value(PARAM_INT, 'activity id int', VALUE_DEFAULT, null),
                        'moduletype' => new external_value(PARAM_RAW, 'moduletype text', VALUE_DEFAULT, null),
                        'selectgroupid' => new external_value(PARAM_RAW, 'selectgroupid text', VALUE_DEFAULT, null),
                )
        );
    }

    /**
     * Returns result
     *
     * @return result
     */
    public static function render_block_html_page_returns() {
        return new external_single_structure(
                array(
                        'result' => new external_value(PARAM_RAW, 'result bool'),
                )
        );
    }

    /**
     * Add share task
     *
     * @param int $courseid
     * @param int $activityid
     * @param text $moduletype
     * @param text $selectgroupid
     * @return array
     */
    public static function render_block_html_page($courseid, $activityid, $moduletype, $selectgroupid) {
        global $OUTPUT, $DB, $USER;

        $context = context_user::instance($USER->id);
        self::validate_context($context);
        $block = '';

        $isateacher = if_user_teacher_on_course($courseid);
        $teamwork = $DB->get_record('local_teamwork', array('moduleid' => $activityid, 'type' => get_module_name($activityid)));

        // Default value of select.
        $groups = view_groups_select($courseid);

        // PTL 3921
        //if(!empty($groups) && isset($groups[0])){
        //    $selectgroupid = array($groups[0]->id);
        //}else{
        //    $selectgroupid = array(0);
        //}

        $selectgroupid = json_decode($selectgroupid);

        if ($isateacher || (if_user_student_on_course($courseid) && if_access_to_student($activityid) && if_teamwork_enable($activityid))) {
            $block .= html_writer::tag('button', get_string('open_local', 'local_teamwork'),
                    array('id' => 'open_local', 'class' => 'btn btn-primary m-4'));
            if (!$isateacher && $teamwork->teamuserallowenddate) {
                $block .= '<style>.singlebutton{display:none;}</style>';
            }

            if (isset($teamwork->teamuserallowenddate) && $teamwork->teamuserallowenddate) {
                $block .= html_writer::tag('div', get_string('letsubmitafterteamworkenddate', 'local_teamwork',
                        userdate($teamwork->teamuserenddate)),
                        ['class' => 'teawmworkenddatemessage']);
            }

            if (!empty($groups) && if_teamwork_enable($activityid) && $isateacher) {
                $data = array();
                foreach ($groups as $group) {
                    $tmp['teacherinfo_title'] = get_string('forgroup', 'local_teamwork') . $group->name;
                    $tmp['teamsharedusers'] = get_cards($activityid, $moduletype, $courseid, $group->id);
                    $data[] = $tmp;
                }
                $block .= $OUTPUT->render_from_template('local_teamwork/teamwork-info', array('data' => $data));
            }
        }
        // Get information for student.
        if (if_user_student_on_course($courseid)) {
            $datastudent = return_data_for_student_tohtml($activityid, $moduletype, $courseid, $selectgroupid);
            $block .= $OUTPUT->render_from_template('local_teamwork/student-info', array('studentCard' => $datastudent));
        }

        return array('result' => $block);
    }


    // Render_teamwork_html.

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function render_teamwork_html_parameters() {
        return new external_function_parameters(
                array(
                        'courseid' => new external_value(PARAM_INT, 'course id int', VALUE_DEFAULT, null),
                        'activityid' => new external_value(PARAM_INT, 'activity id int', VALUE_DEFAULT, null),
                        'moduletype' => new external_value(PARAM_RAW, 'moduletype text', VALUE_DEFAULT, null),
                        'selectgroupid' => new external_value(PARAM_RAW, 'selectgroupid text', VALUE_DEFAULT, null),
                )
        );
    }

    /**
     * Returns result
     *
     * @return result
     */
    public static function render_teamwork_html_returns() {
        return new external_single_structure(
                array(
                        'result' => new external_value(PARAM_RAW, 'result bool'),
                )
        );
    }

    /**
     * Add share task
     *
     * @param int $courseid
     * @param int $activityid
     * @param text $moduletype
     * @param text $selectgroupid
     * @return array
     */
    public static function render_teamwork_html($courseid, $activityid, $moduletype, $selectgroupid) {
        global $OUTPUT, $USER;

        $context = context_user::instance($USER->id);
        self::validate_context($context);

        $arrgroupid = json_decode($selectgroupid);

        $data = array();
        $data['courseid'] = $courseid;
        $data['activityid'] = $activityid;
        $data['moduletype'] = $moduletype;

        $data['teamwork_enable'] = if_teamwork_enable($activityid);
        $data['students_button_status'] = students_button_status($activityid);
        $data['allow_add_teams'] = allow_add_teams($courseid, $activityid, $arrgroupid[0]);
        $data['groups'] = view_groups_select($courseid);

        // Set default groups.
        foreach ($data['groups'] as $group) {
            if ($group->id == $arrgroupid[0]) {
                $group->firstelement = true;
                $data['group_name_select'] = $group->name;
            }
        }

        $data['list_students'] = get_students_by_select($selectgroupid, $courseid, $activityid, $moduletype);
        $data['count_all_students'] = count(get_students_course($courseid));

        $data['cards'] = get_cards($activityid, $moduletype, $courseid, $arrgroupid[0]);
        $data['if_user_teacher'] = if_user_teacher_on_course($courseid);
        $data['if_user_student'] = if_user_student_on_course($courseid);

        $html = $OUTPUT->render_from_template('local_teamwork/main', $data);

        $arrcontent = array(
                'shadow' => (if_teamwork_enable($activityid)) ? 'skin_hide' : 'skin_show',
                'content' => $html,
        );

        return array('result' => json_encode($arrcontent));
    }

    // Set_teamwork_enable.

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function set_teamwork_enable_parameters() {
        return new external_function_parameters(
                array(
                        'activityid' => new external_value(PARAM_INT, 'activity id int', VALUE_DEFAULT, null),
                        'moduletype' => new external_value(PARAM_RAW, 'moduletype text', VALUE_DEFAULT, null),
                )
        );
    }

    /**
     * Returns result
     *
     * @return result
     */
    public static function set_teamwork_enable_returns() {
        return new external_single_structure(
                array(
                        'result' => new external_value(PARAM_RAW, 'result bool'),
                )
        );
    }

    /**
     * Add share task
     *
     * @param int $activityid
     * @param text $moduletype
     * @return array
     */
    public static function set_teamwork_enable($activityid, $moduletype) {
        global $USER, $DB, $USER;

        $context = context_user::instance($USER->id);
        self::validate_context($context);

        $teamwork = $DB->get_record('local_teamwork', array('moduleid' => $activityid, 'type' => $moduletype));
        if (!empty($teamwork)) {
            switch ($teamwork->active) {
                case 0:
                    $teamwork->active = 1;
                    break;
                case 1:
                    $teamwork->active = 0;
                    break;
                default:
                    $teamwork->active = 1;
            }
            $DB->update_record('local_teamwork', $teamwork, $bulk = false);
        } else {
            $dataobject = new stdClass();
            $dataobject->creatorid = $USER->id;
            $dataobject->moduleid = $activityid;
            $dataobject->type = $moduletype;
            $dataobject->studentediting = 1;
            $dataobject->active = 1;
            $dataobject->timecreated = time();
            $dataobject->timemodified = time();
            $DB->insert_record('local_teamwork', $dataobject);
        }

        return array('result' => json_encode(array()));
    }

    // Set access to student.

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function set_access_to_student_parameters() {
        return new external_function_parameters(
                array(
                        'activityid' => new external_value(PARAM_INT, 'activity id int', VALUE_DEFAULT, null),
                        'moduletype' => new external_value(PARAM_RAW, 'moduletype text', VALUE_DEFAULT, null),
                )
        );
    }

    /**
     * Returns result
     *
     * @return result
     */
    public static function set_access_to_student_returns() {
        return new external_single_structure(
                array(
                        'result' => new external_value(PARAM_RAW, 'result bool'),
                )
        );
    }

    /**
     * Add share task
     *
     * @param int $activityid
     * @param text $moduletype
     * @return array
     */
    public static function set_access_to_student($activityid, $moduletype) {
        global $DB, $USER;

        $context = context_user::instance($USER->id);
        self::validate_context($context);

        $teamwork = $DB->get_record('local_teamwork', array('moduleid' => $activityid, 'type' => $moduletype));
        if (!empty($teamwork)) {
            switch ($teamwork->studentediting) {
                case 0:
                    $teamwork->studentediting = 1;
                    break;
                case 1:
                    $teamwork->studentediting = 0;
                    break;
                default:
                    $teamwork->studentediting = 0;
            }
            $DB->update_record('local_teamwork', $teamwork, $bulk = false);
        }

        return array('result' => json_encode(array()));
    }

    // Add new card.

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function add_new_card_parameters() {
        return new external_function_parameters(
                array(
                        'courseid' => new external_value(PARAM_INT, 'course id int', VALUE_DEFAULT, null),
                        'activityid' => new external_value(PARAM_INT, 'activity id int', VALUE_DEFAULT, null),
                        'moduletype' => new external_value(PARAM_RAW, 'moduletype text', VALUE_DEFAULT, null),
                        'selectgroupid' => new external_value(PARAM_RAW, 'selectgroupid text', VALUE_DEFAULT, null),
                )
        );
    }

    /**
     * Returns result
     *
     * @return result
     */
    public static function add_new_card_returns() {
        return new external_single_structure(
                array(
                        'result' => new external_value(PARAM_RAW, 'result bool'),
                )
        );
    }

    /**
     * Add share task
     *
     * @param int $courseid
     * @param int $activityid
     * @param text $moduletype
     * @param text $selectgroupid
     * @return array
     */
    public static function add_new_card($courseid, $activityid, $moduletype, $selectgroupid) {

        $arrgroupid = json_decode($selectgroupid);
        foreach ($arrgroupid as $id) {
            $result = add_new_card($activityid, $moduletype, $id, array(), $courseid);
        }

        return array('result' => json_encode($result));
    }

    // Delete card.

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function delete_card_parameters() {
        return new external_function_parameters(
                array(
                        'teamid' => new external_value(PARAM_INT, 'teamid int', VALUE_DEFAULT, null),
                )
        );
    }

    /**
     * Returns result
     *
     * @return result
     */
    public static function delete_card_returns() {
        return new external_single_structure(
                array(
                        'result' => new external_value(PARAM_RAW, 'result bool'),
                )
        );
    }

    /**
     * Add share task
     *
     * @param int $teamid
     * @return array
     */
    public static function delete_card($teamid) {
        global $DB;

        $DB->delete_records('local_teamwork_groups', array('id' => $teamid));
        $DB->delete_records('local_teamwork_members', array('teamworkgroupid' => $teamid));

        return array('result' => json_encode(array()));
    }

    // Show random popup.

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function show_random_popup_parameters() {
        return new external_function_parameters(
                array()
        );
    }

    /**
     * Returns result
     *
     * @return result
     */
    public static function show_random_popup_returns() {
        return new external_single_structure(
                array(
                        'result' => new external_value(PARAM_RAW, 'result bool'),
                )
        );
    }

    /**
     * Add share task
     *
     * @return array
     */
    public static function show_random_popup() {
        global $OUTPUT, $USER;

        $context = context_user::instance($USER->id);
        self::validate_context($context);
        $data = array(
                'num_students' => 10,
        );

        $html = $OUTPUT->render_from_template('local_teamwork/popup-team-selection', $data);

        $arrcontent = array(
                'content' => $html,
                'header' => get_string('randomgroups', 'local_teamwork'),
        );

        return array('result' => json_encode($arrcontent));
    }

    // Set random teams.

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function set_random_teams_parameters() {
        return new external_function_parameters(
                array(
                        'courseid' => new external_value(PARAM_INT, 'course id int', VALUE_DEFAULT, null),
                        'activityid' => new external_value(PARAM_INT, 'activity id int', VALUE_DEFAULT, null),
                        'moduletype' => new external_value(PARAM_RAW, 'moduletype text', VALUE_DEFAULT, null),
                        'selectgroupid' => new external_value(PARAM_RAW, 'selectgroupid text', VALUE_DEFAULT, null),
                        'numberofstudent' => new external_value(PARAM_INT, 'numberofstudent int', VALUE_DEFAULT, null),
                )
        );
    }

    /**
     * Returns result
     *
     * @return result
     */
    public static function set_random_teams_returns() {
        return new external_single_structure(
                array(
                        'result' => new external_value(PARAM_RAW, 'result bool'),
                )
        );
    }

    /**
     * Add share task
     *
     * @param int $courseid
     * @param int $activityid
     * @param text $moduletype
     * @param text $selectgroupid
     * @return array
     */
    public static function set_random_teams($courseid, $activityid, $moduletype, $selectgroupid, $numberofstudent) {
        global $DB, $USER;

        $context = context_user::instance($USER->id);
        self::validate_context($context);

        $arrselectid = json_decode($selectgroupid);

        $teamwork = $DB->get_record('local_teamwork', array('moduleid' => $activityid, 'type' => $moduletype));
        if (!empty($teamwork)) {

            // Delete all cards from tables.
            $teams = $DB->get_records('local_teamwork_groups', array('teamworkid' => $teamwork->id, 'groupid' => $arrselectid[0]));
            foreach ($teams as $team) {
                $DB->delete_records('local_teamwork_members', array('teamworkgroupid' => $team->id));
                $DB->delete_records('local_teamwork_groups', array('id' => $team->id));
            }

            // Insert new teams.
            $students = get_students_by_select($selectgroupid, $courseid, $activityid, $moduletype);
            shuffle($students);
            $chunk = array_chunk($students, $numberofstudent);

            foreach ($chunk as $item) {
                add_new_card($activityid, $moduletype, $arrselectid[0], $item, $courseid);
            }
        }

        return array('result' => json_encode(array()));
    }

    // Set name card.

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function set_new_team_name_parameters() {
        return new external_function_parameters(
                array(
                        'cardid' => new external_value(PARAM_INT, 'cardid int', VALUE_DEFAULT, null),
                        'cardname' => new external_value(PARAM_RAW, 'cardname text', VALUE_DEFAULT, null),
                )
        );
    }

    /**
     * Returns result
     *
     * @return result
     */
    public static function set_new_team_name_returns() {
        return new external_single_structure(
                array(
                        'result' => new external_value(PARAM_RAW, 'result bool'),
                )
        );
    }

    /**
     * Add share task
     *
     * @param int $teamid
     * @return array
     */
    public static function set_new_team_name($cardid, $cardname) {
        global $DB, $USER;

        $context = context_user::instance($USER->id);
        self::validate_context($context);

        $team = $DB->get_record('local_teamwork_groups', array('id' => $cardid));
        if (!empty($team)) {
            $team->name = $cardname;
            $DB->update_record('local_teamwork_groups', $team, $bulk = false);
        }

        return array('result' => json_encode(array()));
    }

    // Render student settings popup.

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function render_student_settings_popup_parameters() {
        return new external_function_parameters(
                array(
                        'activityid' => new external_value(PARAM_INT, 'activity id int', VALUE_DEFAULT, null),
                        'moduletype' => new external_value(PARAM_RAW, 'moduletype text', VALUE_DEFAULT, null),
                )
        );
    }

    /**
     * Returns result
     *
     * @return result
     */
    public static function render_student_settings_popup_returns() {
        return new external_single_structure(
                array(
                        'result' => new external_value(PARAM_RAW, 'result bool'),
                )
        );
    }

    /**
     * Add share task
     *
     * @param int $activityid
     * @param text $moduletype
     * @return array
     */
    public static function render_student_settings_popup($activityid, $moduletype) {
        global $DB, $OUTPUT, $USER;

        $context = context_user::instance($USER->id);
        self::validate_context($context);

        // Get data from DB.
        $teamworkdata = $DB->get_record('local_teamwork', array('moduleid' => $activityid, 'type' => $moduletype));
        if ($teamworkdata) {
            // Decodede and parse unixdate for separate values.
            if (!empty($teamworkdata->teamuserenddate)) {
                $teamuserenddate = new DateTime("now", core_date::get_server_timezone_object());
                $teamuserenddate->setTimestamp($teamworkdata->teamuserenddate);
            } else {
                $teamuserenddate = new DateTime(
                        "7 days",
                        core_date::get_server_timezone_object()
                ); // If time was not defined - we suggest 7 days window as default.
            }
            $teamworkdata->endday = $teamuserenddate->format('d');
            $teamworkdata->endmonth = $teamuserenddate->format('m');
            $teamworkdata->endyear = $teamuserenddate->format('Y');
            $teamworkdata->endhour = $teamuserenddate->format('H');
            $teamworkdata->endmin = $teamuserenddate->format('i');

            // Create months array for select tag.
            $monthselect = array();
            for ($i = 1; $i <= 12; $i++) {
                $monthselect[$i - 1]['mnum'] = $i;
                $monthselect[$i - 1]['mname'] = get_string('month' . $i, 'local_teamwork');
                if ($monthselect[$i - 1]['mnum'] == $teamworkdata->endmonth) {
                    $monthselect[$i - 1]['selected'] = 'selected';
                }
            }
            $teamworkdata->monthselect = $monthselect;

            if ($teamworkdata->teamuserallowenddate == 1) {
                $teamworkdata->userenddateallowchecked = "checked";
                $teamworkdata->userenddateallowvalue = "1";
            } else {
                $teamworkdata->userenddateallowvalue = "0";
                $teamworkdata->userenddatedisabled = 'disabled="disabled"';
            }

            // Render the popup.
            $html = $OUTPUT->render_from_template('local_teamwork/student_settings', $teamworkdata);
        }

        $arrcontent = array(
                'content' => $html,
                'header' => get_string('headerstudentsettings', 'local_teamwork'),
        );

        return array('result' => json_encode($arrcontent));
    }

    // Save student settings popup data.

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function student_settings_popup_data_parameters() {
        return new external_function_parameters(
                array(
                        'courseid' => new external_value(PARAM_INT, 'course id int', VALUE_DEFAULT, null),
                        'activityid' => new external_value(PARAM_INT, 'activity id int', VALUE_DEFAULT, null),
                        'moduletype' => new external_value(PARAM_RAW, 'moduletype text', VALUE_DEFAULT, null),
                        'teamnumbers' => new external_value(PARAM_INT, 'team numbers int', VALUE_DEFAULT, 10),
                        'teamusernumbers' => new external_value(PARAM_INT, 'team user numbers int', VALUE_DEFAULT, 3),
                        'teamuserallowenddate' => new external_value(PARAM_INT, 'team user allowend date int', VALUE_DEFAULT, null),
                        'teamuserenddate' => new external_value(PARAM_INT, 'team user end date int', VALUE_DEFAULT, null),
                        'teamuserendmonth' => new external_value(PARAM_INT, 'team user end month int', VALUE_DEFAULT, null),
                        'teamuserendyear' => new external_value(PARAM_INT, 'team user end year int', VALUE_DEFAULT, null),
                        'teamuserendhour' => new external_value(PARAM_INT, 'team user end hour int', VALUE_DEFAULT, null),
                        'teamuserendminute' => new external_value(PARAM_INT, 'team user end minute int', VALUE_DEFAULT, null),
                )
        );
    }

    /**
     * Returns result
     *
     * @return result
     */
    public static function student_settings_popup_data_returns() {
        return new external_single_structure(
                array(
                        'result' => new external_value(PARAM_RAW, 'result bool'),
                )
        );
    }

    /**
     * Add share task
     *
     * @param int $courseid
     * @param int $activityid
     * @param text $moduletype
     * @param int $teamnumbers
     * @param int $teamusernumbers
     * @param int $teamuserallowenddate
     * @param int $teamuserenddate
     * @param int $teamuserendmonth
     * @param int $teamuserendyear
     * @param int $teamuserendhour
     * @param int $teamuserendminute
     * @return array
     */
    public static function student_settings_popup_data($courseid, $activityid, $moduletype, $teamnumbers, $teamusernumbers,
            $teamuserallowenddate, $teamuserenddate, $teamuserendmonth, $teamuserendyear, $teamuserendhour, $teamuserendminute) {
        global $DB, $USER;

        $context = context_user::instance($USER->id);
        self::validate_context($context);

        $teamnumbers = (empty($teamnumbers)) ? 10 : $teamnumbers;
        $teamusernumbers = (empty($teamusernumbers)) ? 3 : $teamusernumbers;

        $teamuserenddatestring = $teamuserendyear . '-'
                . $teamuserendmonth . '-'
                . $teamuserenddate . 'T'
                . $teamuserendhour . ':'
                . $teamuserendminute . ':00';
        $teamuserenddate = new DateTime($teamuserenddatestring, core_date::get_server_timezone_object());

        if ($teamuserenddate) {
            $teamuserenddateunix = $teamuserenddate->getTimestamp(); // Get unixtime from input data.
        } else {
            $teamuserenddateunix = null; // Set end time as null.
        }

        $answer = array();

        // Update students limits in DB.
        $teamworkdata = $DB->get_record('local_teamwork', array('moduleid' => $activityid, 'type' => $moduletype));
        if (!empty($teamworkdata)) {
            $teamworkdata->teamnumbers = $teamnumbers;
            $teamworkdata->teamusernumbers = $teamusernumbers;
            $teamworkdata->teamuserallowenddate = $teamuserallowenddate;
            if ($teamuserallowenddate == "0") {
                $teamworkdata->teamuserenddate = null;
            } else if ($teamuserallowenddate == "1") {
                $teamworkdata->teamuserenddate = $teamuserenddateunix;
            }
            $result = $DB->update_record('local_teamwork', $teamworkdata);
        } else {
            $answer = array('error' => 2, 'errormsg' => get_string(
                    'error_no_db_entry',
                    'local_teamwork')
            ); // Send error to front.
        }

        return array('result' => json_encode($answer));
    }

    // Drag student to/from card.

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function drag_student_card_parameters() {
        return new external_function_parameters(
                array(
                        'courseid' => new external_value(PARAM_INT, 'course id int', VALUE_DEFAULT, null),
                        'activityid' => new external_value(PARAM_INT, 'activity id int', VALUE_DEFAULT, null),
                        'moduletype' => new external_value(PARAM_RAW, 'moduletype text', VALUE_DEFAULT, null),
                        'selectgroupid' => new external_value(PARAM_RAW, 'selectgroupid text', VALUE_DEFAULT, null),
                        'newteamspost' => new external_value(PARAM_RAW, 'newteamspost text', VALUE_DEFAULT, null),
                        'draguserid' => new external_value(PARAM_INT, 'drag user id int', VALUE_DEFAULT, null),
                )
        );
    }

    /**
     * Returns result
     *
     * @return result
     */
    public static function drag_student_card_returns() {
        return new external_single_structure(
                array(
                        'result' => new external_value(PARAM_RAW, 'result bool'),
                )
        );
    }

    /**
     * Add share task
     *
     * @param int $courseid
     * @param int $activityid
     * @param text $moduletype
     * @param text $selectgroupid
     * @return array
     */
    public static function drag_student_card($courseid, $activityid, $moduletype, $selectgroupid, $newteamspost, $draguserid) {
        global $USER, $DB, $USER;

        $context = context_user::instance($USER->id);
        self::validate_context($context);

        $newteams = json_decode($newteamspost);

        $teamwork = $DB->get_record('local_teamwork', array('moduleid' => $activityid, 'type' => $moduletype));

        // Validate drag and drop.
        if (if_user_student_on_course($courseid) && $draguserid != $USER->id) {
            $students = get_students_by_select($selectgroupid, $courseid, $activityid, $moduletype);

            $flag = 0;
            foreach ($students as $student) {
                if ($student->userid == $draguserid) {
                    $flag = 1;
                }
            }

            if (!$flag) {
                return json_encode(array('error' => 1, 'errormsg' => get_string('errordragdrop', 'local_teamwork')));
            }
        }

        foreach ($newteams as $team) {
            if (!empty($team->teamid)) {

                // If action is done by student - apply some locals, limits or additional actions.
                if (if_user_student_on_course($courseid)) {

                    // SF - #753 - Validate number of the team users. Do not add new team member if limit is exceeded.
                    if (count($team->studentid) > $teamwork->teamusernumbers && !empty($teamwork->teamusernumbers)) {
                        continue;
                    }

                    // SG - #754 - don't let user drag another, if he/she doesn/t belong to this team.
                    if (!in_array($USER->id, $team->studentid) && $draguserid != $USER->id) {
                        continue;
                    }

                    // SG - #855 - remove card, if empty team after dragging self out of team.
                    if (empty($team->studentid) && $draguserid == $USER->id) {
                        $DB->delete_records('local_teamwork_groups', array('id' => $team->teamid));
                        $DB->delete_records('local_teamwork_members', array('teamworkgroupid' => $team->teamid));
                    }
                }

                // Step 1.
                $arrstudentsrequest = array();
                foreach ($team->studentid as $studentid) {
                    if (!empty($studentid)) {
                        $arrstudentsrequest[] = $studentid;
                        $obj = $DB->get_record('local_teamwork_members', array(
                                        'teamworkgroupid' => $team->teamid,
                                        'userid' => $studentid)
                        );
                        if (empty($obj)) {
                            $dataobject = new stdClass();
                            $dataobject->teamworkgroupid = $team->teamid;
                            $dataobject->userid = $studentid;
                            $dataobject->timecreated = time();
                            $dataobject->timemodified = time();
                            $DB->insert_record('local_teamwork_members', $dataobject);
                        }
                    }
                }

                // Step 2.
                $obj = $DB->get_records('local_teamwork_members', array('teamworkgroupid' => $team->teamid));
                foreach ($obj as $item) {
                    if (!in_array($item->userid, $arrstudentsrequest)) {
                        $DB->delete_records('local_teamwork_members', array('id' => $item->id));
                    }
                }
            } // If !empty team.
        } // End foreach newteams/team.

        return array('result' => json_encode(array()));
    }

    // Render teams card.

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function render_teams_card_parameters() {
        return new external_function_parameters(
                array(
                        'courseid' => new external_value(PARAM_INT, 'course id int', VALUE_DEFAULT, null),
                        'activityid' => new external_value(PARAM_INT, 'activity id int', VALUE_DEFAULT, null),
                        'moduletype' => new external_value(PARAM_RAW, 'moduletype text', VALUE_DEFAULT, null),
                        'selectgroupid' => new external_value(PARAM_RAW, 'selectgroupid text', VALUE_DEFAULT, null),
                )
        );
    }

    /**
     * Returns result
     *
     * @return result
     */
    public static function render_teams_card_returns() {
        return new external_single_structure(
                array(
                        'result' => new external_value(PARAM_RAW, 'result bool'),
                )
        );
    }

    /**
     * Add share task
     *
     * @param int $courseid
     * @param int $activityid
     * @param text $moduletype
     * @param text $selectgroupid
     * @return array
     */
    public static function render_teams_card($courseid, $activityid, $moduletype, $selectgroupid) {
        global $OUTPUT, $USER;

        $context = context_user::instance($USER->id);
        self::validate_context($context);

        $arrgroupid = json_decode($selectgroupid);

        $data = array();
        $data['cards'] = get_cards($activityid, $moduletype, $courseid, $arrgroupid[0]);
        $data['if_user_teacher'] = if_user_teacher_on_course($courseid);
        $data['allow_add_teams'] = allow_add_teams($courseid, $activityid, $arrgroupid[0]);

        $html = $OUTPUT->render_from_template('local_teamwork/teams-card', $data);

        $arrcontent = array(
                'content' => $html,
                'header' => '',
        );

        return array('result' => json_encode($arrcontent));
    }

    // Render student list.

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function render_student_list_parameters() {
        return new external_function_parameters(
                array(
                        'courseid' => new external_value(PARAM_INT, 'course id int', VALUE_DEFAULT, null),
                        'activityid' => new external_value(PARAM_INT, 'activity id int', VALUE_DEFAULT, null),
                        'moduletype' => new external_value(PARAM_RAW, 'moduletype text', VALUE_DEFAULT, null),
                        'selectgroupid' => new external_value(PARAM_RAW, 'selectgroupid text', VALUE_DEFAULT, null),
                )
        );
    }

    /**
     * Returns result
     *
     * @return result
     */
    public static function render_student_list_returns() {
        return new external_single_structure(
                array(
                        'result' => new external_value(PARAM_RAW, 'result bool'),
                )
        );
    }

    /**
     * Add share task
     *
     * @param int $courseid
     * @param int $activityid
     * @param text $moduletype
     * @param text $selectgroupid
     * @return array
     */
    public static function render_student_list($courseid, $activityid, $moduletype, $selectgroupid) {
        global $OUTPUT, $USER;

        $context = context_user::instance($USER->id);
        self::validate_context($context);

        $data = array();
        $data['list_students'] = get_students_by_select($selectgroupid, $courseid, $activityid, $moduletype);
        $html = $OUTPUT->render_from_template('local_teamwork/students', $data);

        $arrcontent = array(
                'content' => $html,
                'header' => '',
        );

        return array('result' => json_encode($arrcontent));
    }

    /**
     * Describes the parameters for save_rubrics_pdf.
     *
     * @return external_function_parameters
     * @since Moodle 3.2
     */
    public static function save_rubrics_pdf_parameters() {
        return new external_function_parameters (
                array(
                        'assignid' => new external_value(PARAM_INT, 'assign instance id'),
                        'userid' => new external_value(PARAM_INT, 'user id'),
                        'content' => new external_value(PARAM_TEXT, 'content pdf'),
                )
        );
    }

    /**
     * Update the module completion status.
     *
     * @param int $assignid assign instance id
     * @param int $userid user instance id
     * @param text $content content pdf
     * @return array of warnings and status result
     * @since Moodle 3.2
     */
    public static function save_rubrics_pdf($assignid, $userid, $content) {
        global $DB, $USER, $CFG, $USER;

        $context = context_user::instance($USER->id);
        self::validate_context($context);

        require_once($CFG->dirroot . '/question/editlib.php');

        $warnings = array();
        $result = array();

        $fs = get_file_storage();
        list($module, $cm) = get_module_from_cmid($assignid);
        $context = context_module::instance($assignid);

        $agrow = $DB->get_record('assign_grades', array('assignment' => $cm->instance, 'userid' => $userid));
        if (!empty($agrow)) {
            $itemid = $agrow->id;
        } else {
            $itemid = $DB->insert_record('assign_grades', array(
                    'assignment' => $cm->instance,
                    'userid' => $userid,
                    'grader' => $USER->id,
                    'grade' => -1,
                    'timecreated' => time(),
                    'timemodified' => time(),
            ));
        }

        $filedata = new \StdClass();
        $filedata->itemid = $itemid;
        $filedata->userid = $USER->id;
        $filedata->contextid = $context->id;
        $filedata->component = 'assignfeedback_file';
        $filedata->filearea = 'feedback_files';
        $filedata->filepath = '/';
        $filedata->filename = 'rubrics_' . time() . '.pdf';

        $content = base64_decode($content, false);

        // Save file.
        $fs->create_file_from_string($filedata, $content);

        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the view_assign return value.
     *
     * @return external_single_structure
     * @since Moodle 3.2
     */
    public static function save_rubrics_pdf_returns() {
        return new external_single_structure(
                array(
                        'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                        'warnings' => new external_warnings(),
                )
        );
    }

}
