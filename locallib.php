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
function get_module_name($activityid) {
    global $CFG, $USER, $DB, $PAGE;

    $sql = "SELECT m.name FROM {course_modules} AS cm LEFT JOIN {modules} AS m ON(cm.module=m.id) WHERE cm.id=?";
    $activity = $DB->get_record_sql($sql, array($activityid));

    if (!empty($activity)) {
        return $activity->name;
    }

    return false;
}

// Get mod events members.
function get_mod_events_members($activityid, $userid, $mod) {
    global $CFG, $USER, $DB, $PAGE;

    if (!in_array($mod, array('quiz', 'assign'))) {
        return false;
    }

    $teamwork = $DB->get_record('local_teamwork', array('moduleid' => $activityid, 'type' => $mod, 'active' => 1));
    if (!empty($teamwork)) {
        $data = array();

        $teamgroup = $DB->get_records('local_teamwork_groups', array('teamworkid' => $teamwork->id));
        foreach ($teamgroup as $group) {
            $sql = "
                SELECT tm.userid, CONCAT(u.firstname,' ',u.lastname) AS name
                FROM {local_teamwork_members} AS tm
                LEFT JOIN {user} AS u ON(u.id=tm.userid)
                WHERE tm.teamworkgroupid=?
            ";

            $users = $DB->get_records_sql($sql, array($group->id));
            $data[] = $users;
        }

        $result = array();
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
    global $CFG, $USER, $DB, $PAGE;

    if (is_siteadmin()) {
        return true;
    }

    $permissions = array('editingteacher', 'teacher', 'coursecreator');
    $context = context_course::instance($courseid);
    $roles = get_user_roles($context, $USER->id);

    foreach ($roles as $role) {
        if (in_array($role->shortname, $permissions)) {
            return true;
        }

    }

    return false;
}

// If user student on course.
function if_user_student_on_course($courseid) {
    global $CFG, $USER, $DB, $PAGE;

    $permissions = array('student');
    $context = context_course::instance($courseid);
    $roles = get_user_roles($context, $USER->id);

    foreach ($roles as $role) {
        if (in_array($role->shortname, $permissions)) {
            return true;
        }
    }

    return false;
}

// If user student on course.
function if_to_user_groups_empty($courseid) {
    global $CFG, $USER, $DB, $PAGE;

    $groups = view_groups_select($courseid);

    if (empty($groups)) {
        return true;
    }

    return false;
}

// Return users data for students fo HTML.
function return_data_for_student_tohtml($activityid, $moduletype, $courseid, $jsonselectgroupid) {
    global $CFG, $USER, $DB, $PAGE;

    $result = array();
    $cards = get_cards($activityid, $moduletype, $courseid, $jsonselectgroupid[0]);

    foreach ($cards as $card) {
        foreach ($card['users'] as $user) {
            if ($user->userid == $USER->id) {
                $result[] = $card;
                break;
            }
        }
    }
    return $result;
}

// If team block enable.
function if_teamwork_enable($activityid) {
    global $CFG, $USER, $DB, $PAGE;

    $teamwork = $DB->get_record('local_teamwork', array('moduleid' => $activityid, 'type' => get_module_name($activityid)));
    if (!empty($teamwork) && $teamwork->active == 1) {
        return true;
    }

    return false;
}

// If access to student.
function if_access_to_student($activityid) {
    global $CFG, $USER, $DB, $PAGE;

    $teamwork = $DB->get_record('local_teamwork', array('moduleid' => $activityid, 'type' => get_module_name($activityid)));
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
function students_button_status($activityid) {
    global $DB;

    $teamwork = $DB->get_record('local_teamwork', array('moduleid' => $activityid, 'type' => get_module_name($activityid)));
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
 * @param int $activityid
 * @return bool
 */
function allow_add_teams($courseid, $activityid, $selectgroupid) {
    global $DB;
    if (if_user_teacher_on_course($courseid)) {
        return true;
    } else if (if_user_student_on_course($courseid) && if_access_to_student($activityid)) {
        // Check if student can access to team building by time limit criteria and action is allowed by teacher.
        // SG - local 1. If teanumber limit is exceeded - don't allow add teams.
        $teamwork = $DB->get_record('local_teamwork', array('moduleid' => $activityid, 'type' => get_module_name($activityid)));
        if (!empty($teamwork)) {
            $groups = $DB->get_records('local_teamwork_groups', array('teamworkid' => $teamwork->id, 'groupid' => $selectgroupid));
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
function view_groups_select($courseid) {
    global $USER;

    $groupallstudents = get_groups_course($courseid);

    if (if_user_teacher_on_course($courseid)) {
        $obj = new stdClass();
        $obj->id = 0;
        $obj->courseid = $courseid;
        $obj->name = get_string('allstudents', 'local_teamwork');

        array_unshift($groupallstudents, $obj);

        return $groupallstudents;
    }

    // If student.
    if (if_user_student_on_course($courseid)) {
        $newgroupallstudents = array();

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

        // If seudent not in group.
        if(empty($newgroupallstudents)){
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
    global $CFG, $USER, $DB, $PAGE;

    $sql = "
        SELECT u.id as userid, CONCAT(u.firstname,' ',u.lastname) as name
        FROM {user} u
        INNER JOIN {role_assignments} ra ON ra.userid = u.id
        INNER JOIN {context} ct ON ct.id = ra.contextid
        INNER JOIN {course} c ON c.id = ct.instanceid
        INNER JOIN {role} r ON r.id = ra.roleid
        WHERE r.shortname=? AND c.id=?
    ";
    $students = $DB->get_records_sql($sql, array('student', $courseid));

    return array_values($students);
}

// Get students by group.
function get_students_by_group($groupid, $courseid) {
    $roles = array();
    $result = array();

    if ($groupmemberroles = groups_get_members_by_role($groupid, $courseid, 'u.id, ' . get_all_user_name_fields(true, 'u'))) {
        foreach ($groupmemberroles as $roleid => $roledata) {
            $shortroledata = new stdClass();
            $shortroledata->name = $roledata->name;
            $shortroledata->users = array();
            foreach ($roledata->users as $member) {
                $shortmember = new stdClass();
                $shortmember->userid = $member->id;
                $shortmember->name = fullname($member, true);
                $shortroledata->users[] = $shortmember;
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
function get_students_by_select($jsonselectid, $courseid, $activityid, $moduletype) {
    global $USER;

    $result = array();
    $arrselectid = json_decode($jsonselectid);

    $students = array();

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
    $cardsusers = array();
    $cards = get_cards($activityid, $moduletype, $courseid, $arrselectid[0]);
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
function get_cards($activityid, $moduletype, $courseid, $groupid) {
    global $CFG, $USER, $DB, $PAGE;

    $data = array();

    $teamwork = $DB->get_record('local_teamwork', array('moduleid' => $activityid, 'type' => $moduletype));
    if (!empty($teamwork)) {
        $teamgroup = $DB->get_records('local_teamwork_groups', array('teamworkid' => $teamwork->id, 'groupid' => $groupid));
        foreach ($teamgroup as $group) {
            $sql = "
                SELECT tm.userid, CONCAT(u.firstname,' ',u.lastname) AS name
                FROM {local_teamwork_members} AS tm
                LEFT JOIN {user} AS u ON(u.id=tm.userid)
                WHERE tm.teamworkgroupid=?
            ";
            $users = $DB->get_records_sql($sql, array($group->id));

            $tmp = array(
                    'cardid' => $group->id,
                    'cardname' => $group->name,
                    'ifedit' => (if_user_student_on_course($courseid)) ? true : false,
                    'users' => array(),
            );

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
function add_new_card($activityid, $moduletype, $selectgroupid, $users = array(), $courseid) {
    global $CFG, $USER, $DB, $PAGE;

    $teamwork = $DB->get_record('local_teamwork', array('moduleid' => $activityid, 'type' => $moduletype));
    if (!empty($teamwork)) {
        $groups = $DB->get_records('local_teamwork_groups', array('teamworkid' => $teamwork->id, 'groupid' => $selectgroupid));

        // SG - Do not create new card, if it is student and teamnumbers limit is exceeded. Throw an error.
        if (count($groups) >= $teamwork->teamnumbers && !empty($teamwork->teamnumbers) && if_user_student_on_course($courseid)) {
            return array('error' => 3, 'errormsg' => get_string('exceedteamnumberslimit', 'local_teamwork'));
        }

        // SG - #855 - if student and if is not in any team yet - add him to the new card.
        if (if_user_student_on_course($courseid)) {
            $groupid = json_encode(array($selectgroupid)); // Uly hack, to comply function get_students_by_select() below.

            // Get all student, who are out of teams (not in cards).
            $studselect = get_students_by_select($groupid, $courseid, $activityid, $moduletype);

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
                return array('error' => 4, 'errormsg' => get_string('exceedstudentteamslimit', 'local_teamwork'));
            }
        }

        $nextnumber = count($groups) + 1;

        $dataobject = new stdClass();
        $dataobject->teamworkid = $teamwork->id;
        $dataobject->name = get_string('defaultnamegroup', 'local_teamwork') . ' ' . $nextnumber;
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

function add_comments_to_assign($comment) {
    global $DB, $CFG;

    $submission = $DB->get_record('assign_submission', array('id' => $comment->itemid));

    // Get contents of current user.
    $obj = array(
            'contextid' => $comment->contextid,
            'component' => 'assignsubmission_comments',
            'commentarea' => 'submission_comments',
            'itemid' => $submission->id,
    );
    $usercomments = $DB->get_records('comments', $obj);

    $context = $DB->get_record('context', array('id' => $comment->contextid));
    $members = get_mod_events_members($context->instanceid, $submission->userid, 'assign');
    if ($members && !empty($members)) {
        foreach ($members as $member) {
            $assignrow = $DB->get_record('assign_submission', array(
                            'assignment' => $submission->assignment,
                            'userid' => $member->userid)
            );

            // Delete all comments by user.
            // Check if present comments and delete.
            $obj = array(
                    'contextid' => $comment->contextid,
                    'component' => 'assignsubmission_comments',
                    'commentarea' => 'submission_comments',
                    'itemid' => $assignrow->id,
            );
            $row = $DB->get_records('comments', $obj);
            if ($row) {
                $DB->delete_records('comments', $obj);
            }

            // Insert previus comments.
            foreach ($usercomments as $item) {
                unset($item->id);
                $item->itemid = $assignrow->id;
                $DB->insert_record('comments', $item);
            }

            $insert = array(
                    'contextid' => $comment->contextid,
                    'commentarea' => $comment->commentarea,
                    'itemid' => $assignrow->id,
                    'component' => $comment->component,
                    'content' => $comment->content,
                    'format' => $comment->format,
                    'userid' => $comment->userid,
                    'timecreated' => $comment->timecreated,
            );

            $DB->insert_record('comments', $insert);

        }
    }
}

/**
 * clone_user_assignment_rubrics
 *
 * @param mixed $userid
 * @param mixed $instanceid
 * @param mixed $targetusers
 *
 * @return void
 */
function clone_user_assignment_fillings($userid, $instanceid, $targetusers) {
    global $DB;
    $assign = $DB->get_record('course_modules', ['id' => $instanceid]);
    // Actual item id.
    $actualassigngrade = $DB->get_record('assign_grades', ['userid' => $userid, 'assignment' => $assign->instance]);
    $actualgradinginstance = $DB->get_record('grading_instances', ['itemid' => $actualassigngrade->id, 'status' => 1]);
    if (!$actualgradinginstance) {
        return false;
    }
    foreach ($targetusers as $targetuser) {
        // Old item id.
        $oldassigngrade = $DB->get_record('assign_grades', ['userid' => $targetuser->userid, 'assignment' => $assign->instance]);
        if (!$oldassigngrade) {
            $oldassigngrade = clone $actualassigngrade;
            unset($oldassigngrade->id);
            $oldassigngrade->userid = $targetuser->userid;
            $newoldassigngradeinsert = $DB->insert_record('assign_grades', $oldassigngrade);
            $oldassigngrade->id = $newoldassigngradeinsert;
        }

        // Delete old fillings.
        $oldinstancesforitem = $DB->get_records('grading_instances', ['itemid' => $oldassigngrade->id]);
        foreach ($oldinstancesforitem as $oldinstanceforitem) {
            $DB->delete_records('gradingform_guide_fillings', ['instanceid' => $oldinstanceforitem->id]);
            $DB->delete_records('gradingform_rubric_fillings', ['instanceid' => $oldinstanceforitem->id]);
        }

        // Delete old instances.
        $DB->delete_records('grading_instances', ['itemid' => $oldassigngrade->id]);
        $temp = clone $actualgradinginstance;
        $temp->itemid = $oldassigngrade->id;
        unset($temp->id);
        $temp->feedback = $targetuser->userid;
        $DB->insert_record('grading_instances', $temp);
        $gradinginstance = $DB->get_record('grading_instances', ['itemid' => $oldassigngrade->id, 'status' => 1]);

        // Guide.
        $actualfillings = $DB->get_records('gradingform_guide_fillings', ['instanceid' => $actualgradinginstance->id]);
        if (!empty($actualfillings)) {
            clone_fillings($actualfillings, $gradinginstance->id, 'gradingform_guide_fillings');
        }

        // Rubric.
        $actualfillings = $DB->get_records('gradingform_rubric_fillings', ['instanceid' => $actualgradinginstance->id]);
        if (!empty($actualfillings)) {
            clone_fillings($actualfillings, $gradinginstance->id, 'gradingform_rubric_fillings');
        }
    }
}

/**
 * update_user_final_grades
 *
 * @param mixed $event
 * @param mixed $members
 *
 * @return void
 */
function update_user_final_grades($event, $members) {
    global $DB;

    list ($course, $cm) = get_course_and_cm_from_cmid($event->contextinstanceid, 'assign');

    $sql = 'SELECT * FROM {assign_grades} WHERE userid = ? AND assignment = ? ORDER BY id DESC LIMIT 1';
    $finalassigngrade = $DB->get_record_sql($sql, array($event->relateduserid, $cm->instance));

    foreach ($members as $member) {
        $memberid = $member->userid;
        $sql = 'SELECT * FROM {assign_grades} WHERE userid = ? AND assignment = ? ORDER BY id DESC LIMIT 1';
        $currentassigngrade = $DB->get_record_sql($sql, array($memberid, $cm->instance));

        if (!empty($currentassigngrade)) {
            $finalassigngrade->id = $currentassigngrade->id;
            $finalassigngrade->userid = $memberid;
            $DB->update_record('assign_grades', $finalassigngrade);
        } else {
            unset($finalassigngrade->id);
            $finalassigngrade->userid = $memberid;
            $DB->insert_record('assign_grades', $finalassigngrade);
        }
    }

    $obj = array(
            'iteminstance' => $event->get_assign()->get_grade_item()->iteminstance,
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'courseid' => $course->id
    );
    $gradeitem = $DB->get_record('grade_items', $obj);

    $obj = array(
            'itemid' => $gradeitem->id,
            'userid' => $event->relateduserid
    );
    $finalgradegrade = $DB->get_record('grade_grades', $obj);

    foreach ($members as $member) {
        $memberid = $member->userid;
        $obj = array(
                'itemid' => $gradeitem->id,
                'userid' => $memberid
        );
        $currentgradegrade = $DB->get_record('grade_grades', $obj);

        if (!empty($currentgradegrade)) {
            $finalgradegrade->id = $currentgradegrade->id;
            $finalgradegrade->userid = $memberid;
            $DB->update_record('grade_grades', $finalgradegrade);
        } else {
            unset($finalgradegrade->id);
            $finalgradegrade->userid = $memberid;
            $DB->insert_record('grade_grades', $finalgradegrade);
        }
    }
}

/**
 * clone_fillings
 *
 * @param mixed $actualfillings
 * @param mixed $gradinginstanceid
 *
 * @return void
 */
function clone_fillings($actualfillings, $gradinginstanceid, $tablename = 'gradingform_rubric_fillings') {
    global $DB;

    foreach ($actualfillings as $newfilling) {
        $temp = clone $newfilling;
        $temp->instanceid = $gradinginstanceid;
        unset($temp->id);
        $DB->insert_record($tablename, $temp);
    }
}

class assign_custom extends assign {

    public function __construct($coursemodulecontext, $coursemodule, $course) {
        parent::__construct($coursemodulecontext, $coursemodule, $course);
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
                if ($scale = $DB->get_record('scale', array('id' => -($this->get_instance()->grade)))) {
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
