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
 * Plugin internal classes, functions and constants are defined here.
 *
 * @package     local_teamwork
 * @category    local
 * @copyright   2019 Devlion  <info@devlion.co
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

define('LOCAL_TEAMWORK_USERS_IN_GROUP', '5');

// Get module name.
function get_module_name($cmid) {
    global $DB;

    $sql = "SELECT m.name FROM {course_modules} cm LEFT JOIN {modules} m ON(cm.module=m.id) WHERE cm.id=?";
    $activity = $DB->get_record_sql($sql, [$cmid]);

    if (!empty($activity)) {
        return $activity->name;
    }

    return false;
}

// Get mod events members.
function get_mod_events_members($cmid, $userid, $moduletype) {
    global $DB;

    $teamwork = $DB->get_record('local_teamwork', ['moduleid' => $cmid, 'type' => $moduletype, 'active' => 1]);
    if (!empty($teamwork)) {
        $data = [];
        $cm = $DB->get_record('course_modules', ['id' => $cmid]);

        $teamgroup = $DB->get_records('local_teamwork_groups', ['teamworkid' => $teamwork->id]);
        foreach ($teamgroup as $group) {
            $sql = "
                SELECT tm.userid, CONCAT(u.firstname,' ',u.lastname) AS name
                FROM {local_teamwork_members} tm
                LEFT JOIN {user} u ON(u.id=tm.userid)
                JOIN {user_enrolments} ue_f ON ue_f.userid = u.id 
                JOIN {enrol} ej4_e ON (ej4_e.id = ue_f.enrolid AND ej4_e.courseid = ?) 
                WHERE u.suspended = 0 AND ue_f.status = 0 AND tm.teamworkgroupid=?
                AND ( 
                        (ue_f.timestart = '0' AND ue_f.timeend = '0') OR 
                        (ue_f.timestart = '0' AND ue_f.timeend > UNIX_TIMESTAMP()) OR 
                        (ue_f.timeend = '0' AND ue_f.timestart < UNIX_TIMESTAMP()) OR
                        (ue_f.timeend > UNIX_TIMESTAMP() AND ue_f.timestart < UNIX_TIMESTAMP())
                    )
            ";

            $users = $DB->get_records_sql($sql, [$cm->course, $group->id]);
            $data[] = $users;
        }

        $result = [];
        foreach ($data as $group) {
            foreach ($group as $k => $item) {
                if ($item->userid == $userid) {
                    $result = $group;
                    unset($result[$k]);
                    break;
                }
            }
        }

        return array_values($result);
    }

    return false;
}

// If user teacher on course.
function if_user_teacher_on_course($courseid) {
    global $USER;

    if (is_siteadmin()) {
        return true;
    }

    $context = context_course::instance($courseid);
    if (has_capability('local/teamwork:manageteams', $context, $USER->id)) {
        return true;
    }

    return false;
}

// If user student on course.
function if_user_student_on_course($courseid) {
    global $USER;

    $permissions = ['student'];
    $context = context_course::instance($courseid);
    $roles = get_user_roles($context, $USER->id);

    foreach ($roles as $role) {
        if (in_array($role->shortname, $permissions)) {
            return true;
        }
    }

    return false;
}

// Return users data for students fo HTML.
function return_data_for_student_tohtml($cmid, $moduletype, $courseid) {
    global $USER;

    $groups = view_groups_select($courseid, $cmid);

    $result = [];
    foreach ($groups as $group) {
        $cards = get_cards($cmid, $moduletype, $courseid, $group->id);

        foreach ($cards as $card) {
            foreach ($card['users'] as $user) {
                if ($user->userid == $USER->id) {
                    $result[] = $card;
                    break;
                }
            }
        }
    }

    return $result;
}

// If team block enable.
function if_teamwork_enable($cmid) {
    global $DB;

    $teamwork = $DB->get_record('local_teamwork', ['moduleid' => $cmid, 'type' => get_module_name($cmid)]);
    if (!empty($teamwork) && $teamwork->active == 1) {
        return true;
    }

    return false;
}

