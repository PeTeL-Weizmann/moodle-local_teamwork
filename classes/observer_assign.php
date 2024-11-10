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
 * @copyright  2018 Devlion <info@devlion.co>
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
require_once($CFG->dirroot . '/group/lib.php');

class observer_assign {

    /**
     * @param \mod_assign\event\submission_graded $event
     * @return bool
     * @throws \dml_exception
     * @throws \file_exception
     * @throws \file_reference_exception
     * @throws \stored_file_creation_exception
     */
    public static function update_team_members_grades(\mod_assign\event\submission_graded $event): bool {
        global $DB, $USER;

        $mainusergradesid = $event->objectid;
        $finalgrade = $event->get_assign()->get_grade_item()->get_final($event->relateduserid);

        // Check if local on.
        $members = get_mod_events_members($event->contextinstanceid, $event->relateduserid, 'assign');
        if ($members == false || empty($members)) {
            return false;
        }

        // Clone user assigment rubrics and guide.
        self::clone_user_assignment_fillings($event->relateduserid, $event->contextinstanceid, $members);

        list ($course, $cm) = get_course_and_cm_from_cmid($event->contextinstanceid, 'assign');
        if (!\local_teamwork\common::is_assign_submission_enable($cm->instance)) {
            return false;
        }

        $mainusercomments =
                $DB->get_record('assignfeedback_comments', array('grade' => $mainusergradesid, 'assignment' => $cm->instance));

        // Get source user submission from assign_submission.
        $obj = array(
                'userid' => $event->relateduserid,
                'assignment' => $event->get_assign()->get_grade_item()->iteminstance,
                'latest' => 1
        );
        $sourceusersubmission = $DB->get_record('assign_submission', $obj);

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
                " AND filename != '.' ", array($mainusergradesid, $event->get_context()->id));

        $pathnamehashesedit = array();
        foreach ($mainusereditfiles as $itemfile) {
            $pathnamehashesedit[] = $itemfile->pathnamehash;
        }

        // Copy previous edit file to feedback area for main user.
        self::copy_history_edit_file($event, $pathnamehashesedit);

        // Update onlinetext.
        self::update_onlinetext($event, $members);

        // Assign class.
        $context = \context_module::instance($cm->id);
        $assign = new \assign_custom($context, $cm, $course);

        // Reopen attempt.
        if (isset($event->other['reopenattempt']) && $event->other['reopenattempt']) {
            foreach ($members as $member) {
                if ($submission = $assign->get_user_submission($member->userid, false)) {
                    $assign->reopen_submission_if_required($member->userid, $submission, $event->other['reopenattempt']);
                }
            }

            return true;
        }

        // Get final from grade_grades.
        $obj = array(
                'itemid' => $event->get_assign()->get_grade_item()->id,
                'userid' => $event->relateduserid
        );
        $gradegrade = $DB->get_record('grade_grades', $obj);

        // Get final from assign_grades
        $assigngrades = $DB->get_record('assign_grades', ['assignment' => $cm->instance, 'userid' => $event->relateduserid]);

