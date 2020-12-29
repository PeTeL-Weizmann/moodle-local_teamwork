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
 * Event observers.
 *
 * @package    local_teamwork
 * @copyright  2016 onwards - Davidson institute (Weizmann institute)
 * @author     Nadav Kavalerchik <nadav.kavalerchik@weizmann.ac.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_teamwork;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/teamwork/locallib.php');
require_once($CFG->dirroot . '/completion/completion_aggregation.php');
require_once($CFG->dirroot . '/completion/criteria/completion_criteria.php');
require_once($CFG->dirroot . '/completion/completion_completion.php');
require_once($CFG->dirroot . '/completion/completion_criteria_completion.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

class observer {

    /**
     * @param \mod_assign\event\submission_graded $event
     * @return bool
     * @throws \dml_exception
     * @throws \file_exception
     * @throws \file_reference_exception
     * @throws \stored_file_creation_exception
     */
    public static function update_team_members_grades(\mod_assign\event\submission_graded $event): bool {
        global $DB, $CFG, $USER;

        // Check if local on.
        $members = get_mod_events_members($event->contextinstanceid, $event->relateduserid, 'assign');
        if ($members == false) {
            return false;
        }

        if (empty($members)) {
            // Team leader user was not found.
            // Problem grade given to a user which is not leading (submitted) the team.
            return false;
        }

        // Clone user assigment rubrics and guide.
        clone_user_assignment_fillings($event->relateduserid, $event->contextinstanceid, $members);

        list ($course, $cm) = get_course_and_cm_from_cmid($event->contextinstanceid, 'assign');
        if (!\local_teamwork\common::is_submission_enable($cm->instance)) {
            return false;
        }
        $context = \context_module::instance($cm->id);

        $sql = 'SELECT * FROM {assign_grades} WHERE userid = ? AND assignment = ? ORDER BY id DESC LIMIT 1';
        $mainusergrades = $DB->get_record_sql($sql, array($event->relateduserid, $cm->instance));

        if (empty($mainusergrades)) {
            return true;
        }

        $mainusercomments =
                $DB->get_record('assignfeedback_comments', array('grade' => $mainusergrades->id, 'assignment' => $cm->instance));

        // Get source user submission from assign_submission.
        $obj = array(
                'userid' => $event->relateduserid,
                'assignment' => $event->get_assign()->get_grade_item()->iteminstance,
                'latest' => 1
        );
        $sourceusersubmission = $DB->get_record('assign_submission', $obj);

        // Get final from grade_grades.
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
        $gradegrade = $DB->get_record('grade_grades', $obj);

        // Main submission file.
        $mainusersubmissionfiles = $DB->get_records_sql("SELECT * FROM {files} WHERE component = 'assignsubmission_file' " .
                " AND filearea = 'submission_files' AND itemid = ? AND contextid = ? " .
                " AND filename != '.' ", array($sourceusersubmission->id, $event->get_context()->id));

        $pathnamehashessubmission = array();
        foreach ($mainusersubmissionfiles as $itemfile) {
            $pathnamehashessubmission[] = $itemfile->pathnamehash;
        }

        // Main edit file.
        $mainusereditfiles = $DB->get_records_sql("SELECT * FROM {files} WHERE component = 'assignfeedback_editpdf' " .
                " AND filearea = 'download' AND itemid = ? AND contextid = ? " .
                " AND filename != '.' ", array($mainusergrades->id, $event->get_context()->id));

        $pathnamehashesedit = array();
        foreach ($mainusereditfiles as $itemfile) {
            $pathnamehashesedit[] = $itemfile->pathnamehash;
        }

        // Copy previous edit file to feedback area for main user.
        self::copy_history_edit_file($event, $pathnamehashesedit);

        // Assign class.
        $assign = new \assign_custom($context, $cm, $course);
        // Set main user's grade to all other team members.
        foreach ($members as $member) {
            $memberid = $member->userid;

            self::delete_teacher_feedback($event, $memberid);

            // Copy main submission file to member.
            self::copy_files_to_member_assignsubmission($event, $pathnamehashessubmission, $memberid);

            $msubm = $assign->get_user_submission($memberid, 0);

            $usercontext = \context_user::instance($event->relateduserid);

            $obj = new \StdClass();
            $obj->id = $event->contextinstanceid;
            $obj->grade = (!empty($mainusergrades->grade)) ? $mainusergrades->grade : '';
            $obj->assignfeedbackcomments_editor = array(
                    'text' => (isset($mainusercomments->commenttext)) ? $mainusercomments->commenttext : '',
                    'format' => FORMAT_HTML
            );

            $obj->editpdf_source_userid = $event->relateduserid;
            $obj->draftitemid = time();
            $obj->assignfeedback_editpdf_haschanges = true;

            $obj->usercontextid = $usercontext->id;
            $obj->poodllfeedback = '';
            $obj->rownum = 0;
            $obj->useridlistid = '';
            $obj->attemptnumber = $msubm->attemptnumber;
            $obj->ajax = 0;
            $obj->userid = 0;
            $obj->sendstudentnotifications = 0;
            $obj->action = 'submitgrade';

            $assign->save_grade($memberid, $obj);
            // Update final grade_grades.
            $obj = array(
                    'itemid' => $gradeitem->id,
                    'userid' => $memberid
            );
            $currentgradegrade = $DB->get_record('grade_grades', $obj);

            if (!empty($currentgradegrade)) {
                $gradegrade->id = $currentgradegrade->id;
                $gradegrade->userid = $memberid;
                $DB->update_record('grade_grades', $gradegrade);
            } else {
                unset($gradegrade->id);
                $gradegrade->userid = $memberid;
                $DB->insert_record('grade_grades', $gradegrade);
            }

            // Update comments.
            $obj = array(
                    'contextid' => $event->contextid,
                    'component' => 'assignsubmission_comments',
                    'commentarea' => 'submission_comments',
                    'itemid' => $sourceusersubmission->id
            );
            $comments = $DB->get_records('comments', $obj);

            // Submitted data.
            $submission = $DB->get_record('assign_submission', array(
                            'assignment' => $cm->instance, 'userid' => $memberid, 'latest' => 1)
            );

            // Update submission.
            // Status was $submission->status = 'submitted'.
            $submission->status = $sourceusersubmission->status;
            $DB->update_record('assign_submission', $submission);

            // Check if present comments and delete.
            $obj = array(
                    'contextid' => $event->contextid,
                    'component' => 'assignsubmission_comments',
                    'commentarea' => 'submission_comments',
                    'itemid' => $submission->id
            );
            $row = $DB->get_records('comments', $obj);
            if ($row) {
                $DB->delete_records('comments', $obj);
            }

            // Insert new comments.
            foreach ($comments as $comment) {
                unset($comment->id);
                $comment->itemid = $submission->id;

                $DB->insert_record('comments', $comment);
            }

            // Add complete tasks to mod_checklist.
            $modnames = \core_component::get_plugin_list('mod');
            if (isset($modnames['checklist'])) {

                $obj = new \StdClass();
                $obj->userid = $event->relateduserid;

                $memberschecklist = $members;
                $memberschecklist[] = $obj;

                foreach ($memberschecklist as $member) {
                    $ci = $DB->get_record('checklist_item', array('moduleid' => $event->contextinstanceid));

                    if (!empty($ci)) {
                        $row = $DB->get_record('checklist_check', array('item' => $ci->id, 'userid' => $member->userid));

                        if (!empty($row)) {
                            $row->usertimestamp = time();
                            $row->teachermark = 1;
                            $row->teachertimestamp = time();
                            $row->teacherid = $USER->id;
                            $DB->update_record('checklist_check', $row);
                        } else {
                            $ins = new \StdClass();
                            $ins->item = $ci->id;
                            $ins->userid = $member->userid;
                            $ins->usertimestamp = time();
                            $ins->teachermark = 1;
                            $ins->teachertimestamp = time();
                            $ins->teacherid = $USER->id;

                            $DB->insert_record('checklist_check', $ins);
                        }
                    }
                }
            }

            // Main feedback file.
            $mainusersubmissionfiles = $DB->get_records_sql("SELECT * FROM {files} WHERE component = 'assignfeedback_file' " .
                    " AND filearea = 'feedback_files' AND itemid = ? AND contextid = ? " .
                    " AND filename != '.' ", array($mainusergrades->id, $event->get_context()->id));

            $pathnamehashesfeedback = array();
            foreach ($mainusersubmissionfiles as $itemfile) {
                $pathnamehashesfeedback[] = $itemfile->pathnamehash;
            }

            // Copy main feedback file to member.
            self::copy_files_to_member_assignfeedback($event, $pathnamehashesfeedback, $memberid);
        }

        update_user_final_grades($event, $members);

        // Copy main submission file to member.
        self::add_completion_to_member($event, $memberid);
        return true;
    }

    /**
     * @param \mod_assign\event\submission_created $event
     * @return bool
     * @throws \dml_exception
     */
    public static function update_team_memebers_submision_status_created(\mod_assign\event\submission_created $event): bool {
        global $DB;

        // Check if local on.
        $members = get_mod_events_members($event->contextinstanceid, $event->relateduserid, 'assign');
        if ($members == false) {
            return false;
        }

        if (empty($members)) {
            // Team leader user was not found.
            // Problem? grade given to a user which is not leading (submitted) the team.
            return false;
        }

        $cm = $DB->get_record('course_modules', array('id' => $event->contextinstanceid));
        if (!\local_teamwork\common::is_submission_enable($cm->instance)) {
            return false;
        }
        foreach ($members as $member) {
            $DB->set_field('assign_submission', 'status', 'submitted',
                    array('userid' => $member->userid, 'assignment' => $cm->instance));
        }

        // Update onlinetext.
        self::update_onlinetext($event, $members);

        return true;
    }

    public static function update_team_memebers_submision_status_updated(\mod_assign\event\submission_updated $event): bool {
        global $DB;

        // Check if local on.
        $members = get_mod_events_members($event->contextinstanceid, $event->relateduserid, 'assign');
        if ($members == false) {
            return false;
        }

        if (empty($members)) {
            // Team leader user was not found.
            // Problem? grade given to a user which is not leading (submitted) the team.
            return false;
        }

        $cm = $DB->get_record('course_modules', array('id' => $event->contextinstanceid));
        if (!\local_teamwork\common::is_submission_enable($cm->instance)) {
            return false;
        }

        foreach ($members as $member) {
            $DB->set_field('assign_submission', 'status', 'submitted',
                    array('userid' => $member->userid, 'assignment' => $cm->instance));
        }

        // Update onlinetext.
        self::update_onlinetext($event, $members);

        return true;
    }

    /**
     * @param \core\event\assessable_uploaded $event
     * @return bool
     * @throws \dml_exception
     */
    public static function update_team_memebers_submitted_files_uploaded(\core\event\assessable_uploaded $event): bool {
        global $CFG, $DB;

        // Check if local on.
        $members = get_mod_events_members($event->contextinstanceid, $event->userid, 'assign');

        if ($members == false) {
            return false;
        }

        if (empty($members)) {
            // Team leader user was not found.
            // Problem? grade given to a user which is not leading (submitted) the team.
            return false;
        }

        // SG - get Assign object for this cm.
        list ($course, $cm) = get_course_and_cm_from_cmid($event->contextinstanceid, 'assign');
        if (!\local_teamwork\common::is_submission_enable($cm->instance)) {
            return false;
        }
        $context = \context_module::instance($cm->id);
        $assign = new \assign($context, $cm, $course);

        foreach ($members as $i => $member) {
            // Get team leader's submission and update דורש מתן ציון.
            $obj = $assign->get_user_submission($member->userid, 1);
            $obj->timemodified = time();
            $DB->update_record('assign_submission', $obj);

            self::copy_files_to_member_assignsubmission($event, $event->other['pathnamehashes'], $member->userid);
            self::delete_teacher_feedback($event, $member->userid);
        }

        return true;
    }

    /**
     * @param \core\event\course_module_updated $event
     * @return bool
     * @throws \dml_exception
     * @throws \file_exception
     * @throws \file_reference_exception
     * @throws \stored_file_creation_exception
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event): bool {
        global $DB;

        $lt = $DB->get_record('local_teamwork', array('moduleid' => $event->contextinstanceid));

        if (!empty($lt)) {
            $DB->delete_records('local_teamwork', array('id' => $lt->id));

            $ltg = $DB->get_records('local_teamwork_groups', array('teamworkid' => $lt->id));
            $DB->delete_records('local_teamwork_groups', array('teamworkid' => $lt->id));

            foreach ($ltg as $item) {
                $DB->delete_records('local_teamwork_members', array('teamworkgroupid' => $item->id));
            }
        }

        return true;
    }

    /**
     * @param \assignsubmission_comments\event\comment_created $event
     * @return bool
     * @throws \dml_exception
     */
    public static function comment_created(\assignsubmission_comments\event\comment_created $event): bool {
        global $DB;

        $submission = $DB->get_record('assign_submission', ['id'=>$event->other['itemid']]);
        $currentuserid = $submission->userid;

        // Check if local on.
        $members = get_mod_events_members($event->contextinstanceid, $currentuserid, 'assign');
        if (empty($members)) {
            // Team leader user was not found.
            // Problem? grade given to a user which is not leading (submitted) the team.
            return false;
        }

        list ($course, $cm) = get_course_and_cm_from_cmid($event->contextinstanceid, 'assign');
        if (!\local_teamwork\common::is_submission_enable($cm->instance)) {
            return false;
        }
        $context = \context_module::instance($cm->id);
        $assign = new \assign($context, $cm, $course);

        $cuurentcomment = $DB->get_record('comments', ['id' => $event->objectid]);
        unset($cuurentcomment->id);
        unset($cuurentcomment->itemid);

        foreach ($members as $i => $member) {
            if ($currentuserid ==  $member->userid){
                continue;
            }
            $obj = $assign->get_user_submission($member->userid, true);
            $cuurentcomment->itemid=$obj->id;
            $DB->insert_record('comments',$cuurentcomment);
        }
        return true;
    }


    /**
     * @param \assignsubmission_comments\event\comment_deleted $event
     * @return bool
     * @throws \dml_exception
     */
    public static function comment_deleted(\assignsubmission_comments\event\comment_deleted $event): bool {
        global $DB;

        $submission = $DB->get_record('assign_submission', ['id'=>$event->other['itemid']]);
        $currentuserid = $submission->userid;

        // Check if local on.
        $members = get_mod_events_members($event->contextinstanceid, $currentuserid, 'assign');
        if (empty($members)) {
            // Team leader user was not found.
            // Problem? grade given to a user which is not leading (submitted) the team.
            return false;
        }

        list ($course, $cm) = get_course_and_cm_from_cmid($event->contextinstanceid, 'assign');
        if (!\local_teamwork\common::is_submission_enable($cm->instance)) {
            return false;
        }
        $context = \context_module::instance($cm->id);
        $assign = new \assign($context, $cm, $course);

        $data = array(
                'contextid' => $event->contextid,
                'component' => 'assignsubmission_comments',
                'commentarea' => 'submission_comments',
                'itemid' => $event->other['itemid']
        );

        $cuurentcomments = $DB->get_records('comments', $data);

        foreach ($members as $i => $member) {
            if ($currentuserid ==  $member->userid){
                continue;
            }
            $obj = $assign->get_user_submission($member->userid, true);
            $todelete = array(
                    'contextid' => $event->contextid,
                    'component' => 'assignsubmission_comments',
                    'commentarea' => 'submission_comments',
                    'itemid' => $obj->id
            );
            $DB->delete_records('comments',$todelete);
            foreach($cuurentcomments as $comment){
                unset($comment->id);
                unset($comment->itemid);
                $comment->itemid=$obj->id;
                $DB->insert_record('comments',$comment);
            }
        }
        return true;
    }


    public static function copy_files_to_member_assignfeedback($event, $pathnamehashes, $memberid): bool {
        global $DB;

        if (empty($pathnamehashes)) {
            return false;
        }

        list ($course, $cm) = get_course_and_cm_from_cmid($event->contextinstanceid, 'assign');
        $context = \context_module::instance($cm->id);
        $assign = new \assign($context, $cm, $course);

        $fs = get_file_storage();

        $filesdeleted = false;

        $sql = 'SELECT * FROM {assign_grades} WHERE userid = ? AND assignment = ? ORDER BY id DESC LIMIT 1';
        $usergrades = $DB->get_record_sql($sql, array($memberid, $cm->instance));

        foreach ($pathnamehashes as $i => $file) {
            $filedata = $DB->get_record('files', array('pathnamehash' => $file));
            $filedata->itemid = $usergrades->id;
            $filedata->userid = $memberid;

            // Overrwrite or remove previous users' files.
            if (!$filesdeleted) {
                $fs->delete_area_files($filedata->contextid, $filedata->component, $filedata->filearea, $usergrades->id);
                $filesdeleted = true;
            }

            // Copy submitted files to all team members.
            $fs->create_file_from_storedfile($filedata, $filedata->id);
        }

        // Copy the assignsubmission_file record to all team members.
        $filesubmission = $DB->get_record('assignfeedback_file', array('grade' => $usergrades->id));
        if (!$filesubmission) {
            $filesubmission = new \stdClass();
            $filesubmission->assignment = $assign->get_instance()->id;
            $filesubmission->grade = $usergrades->id;
            $filesubmission->numfiles = 1;

            $DB->insert_record('assignfeedback_file', $filesubmission);
        }

        return true;

    }

    public static function copy_files_to_member_assignsubmission($event, $pathnamehashes, $memberid): bool {
        global $DB;

        if (empty($pathnamehashes)) {
            return false;
        }

        list ($course, $cm) = get_course_and_cm_from_cmid($event->contextinstanceid, 'assign');
        $context = \context_module::instance($cm->id);
        $assign = new \assign($context, $cm, $course);

        $fs = get_file_storage();

        $msubm = $assign->get_user_submission($memberid, 0);
        $filesdeleted = false;

        foreach ($pathnamehashes as $i => $file) {
            $filedata = $DB->get_record('files', array('pathnamehash' => $file));
            $filedata->itemid = $msubm->id;
            $filedata->userid = $memberid;

            // Overrwrite or remove previous users' files.
            if (!$filesdeleted) {
                $fs->delete_area_files($filedata->contextid, $filedata->component, $filedata->filearea, $filedata->itemid);
                $filesdeleted = true;
            }

            // Copy submitted files to all team members.
            $fs->create_file_from_storedfile($filedata, $filedata->id);
        }

        // Copy the assignsubmission_file record to all team members.
        $filesubmission = $DB->get_record('assignsubmission_file', array('submission' => $msubm->id));
        if (!$filesubmission) {
            $filesubmission = new \stdClass();
            $filesubmission->submission = $msubm->id;
            $filesubmission->assignment = $assign->get_instance()->id;
            $filesubmission->numfiles = 1;

            $DB->insert_record('assignsubmission_file', $filesubmission);
        }

        return true;

    }

    public static function add_completion_to_member($event, $memberid): bool {
        global $DB;

        // Course_modules_completion.
        $obj = array(
                'coursemoduleid' => $event->contextinstanceid,
                'userid' => $event->relateduserid,
                'completionstate' => 1
        );
        $completion = $DB->get_record('course_modules_completion', $obj);

        list ($course, $cm) = get_course_and_cm_from_cmid($event->contextinstanceid, 'assign');

        // Course_modules_completion add flag.
        if (!empty($completion)) {

            // Check if present course_modules_completion and delete.
            $obj = array(
                    'coursemoduleid' => $event->contextinstanceid,
                    'userid' => $memberid
            );

            $row = $DB->get_records('course_modules_completion', $obj);
            if ($row) {
                $DB->delete_records('course_modules_completion', $obj);
            }

            // Insert new completion by user.
            unset($completion->id);
            $completion->userid = $memberid;
            $completion->timemodified = time();
            $insertid = $DB->insert_record('course_modules_completion', $completion);
            $data = $DB->get_record('course_modules_completion', array('id' => $insertid));

            // Update cache.
            $completioncache = \cache::make('core', 'completion');

            $course = $DB->get_record('course', array('id' => $cm->course));

            // Update module completion in user's cache.
            if (!($cachedata = $completioncache->get($data->userid . '_' . $cm->course))
                    || $cachedata['cacherev'] != $course->cacherev) {
                $cachedata = array('cacherev' => $course->cacherev);
            }

            $cachedata[$cm->id] = $data;
            $completioncache->set($data->userid . '_' . $cm->course, $cachedata);

            get_fast_modinfo($cm->course, 0, true);
        }

        return true;

    }

    public static function delete_teacher_feedback($event, $memberid): bool {
        global $DB;

        list ($course, $cm) = get_course_and_cm_from_cmid($event->contextinstanceid, 'assign');
        $context = \context_module::instance($cm->id);
        $assign = new \assign($context, $cm, $course);

        // Get or create new submissions for all team members.
        $msubm = $assign->get_user_submission($memberid, false);
        if (!$msubm) {
            $msubm = $assign->get_user_submission($memberid, true);
        }

        // Remove edit files.
        $sql = 'SELECT * FROM {assign_grades} WHERE userid = ? AND assignment = ? ORDER BY id DESC LIMIT 1';
        $grade = $DB->get_record_sql($sql, array($memberid, $msubm->assignment));

        if (!empty($grade)) {
            // Table assignfeedback_editpdf_annot, assignfeedback_editpdf_cmnt.
            $DB->delete_records('assignfeedback_editpdf_annot', array('gradeid' => $grade->id));
            $DB->delete_records('assignfeedback_editpdf_cmnt', array('gradeid' => $grade->id));

            // Table files.
            $DB->delete_records('files', array(
                    'contextid' => $context->id,
                    'itemid' => $grade->id,
                    'component' => 'assignfeedback_editpdf'
            ));
        }

        return true;

    }

    public static function copy_history_edit_file($event, $pathnamehashes): bool {
        global $DB;

        if (empty($pathnamehashes)) {
            return false;
        }

        $var = print_r($event, true);

        $search = 'assignfeedback_editpdf_haschanges';

        if (strpos($var, $search) === false) {
            return false;
        }

        list ($course, $cm) = get_course_and_cm_from_cmid($event->contextinstanceid, 'assign');
        $context = \context_module::instance($cm->id);
        $assign = new \assign($context, $cm, $course);

        $fs = get_file_storage();

        $sql = 'SELECT * FROM {assign_grades} WHERE userid = ? AND assignment = ? ORDER BY id DESC LIMIT 1';
        $usergrades = $DB->get_record_sql($sql, array($event->relateduserid, $cm->instance));

        foreach ($pathnamehashes as $i => $file) {
            $filedata = $DB->get_record('files', array('pathnamehash' => $file));

            $filedata->itemid = $usergrades->id;
            $filedata->userid = $event->relateduserid;
            $filedata->component = 'assignfeedback_file';
            $filedata->filearea = 'feedback_files';

            // Change filename.
            $arr = explode('.', $filedata->filename);
            if (isset($arr[0])) {
                $arr[0] = $arr[0] . time();
            }
            $filedata->filename = implode('.', $arr);

            // Copy submitted files to all team members.
            $fs->create_file_from_storedfile($filedata, $filedata->id);
        }

        // Copy the assignsubmission_file record to all team members.
        $filesubmission = $DB->get_record('assignfeedback_file', array('grade' => $usergrades->id));
        if (!$filesubmission) {
            $filesubmission = new \stdClass();
            $filesubmission->assignment = $assign->get_instance()->id;
            $filesubmission->grade = $usergrades->id;
            $filesubmission->numfiles = 1;

            $DB->insert_record('assignfeedback_file', $filesubmission);
        }

        return true;

    }

    public static function update_onlinetext($event, $members): bool {
        global $DB;

        $cm = $DB->get_record('course_modules', array('id' => $event->contextinstanceid));
        $submission = $DB->get_record('assign_submission', array('assignment' => $cm->instance, 'userid' => $event->relateduserid));

        $onlinetext = $DB->get_record('assignsubmission_onlinetext', array(
                        'assignment' => $cm->instance,
                        'submission' => $submission->id
                )
        );

        if(!empty($onlinetext)) {
            foreach ($members as $member) {
                $membersubmission = $DB->get_record('assign_submission', array(
                                'assignment' => $cm->instance,
                                'userid' => $member->userid
                        )
                );

                $memberonlinetext = $DB->get_record('assignsubmission_onlinetext', array(
                                'assignment' => $cm->instance,
                                'submission' => $membersubmission->id
                        )
                );

                if(empty($memberonlinetext)){
                    $DB->insert_record('assignsubmission_onlinetext', array(
                            'assignment' => $cm->instance,
                            'submission' => $membersubmission->id,
                            'onlinetext' => $onlinetext->onlinetext
                    ));
                }else{
                    $memberonlinetext->onlinetext = $onlinetext->onlinetext;
                    $DB->update_record('assignsubmission_onlinetext', $memberonlinetext);
                }
            }
        }

        return true;
    }
}