// If access to student.
function if_access_to_student($cmid) {
    global $DB;

    $teamwork = $DB->get_record('local_teamwork', ['moduleid' => $cmid, 'type' => get_module_name($cmid)]);
    if (!empty($teamwork)) {
        // Check if enddate validation is off and studentediting is on.
        if ($teamwork->teamuserallowenddate == 0 && $teamwork->studentediting == 1) {
            return true;
        } else if ($teamwork->teamuserallowenddate == 1 && $teamwork->studentediting == 1) {
            // Check allowed access time.
            $now = new DateTime("now", core_date::get_server_timezone_object());
            $teamuserenddate = clone($now);
            $teamuserenddate->setTimestamp($teamwork->teamuserenddate);
            if ($now < $teamuserenddate || empty($teamwork->teamuserenddate)) {
                return true;
            }
        }
    }

    return false;
}

// Status of the button - Choice by students.
function students_button_status($cmid) {
    global $DB;

    $teamwork = $DB->get_record('local_teamwork', ['moduleid' => $cmid, 'type' => get_module_name($cmid)]);
    if (!empty($teamwork) && $teamwork->studentediting == 1) {
        return true;
    } else {
        return false;
    }
}

/**
 * Function defines, either allow for user (teacher or student) to display button for adding teams if conditions are met, or not
 * SG - #855
 *
 * @param int $courseid
 * @param int $cmid
 * @return bool
 */
function allow_add_teams($courseid, $cmid, $selectgroupid) {
    global $DB;

    if (if_user_teacher_on_course($courseid)) {
        return true;
    } else if (if_user_student_on_course($courseid) && if_access_to_student($cmid)) {
        // Check if student can access to team building by time limit criteria and action is allowed by teacher.
        // SG - local 1. If teanumber limit is exceeded - don't allow add teams.
        $teamwork = $DB->get_record('local_teamwork', ['moduleid' => $cmid, 'type' => get_module_name($cmid)]);
        if (!empty($teamwork)) {
            $groups = $DB->get_records('local_teamwork_groups', ['teamworkid' => $teamwork->id, 'groupid' => $selectgroupid]);
            if (count($groups) >= $teamwork->teamnumbers && !empty($teamwork->teamnumbers)) {
                return false;
            }
        }

        // SG - if is student and access is granted and locals didn't worked - allow add teams.
        return true;
    } else {
        return false;
    }
}

// Get groups of course.
function get_groups_course($courseid) {
    $array = groups_get_all_groups($courseid);
    return array_values($array);
}

// Get groups of course and all users.
function view_groups_select($courseid, $cmid) {
    global $USER, $DB;

    $course = $DB->get_record('course', ['id' => $courseid]);
    $cm = $DB->get_record('course_modules', ['id' => $cmid]);

    $activegroup = groups_get_activity_group($cm);
    $groupallstudents = get_groups_course($courseid);

    if ($course && in_array($course->groupmode, [1]) && $activegroup != 0) {
        $tmp = [];
        foreach ($groupallstudents as $item) {
            if ($item->id == $activegroup) {
                $tmp[] = $item;
            }
        }

        $groupallstudents = $tmp;
    }

    if (if_user_teacher_on_course($courseid)) {

        $obj = new \stdClass();
        $obj->id = 0;
        $obj->courseid = $courseid;
        $obj->name = get_string('allstudents', 'local_teamwork');

        array_unshift($groupallstudents, $obj);

        //if ($course && in_array($course->groupmode, [1, 2]) && $activegroup == 0) {
        //    $obj = new \stdClass();
        //    $obj->id = -1;
        //    $obj->courseid = $courseid;
        //    $obj->name = get_string('selectgroup', 'local_teamwork');
        //
        //    array_unshift($groupallstudents, $obj);
        //}

        return $groupallstudents;
    }

    // If student.
    if (if_user_student_on_course($courseid)) {
        $newgroupallstudents = [];

        // If student in group.
        foreach ($groupallstudents as $group) {
            $students = get_students_by_group($group->id, $courseid);

            // Ok if student exists.
            $flag = 0;
            foreach ($students as $student) {
                if ($student->userid == $USER->id) {
                    $flag = 1;
                }
            }

            if ($flag) {
                $newgroupallstudents[] = $group;
            }
        }

        // If student not in group.
        if (empty($newgroupallstudents)) {
            $obj = new stdClass();
            $obj->id = 0;
            $obj->courseid = $courseid;
            $obj->name = get_string('allstudents', 'local_teamwork');

            array_unshift($newgroupallstudents, $obj);
        }

        return $newgroupallstudents;
    }
}