        foreach ($members as $member) {
            $memberid = $member->userid;

            self::delete_teacher_feedback($event, $memberid);

            // Copy main submission file to member.
            self::copy_files_to_member_assignsubmission($event, $pathnamehashessubmission, $memberid);

            $msubm = $assign->get_user_submission($memberid, 0);

            $usercontext = \context_user::instance($event->relateduserid);
            try {
                $obj = new \StdClass();
                $obj->id = $event->contextinstanceid;
                $obj->grade = $finalgrade->finalgrade;
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
            }catch(\Exception $e) {
            }

            // Update final grade_grades.
            $obj = array(
                    'itemid' => $event->get_assign()->get_grade_item()->id,
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

            // Update final assign_grades.
            $currentassigngrades = $DB->get_record('assign_grades', ['assignment' => $cm->instance, 'userid' => $memberid]);
            if (!empty($currentassigngrades)) {
                $assigngrades->id = $currentassigngrades->id;
                $assigngrades->userid = $memberid;
                $DB->update_record('assign_grades', $assigngrades);
            } else {
                unset($assigngrades->id);
                $assigngrades->userid = $memberid;
                $DB->insert_record('assign_grades', $assigngrades);
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

                foreach ($memberschecklist as $checklistmember) {
                    $ci = $DB->get_record('checklist_item', array('moduleid' => $event->contextinstanceid));

                    if (!empty($ci)) {
                        $row = $DB->get_record('checklist_check', array('item' => $ci->id, 'userid' => $checklistmember->userid));

                        if (!empty($row)) {
                            $row->usertimestamp = time();
                            $row->teachermark = 1;
                            $row->teachertimestamp = time();
                            $row->teacherid = $USER->id;
                            $DB->update_record('checklist_check', $row);
                        } else {
                            $ins = new \StdClass();
                            $ins->item = $ci->id;
                            $ins->userid = $checklistmember->userid;
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
                    " AND filename != '.' ", array($mainusergradesid, $event->get_context()->id));

            $pathnamehashesfeedback = array();
            foreach ($mainusersubmissionfiles as $itemfile) {
                $pathnamehashesfeedback[] = $itemfile->pathnamehash;
            }

            // Copy main feedback file to member.
            self::copy_files_to_member_assignfeedback($event, $pathnamehashesfeedback, $memberid);
        }

        // Copy main submission file to member.
        self::add_completion_to_member($event, $memberid);
        return true;
    }

    /**
     * @param \mod_assign\event\grading_form_viewed $event
     * @return bool
     * @throws \dml_exception
     * @throws \file_exception
     * @throws \file_reference_exception
     * @throws \stored_file_creation_exception
     */
    public static function grading_form_viewed(\mod_assign\event\grading_form_viewed $event): bool {

        // Check if local on.
        $members = get_mod_events_members($event->contextinstanceid, $event->relateduserid, 'assign');
        if ($members == false || empty($members)) {
            return false;
        }

        $finalgrade = $event->get_assign()->get_grade_item()->get_final($event->relateduserid);

        return true;
    }

    /**
     * @param \mod_assign\event\submission_status_updated $event
     * @return bool
     * @throws \dml_exception
     * @throws \file_exception
     * @throws \file_reference_exception
     * @throws \stored_file_creation_exception
     */
    public static function submission_status_updated(\mod_assign\event\submission_status_updated $event): bool {

        // Check if local on.
        $members = get_mod_events_members($event->contextinstanceid, $event->relateduserid, 'assign');
        if ($members == false || empty($members)) {
            return false;
        }

        // Remove main submission file to member.
        foreach ($members as $member) {
            self::copy_files_to_member_assignsubmission($event, [], $member->userid);
        }

        // Update onlinetext.
        self::update_onlinetext($event, $members);

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
        if ($members == false || empty($members)) {
            return false;
        }

        $cm = $DB->get_record('course_modules', array('id' => $event->contextinstanceid));
        if (!\local_teamwork\common::is_assign_submission_enable($cm->instance)) {
            return false;
        }

        // Get source user submission from assign_submission.
        $obj = array(
                'userid' => $event->relateduserid,
                'assignment' => $event->get_assign()->get_grade_item()->iteminstance,
                'latest' => 1
        );
        $sourceusersubmission = $DB->get_record('assign_submission', $obj);

        // Main submission file.
        $mainusersubmissionfiles = $DB->get_records_sql("SELECT * FROM {files} WHERE component = 'assignsubmission_file' " .
                " AND filearea = 'submission_files' AND itemid = ? AND contextid = ? " .
                " AND filename != '.' ", array($sourceusersubmission->id, $event->get_context()->id));

        $pathnamehashessubmission = array();
        foreach ($mainusersubmissionfiles as $itemfile) {
            $pathnamehashessubmission[] = $itemfile->pathnamehash;
        }

        foreach ($members as $member) {
            $DB->set_field('assign_submission', 'status', 'submitted',
                    array('userid' => $member->userid, 'assignment' => $cm->instance));

            self::copy_files_to_member_assignsubmission($event, $pathnamehashessubmission, $member->userid);
        }

        // Update onlinetext.
        self::update_onlinetext($event, $members);

        return true;
    }

    public static function update_team_memebers_submision_status_updated(\mod_assign\event\submission_updated $event): bool {
        global $DB;

        // Check if local on.
        $members = get_mod_events_members($event->contextinstanceid, $event->relateduserid, 'assign');
        if ($members == false || empty($members)) {
            return false;
        }

        $cm = $DB->get_record('course_modules', array('id' => $event->contextinstanceid));
        if (!\local_teamwork\common::is_assign_submission_enable($cm->instance)) {
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
        global $DB;

        // Check if local on.
        $members = get_mod_events_members($event->contextinstanceid, $event->userid, 'assign');

        if ($members == false || empty($members)) {
            return false;
        }

        // SG - get Assign object for this cm.
        list ($course, $cm) = get_course_and_cm_from_cmid($event->contextinstanceid, 'assign');
        if (!\local_teamwork\common::is_assign_submission_enable($cm->instance)) {
            return false;
        }
        $context = \context_module::instance($cm->id);
        $assign = new \assign($context, $cm, $course);

        // PTL #7468 add course completion for submited user.
        $obj = array(
                'coursemoduleid' => $event->contextinstanceid,
                'userid' => $event->relateduserid ? $event->relateduserid : $event->userid,
                'completionstate' => 1
        );
        $completion = $DB->get_record('course_modules_completion', $obj);

        if (empty($completion)) {
            $obj['timemodified'] = time();
            $DB->insert_record('course_modules_completion', $obj);
        }

        foreach ($members as $member) {
            // Get team leader's submission and update דורש מתן ציון.
            $obj = $assign->get_user_submission($member->userid, 1);
            $obj->timemodified = time();
            $DB->update_record('assign_submission', $obj);

            self::copy_files_to_member_assignsubmission($event, $event->other['pathnamehashes'], $member->userid);
            self::delete_teacher_feedback($event, $member->userid);
            self::add_completion_to_member($event, $member->userid);
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

        $submission = $DB->get_record('assign_submission', ['id' => $event->other['itemid']]);
        $currentuserid = $submission->userid;

        // Check if local on.
        $members = get_mod_events_members($event->contextinstanceid, $currentuserid, 'assign');
        if ($members == false || empty($members)) {
            return false;
        }

        list ($course, $cm) = get_course_and_cm_from_cmid($event->contextinstanceid, 'assign');
        if (!\local_teamwork\common::is_assign_submission_enable($cm->instance)) {
            return false;
        }
        $context = \context_module::instance($cm->id);
        $assign = new \assign($context, $cm, $course);

        $cuurentcomment = $DB->get_record('comments', ['id' => $event->objectid]);
        unset($cuurentcomment->id);
        unset($cuurentcomment->itemid);

        foreach ($members as $i => $member) {
            if ($currentuserid == $member->userid) {
                continue;
            }
            $obj = $assign->get_user_submission($member->userid, true);
            $cuurentcomment->itemid = $obj->id;
            $DB->insert_record('comments', $cuurentcomment);
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

        $submission = $DB->get_record('assign_submission', ['id' => $event->other['itemid']]);
        $currentuserid = $submission->userid;

        // Check if local on.
        $members = get_mod_events_members($event->contextinstanceid, $currentuserid, 'assign');
        if ($members == false || empty($members)) {
            return false;
        }

        list ($course, $cm) = get_course_and_cm_from_cmid($event->contextinstanceid, 'assign');
        if (!\local_teamwork\common::is_assign_submission_enable($cm->instance)) {
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
            if ($currentuserid == $member->userid) {
                continue;
            }
            $obj = $assign->get_user_submission($member->userid, true);
            $todelete = array(
                    'contextid' => $event->contextid,
                    'component' => 'assignsubmission_comments',
                    'commentarea' => 'submission_comments',
                    'itemid' => $obj->id
            );
            $DB->delete_records('comments', $todelete);
            foreach ($cuurentcomments as $comment) {
                unset($comment->id);
                unset($comment->itemid);
                $comment->itemid = $obj->id;
                $DB->insert_record('comments', $comment);
            }
        }
        return true;
    }

    // Functions.

    private static function copy_files_to_member_assignfeedback($event, $pathnamehashes, $memberid): void {
        global $DB;

        list ($course, $cm) = get_course_and_cm_from_cmid($event->contextinstanceid, 'assign');
        $context = \context_module::instance($cm->id);
        $assign = new \assign($context, $cm, $course);

        $fs = get_file_storage();

        $sql = 'SELECT * FROM {assign_grades} WHERE userid = ? AND assignment = ? ORDER BY id DESC LIMIT 1';
        $usergrades = $DB->get_record_sql($sql, array($memberid, $cm->instance));

        // Overrwrite or remove previous users' files.
        $fs->delete_area_files($context->id, 'assignfeedback_file', 'feedback_files', $usergrades->id);

        foreach ($pathnamehashes as $i => $file) {
            $filedata = $DB->get_record('files', array('pathnamehash' => $file));
            $filedata->itemid = $usergrades->id;
            $filedata->userid = $memberid;

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
    }

    private static function copy_files_to_member_assignsubmission($event, $pathnamehashes, $memberid): bool {
        global $DB;

        list ($course, $cm) = get_course_and_cm_from_cmid($event->contextinstanceid, 'assign');
        $context = \context_module::instance($cm->id);
        $assign = new \assign($context, $cm, $course);

        $msubm = $assign->get_user_submission($memberid, 0);

        $fs = get_file_storage();

        if (empty($pathnamehashes)) {
            $fs->delete_area_files($event->get_context()->id, 'assignsubmission_file', 'submission_files', $msubm->id);
            return true;
        }

        $filesdeleted = false;
        foreach ($pathnamehashes as $file) {
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

    private static function add_completion_to_member($event, $memberid): void {
        global $DB;

        // Course_modules_completion.
        $obj = array(
                'coursemoduleid' => $event->contextinstanceid,
                'userid' => $event->relateduserid ? $event->relateduserid : $event->userid,
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
    }

    private static function delete_teacher_feedback($event, $memberid): void {
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
    }

    private static function copy_history_edit_file($event, $pathnamehashes): bool {
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

    private static function update_onlinetext($event, $members): void {
        global $DB;

        list ($course, $cm) = get_course_and_cm_from_cmid($event->contextinstanceid, 'assign');
        $context = \context_module::instance($cm->id);
        $assign = new \assign($context, $cm, $course);

        $msubm = $assign->get_user_submission($event->relateduserid, 0);

        $onlinetext = $DB->get_record('assignsubmission_onlinetext', array(
                        'assignment' => $cm->instance,
                        'submission' => $msubm->id
                )
        );

        if (!empty($onlinetext)) {
            foreach ($members as $member) {
                $msubm = $assign->get_user_submission($member->userid, 0);

                $memberonlinetext = $DB->get_record('assignsubmission_onlinetext', array(
                                'assignment' => $cm->instance,
                                'submission' => $msubm->id
                        )
                );

                if (empty($memberonlinetext)) {
                    $DB->insert_record('assignsubmission_onlinetext', array(
                            'assignment' => $cm->instance,
                            'submission' => $msubm->id,
                            'onlinetext' => $onlinetext->onlinetext
                    ));
                } else {
                    $memberonlinetext->onlinetext = $onlinetext->onlinetext;
                    $DB->update_record('assignsubmission_onlinetext', $memberonlinetext);
                }
            }
        } else {
            foreach ($members as $member) {
                $msubm = $assign->get_user_submission($member->userid, 0);
                $DB->delete_records('assignsubmission_onlinetext', ['assignment' => $cm->instance, 'submission' => $msubm->id]);
            }
        }
    }

    private static function clone_user_assignment_fillings($userid, $instanceid, $targetusers): bool {
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
            $oldassigngrade =
                    $DB->get_record('assign_grades', ['userid' => $targetuser->userid, 'assignment' => $assign->instance]);
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
                self::clone_fillings($actualfillings, $gradinginstance->id, 'gradingform_guide_fillings');
            }

            // Rubric.
            $actualfillings = $DB->get_records('gradingform_rubric_fillings', ['instanceid' => $actualgradinginstance->id]);
            if (!empty($actualfillings)) {
                self::clone_fillings($actualfillings, $gradinginstance->id, 'gradingform_rubric_fillings');
            }
        }

        return true;
    }

    private static function clone_fillings($actualfillings, $gradinginstanceid, $tablename = 'gradingform_rubric_fillings'): void {
        global $DB;

        foreach ($actualfillings as $newfilling) {
            $temp = clone $newfilling;
            $temp->instanceid = $gradinginstanceid;
            unset($temp->id);
            $DB->insert_record($tablename, $temp);
        }
    }

    private static function add_comments_to_assign($comment): bool {
        global $DB;

        $submission = $DB->get_record('assign_submission', ['id' => $comment->itemid]);

        // Get contents of current user.
        $obj = [
                'contextid' => $comment->contextid,
                'component' => 'assignsubmission_comments',
                'commentarea' => 'submission_comments',
                'itemid' => $submission->id,

        ];
        $usercomments = $DB->get_records('comments', $obj);

        $context = $DB->get_record('context', ['id' => $comment->contextid]);
        $members = get_mod_events_members($context->instanceid, $submission->userid, 'assign');
        if ($members && !empty($members)) {
            foreach ($members as $member) {
                $assignrow = $DB->get_record('assign_submission', [
                                'assignment' => $submission->assignment,
                                'userid' => $member->userid
                        ]
                );

                // Delete all comments by user.
                // Check if present comments and delete.
                $obj = [
                        'contextid' => $comment->contextid,
                        'component' => 'assignsubmission_comments',
                        'commentarea' => 'submission_comments',
                        'itemid' => $assignrow->id,
                ];
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

                $insert = [
                        'contextid' => $comment->contextid,
                        'commentarea' => $comment->commentarea,
                        'itemid' => $assignrow->id,
                        'component' => $comment->component,
                        'content' => $comment->content,
                        'format' => $comment->format,
                        'userid' => $comment->userid,
                        'timecreated' => $comment->timecreated,
                ];

                $DB->insert_record('comments', $insert);
            }
        }

        return true;
    }
}