// Get student users of course.
function get_students_course($courseid) {
    global $CFG, $DB;

    // PTL-6209 Roles to use when populating users in the "student" list
    $teamstudentsroles = $CFG->teamstudentsroles ?? "'student'";

    $sql = "
        SELECT u.id as userid, CONCAT(u.firstname,' ',u.lastname) as name
        FROM {user} u
        INNER JOIN {role_assignments} ra ON ra.userid = u.id
        INNER JOIN {context} ct ON ct.id = ra.contextid
        INNER JOIN {course} c ON c.id = ct.instanceid
        INNER JOIN {role} r ON r.id = ra.roleid
        JOIN {user_enrolments} ue_f ON ue_f.userid = u.id
		JOIN {enrol} ej4_e ON (ej4_e.id = ue_f.enrolid AND ej4_e.courseid = ?)
        WHERE u.suspended = 0 AND ue_f.status = 0 AND c.id=?
          AND r.shortname IN ($teamstudentsroles)
        AND (
                (ue_f.timestart = '0' AND ue_f.timeend = '0') OR
                (ue_f.timestart = '0' AND ue_f.timeend > UNIX_TIMESTAMP()) OR
                (ue_f.timeend = '0' AND ue_f.timestart < UNIX_TIMESTAMP()) OR
                (ue_f.timeend > UNIX_TIMESTAMP() AND ue_f.timestart < UNIX_TIMESTAMP())
            )
    ";
    $students = $DB->get_records_sql($sql, [$courseid, $courseid]);

    return array_values($students);
}

// Get students by group.
function get_students_by_group($groupid, $courseid) {
    global $DB;

    $roles = $result = [];

    $userfieldsapi = \core_user\fields::for_name();
    $allnames = $userfieldsapi->get_sql('u', false, '', '', false)->selects;

    if ($groupmemberroles = groups_get_members_by_role($groupid, $courseid, 'u.id, ' . $allnames)) {
        foreach ($groupmemberroles as $roleid => $roledata) {
            $shortroledata = new stdClass();
            $shortroledata->name = $roledata->name;
            $shortroledata->users = [];
            foreach ($roledata->users as $member) {
                $sql = "
                    SELECT *
                    FROM {user} u
                    INNER JOIN {role_assignments} ra ON ra.userid = u.id      
                    INNER JOIN {context} ct ON ct.id = ra.contextid
                    INNER JOIN {course} c ON c.id = ct.instanceid            
                    JOIN {user_enrolments} ue_f ON ue_f.userid = u.id 
                    JOIN {enrol} ej4_e ON (ej4_e.id = ue_f.enrolid AND ej4_e.courseid = ?)
                    WHERE u.suspended = 0 AND ue_f.status = 0 AND c.id=? AND u.id=?
                    AND ( 
                            (ue_f.timestart = '0' AND ue_f.timeend = '0') OR 
                            (ue_f.timestart = '0' AND ue_f.timeend > UNIX_TIMESTAMP()) OR 
                            (ue_f.timeend = '0' AND ue_f.timestart < UNIX_TIMESTAMP()) OR
                            (ue_f.timeend > UNIX_TIMESTAMP() AND ue_f.timestart < UNIX_TIMESTAMP())
                        )
                ";
                $res = $DB->get_records_sql($sql, [$courseid, $courseid, $member->id]);

                if (!empty($res)) {
                    $shortmember = new stdClass();
                    $shortmember->userid = $member->id;
                    $shortmember->name = fullname($member, true);
                    $shortroledata->users[] = $shortmember;
                }
            }
            $roles[] = $shortroledata;
        }
    }

    if (!empty($roles)) {
        foreach ($roles as $role) {
            $result = array_merge($result, $role->users);
        }
    }

    return $result;
}

// Get students by select.
function get_students_by_select($jsonselectid, $courseid, $cmid, $moduletype) {

    $result = $students = [];
    $arrselectid = json_decode($jsonselectid);

    foreach ($arrselectid as $selectid) {
        if ($selectid == 0) {
            $newstudents = get_students_course($courseid);
        } else {
            $newstudents = get_students_by_group($selectid, $courseid);
        }

        // Unset if student exists.
        foreach ($newstudents as $k => $newstudent) {
            foreach ($students as $student) {
                if ($newstudent->userid == $student->userid) {
                    unset($newstudents[$k]);
                }
            }
        }

        $students = array_merge($students, $newstudents);
    }

    // Get Users from cards.
    $cardsusers = [];
    $cards = get_cards($cmid, $moduletype, $courseid, $arrselectid[0]);
    foreach ($cards as $card) {
        foreach ($card['users'] as $user) {
            $cardsusers[] = $user;
        }
    }

    if (!empty($cardsusers)) {
        $cardsids = array_column($cardsusers, 'userid');

        foreach ($students as $item) {
            if (!in_array($item->userid, $cardsids)) {
                $result[] = $item;
            }
        }
    } else {
        $result = $students;
    }

    return $result;
}

// Return cards.
function get_cards($cmid, $moduletype, $courseid, $groupid) {
    global $USER, $DB;

    $data = [];

    $teamwork = $DB->get_record('local_teamwork', ['moduleid' => $cmid, 'type' => $moduletype]);
    if (!empty($teamwork)) {

        $sql = "
            SELECT *
            FROM {local_teamwork_groups}
            WHERE teamworkid=? AND groupid=?
            ORDER BY name ASC
        ";

        $teamgroup = $DB->get_records_sql($sql, [$teamwork->id, $groupid]);

        foreach ($teamgroup as $group) {
            $sql = "
                SELECT tm.userid, CONCAT(u.firstname,' ',u.lastname) AS name
                FROM {local_teamwork_members} AS tm
                LEFT JOIN {user} AS u ON(u.id=tm.userid)
                JOIN {user_enrolments} ue_f ON ue_f.userid = u.id 
                JOIN {enrol} ej4_e ON (ej4_e.id = ue_f.enrolid AND ej4_e.courseid = ?) 
                WHERE u.suspended = 0 AND ue_f.status = 0 AND tm.teamworkgroupid=?
                AND ( 
                        (ue_f.timestart = '0' AND ue_f.timeend = '0') OR 
                        (ue_f.timestart = '0' AND ue_f.timeend > UNIX_TIMESTAMP()) OR 
                        (ue_f.timeend = '0' AND ue_f.timestart < UNIX_TIMESTAMP()) OR
                        (ue_f.timeend > UNIX_TIMESTAMP() AND ue_f.timestart < UNIX_TIMESTAMP())
                    )
            ";
            $users = $DB->get_records_sql($sql, [$courseid, $group->id]);

            $tmp = [
                    'cardid' => $group->id,
                    'cardname' => $group->name,
                    'ifedit' => (if_user_student_on_course($courseid)) ? true : false,
                    'users' => [],
            ];

            if (!empty($users)) {

                // If student.
                if (if_user_student_on_course($courseid)) {
                    foreach ($users as $user) {
                        if ($user->userid != $USER->id) {
                            $user->notdragclass = 'stop-drag-item';
                        }
                    }

                }

                $tmp['users'] = array_values($users);

                end($tmp['users']);
                $key = key($tmp['users']);
                $tmp['users'][$key]->last = true;
                reset($tmp['users']);

            }

            $data[] = $tmp;
        }
    }

    return $data;
}

// Add new card with/witout users.
function add_new_card($cmid, $moduletype, $selectgroupid, $courseid, $name = null, $users = []) {
    global $USER, $DB;

    $teamwork = $DB->get_record('local_teamwork', ['moduleid' => $cmid, 'type' => $moduletype]);
    if (!empty($teamwork)) {
        $groups = $DB->get_records('local_teamwork_groups', ['teamworkid' => $teamwork->id, 'groupid' => $selectgroupid]);

        // SG - Do not create new card, if it is student and teamnumbers limit is exceeded. Throw an error.
        if (count($groups) >= $teamwork->teamnumbers && !empty($teamwork->teamnumbers) && if_user_student_on_course($courseid)) {
            return ['error' => 3, 'errormsg' => get_string('exceedteamnumberslimit', 'local_teamwork')];
        }

        // SG - #855 - if student and if is not in any team yet - add him to the new card.
        if (if_user_student_on_course($courseid)) {

            // Uly hack, to comply function get_students_by_select() below.
            $groupid = json_encode([$selectgroupid]);

            // Get all student, who are out of teams (not in cards).
            $studselect = get_students_by_select($groupid, $courseid, $cmid, $moduletype);

            foreach ($studselect as $stud) {
                if ($stud->userid == $USER->id) {
                    $tmpuser = new stdClass();
                    $tmpuser->userid = $USER->id;
                    $tmpusers[] = $tmpuser; // Add current user to the new card below in this function.
                }
            }
            if (!empty($tmpusers)) {
                $users = array_merge($users, $tmpusers);
            } else {
                return ['error' => 4, 'errormsg' => get_string('exceedstudentteamslimit', 'local_teamwork')];
            }
        }

        // Update counter.
        $teamwork->counter = $teamwork->counter + 1;
        $DB->update_record('local_teamwork', $teamwork);

        $nextnumber = str_pad($teamwork->counter, 2, "0", STR_PAD_LEFT);

        $dataobject = new stdClass();
        $dataobject->teamworkid = $teamwork->id;
        $dataobject->name = $name ?? get_string('defaultnamegroup', 'local_teamwork') . ' ' . $nextnumber;
        $dataobject->groupid = $selectgroupid;
        $dataobject->timecreated = time();
        $dataobject->timemodified = time();
        $newgroupid = $DB->insert_record('local_teamwork_groups', $dataobject);

        // Insert users.
        if (!empty($users)) {
            foreach ($users as $item) {
                $dataobject = new stdClass();
                $dataobject->teamworkgroupid = $newgroupid;
                $dataobject->userid = $item->userid;
                $dataobject->timecreated = time();
                $dataobject->timemodified = time();
                $DB->insert_record('local_teamwork_members', $dataobject);
            }
        }
    }

    return true;
}

function delete_user_submit($userid, $teamid, $cmid, $ifdelete) {
    global $DB;

    if ($ifdelete) {

        // Delete grade.
        $cm = $DB->get_record('course_modules', ['id' => $cmid]);
        if (!$cm) {
            return false;
        }

        $context = \context_module::instance($cm->id);
        $module = $DB->get_record('modules', ['id' => $cm->module]);

        $gradeitems = $DB->get_record('grade_items', [
            'itemtype' => 'mod',
            'itemmodule' => $module->name,
            'iteminstance' => $cm->instance,
        ]);

        if (!$gradeitems) {
            return ['result' => json_encode(false)];
        }

        $DB->delete_records('grade_grades', ['itemid' => $gradeitems->id, 'userid' => $userid]);

        $assigngrades = $DB->get_records('assign_grades', ['assignment' => $cm->instance, 'userid' => $userid]);

        $DB->delete_records('assign_grades', ['assignment' => $cm->instance, 'userid' => $userid]);

        // Delete comments.
        $submissions = $DB->get_records('assign_submission', ['assignment' => $cm->instance, 'userid' => $userid]);
        foreach ($submissions as $submission) {
            $obj = [
                'component' => 'assignsubmission_comments',
                'commentarea' => 'submission_comments',
                'itemid' => $submission->id,
            ];
            $DB->delete_records('comments', $obj);
        }

        // Delete submitted data.
        $DB->delete_records('assign_submission', ['assignment' => $cm->instance, 'userid' => $userid]);

        // Delete feedback files.
        foreach ($assigngrades as $grade) {
            $DB->delete_records('assignfeedback_editpdf_annot', ['gradeid' => $grade->id]);
            $DB->delete_records('assignfeedback_editpdf_cmnt', ['gradeid' => $grade->id]);

            // Table files.
            $DB->delete_records('files', [
                'contextid' => $context->id,
                'itemid' => $grade->id,
                'component' => 'assignfeedback_editpdf'
            ]);
        }
    }

    // Delete user from card.
    $DB->delete_records('local_teamwork_members', ['teamworkgroupid' => $teamid, 'userid' => $userid]);

    return true;
}

function check_user_submit($userid, $cmid) {
    global $DB;

    $cm = $DB->get_record('course_modules', ['id' => $cmid]);
    if (!$cm) {
        return false;
    }

    $module = $DB->get_record('modules', ['id' => $cm->module]);

    $gradeitems = $DB->get_record('grade_items', [
            'itemtype' => 'mod',
            'itemmodule' => $module->name,
            'iteminstance' => $cm->instance,
    ]);

    if (!$gradeitems) {
        return false;
    }

    //if ($gradegrades = $DB->get_records('grade_grades', ['itemid' => $gradeitems->id, 'userid' => $userid])) {
    //    return true;
    //}

    if ($assigngrades = $DB->get_records('assign_grades', ['assignment' => $cm->instance, 'userid' => $userid])) {
        return true;
    }

    if ($submissions = $DB->get_records('assign_submission', ['assignment' => $cm->instance, 'status' => 'submitted', 'userid' => $userid])) {
        return true;
    }

    return false;
}

function local_teamwork_voice_init() {

    $translations = get_string_manager()->get_list_of_translations();

    $languages = [];
    foreach ($translations as $key => $translation) {
        $lang = new stdClass;
        $lang->code = local_teamwork_get_full_lang_code($key);
        $lang->name = $translation;
        $languages[local_teamwork_get_full_lang_code($key)] = $lang;
    }

    $html = local_teamwork_get_mainhtml(array_values($languages));

    return $html;
}

function local_teamwork_get_full_lang_code($shortlangcode) {

    switch ($shortlangcode) {
        case 'ru':
            $fulllangcode = 'ru-RU';
            break;
        case 'he_kids':
            $fulllangcode = 'iw-IL';
            break;
        case 'he':
            $fulllangcode = 'iw-IL';
            break;
        case 'en':
            $fulllangcode = 'en-US';
            break;
        default:
            $fulllangcode = 'en-US';
            break;
    }

    return $fulllangcode;
}

function local_teamwork_get_mainhtml($languages) {
    global $OUTPUT;

    $html = $OUTPUT->render_from_template('local_teamwork/menuitem', ['languages' => $languages]);

    return $html;
}

class assign_custom extends assign {

    public function __construct($coursemodulecontext, $coursemodule, $course) {
        parent::__construct($coursemodulecontext, $coursemodule, $course);
    }

    public function reopen_submission_if_required($userid, $submission, $addattempt) {
        return parent::reopen_submission_if_required($userid, $submission, $addattempt);
    }

    /**
     * Update a grade in the grade table for the assignment and in the gradebook.
     *
     * @param stdClass $grade a grade record keyed on id
     * @param bool $reopenattempt If the attempt reopen method is manual, allow another attempt at this assignment.
     * @return bool true for success
     */
    public function update_grade($grade, $reopenattempt = false) {
        global $DB;

        $grade->timemodified = time();

        if (!empty($grade->workflowstate)) {
            $validstates = $this->get_marking_workflow_states_for_current_user();
            if (!array_key_exists($grade->workflowstate, $validstates)) {
                return false;
            }
        }

        if ($grade->grade && $grade->grade != -1) {
            if ($this->get_instance()->grade > 0) {
                if (!is_numeric($grade->grade)) {
                    return false;
                } else if ($grade->grade > $this->get_instance()->grade) {
                    return false;
                } else if ($grade->grade < 0) {
                    return false;
                }
            } else {
                // This is a scale.
                if ($scale = $DB->get_record('scale', ['id' => -($this->get_instance()->grade)])) {
                    $scaleoptions = make_menu_from_list($scale->scale);
                    if (!array_key_exists((int) $grade->grade, $scaleoptions)) {
                        return false;
                    }
                }
            }
        }

        if (empty($grade->attemptnumber)) {
            // Set it to the default.
            $grade->attemptnumber = 0;
        }
        $DB->update_record('assign_grades', $grade);

        $submission = null;
        if ($this->get_instance()->teamsubmission) {
            $submission = $this->get_group_submission($grade->userid, 0, false);
        } else {
            $submission = $this->get_user_submission($grade->userid, false);
        }

        // Only push to gradebook if the update is for the latest attempt.
        // Not the latest attempt.
        if ($submission && $submission->attemptnumber != $grade->attemptnumber) {
            return true;
        }

        // If the conditions are met, allow another attempt.
        if ($submission) {
            $this->reopen_submission_if_required($grade->userid,
                    $submission,
                    $reopenattempt);
        }

        return true;
    }
}
