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

class observer_quiz {

    public static function question_manually_graded(\mod_quiz\event\question_manually_graded $event) {
        global $DB;

        if (!\local_teamwork\common::is_quiz_enable($event->contextinstanceid)) {
            return false;
        }

        $relateduserid = $DB->get_field(
                'quiz_attempts',
                'userid',
                ['id' => $event->other['attemptid'], 'quiz' => $event->other['quizid']],
                IGNORE_MISSING);

        // Check if local on.
        $members = get_mod_events_members($event->contextinstanceid, $relateduserid, 'quiz');
        if ($members == false || empty($members)) {
            return false;
        }

        foreach ($members as $member) {
            self::duplicate_qa($event->other['quizid'], $event->other['attemptid'], $relateduserid, $member->userid);
        }

        return true;
    }

    public static function attempt_submitted(\mod_quiz\event\attempt_submitted $event) {

        if (!\local_teamwork\common::is_quiz_enable($event->contextinstanceid)) {
            return false;
        }

        // Check if local on.
        $members = get_mod_events_members($event->contextinstanceid, $event->relateduserid, 'quiz');
        if ($members == false || empty($members)) {
            return false;
        }

        $cmid = $event->contextinstanceid;
        $quizattempts = $event->objectid;

        foreach ($members as $member) {
            self::duplicate_qa($event->other['quizid'], $quizattempts, $event->userid, $member->userid);
        }

        return true;
    }

    // Functions.

    /**
     * Duplicates a quiz attempt for a target user, copying all the questions
     *
     * @param int $quizid The ID of the quiz
     * @param int $sourceattemptid The ID of the attempt to duplicate
     * @param int $sourceuserid The ID of the user who owns the attempt
     * @param int $targetuserid The ID of the user who will own the new attempt
     * @throws dml_exception If an error occurs during database operations
     */
    private static function duplicate_qa($quizid, $sourceattemptid, $sourceuserid, $targetuserid) {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $srcqa = $DB->get_record('quiz_attempts', array('id' => $sourceattemptid));
        $srcqu = $DB->get_record('question_usages', array('id' => $srcqa->uniqueid));

        unset($srcqu->id);
        $trgqu = $DB->insert_record('question_usages', $srcqu);

        $trgqa = clone $srcqa;

        $trgqa->userid = $targetuserid;
        $trgqa->uniqueid = $trgqu;
        $trgqa->quiz = $quizid;

        if ($exist = $DB->get_record('quiz_attempts', array(
                'userid' => $trgqa->userid,
                'quiz' => $trgqa->quiz,
                'attempt' => $trgqa->attempt,
        ))) {
            $trgqa->id = $exist->id;
            $DB->update_record('quiz_attempts', $trgqa);
        } else {
            unset($trgqa->id);
            $DB->insert_record('quiz_attempts', $trgqa);
        }

        $sourcequestionusageid = $srcqa->uniqueid;
        $targetquestionusageid = $trgqa->uniqueid;

        self::tw_clone_questions($sourcequestionusageid, $targetquestionusageid, $sourceuserid, $targetuserid);

        self::tw_clone_grades($quizid, $sourceuserid, $targetuserid);
    }

    private static function tw_clone_grades($quizid, $sourceuserid, $targetuserid) {
        global $DB;

        $quiz = $DB->get_record('quiz', ['id' => $quizid]);
        $qi = $DB->get_record('grade_items', [
                'courseid' => $quiz->course,
                'itemtype' => 'mod',
                'itemmodule' => 'quiz',
                'iteminstance' => $quizid,
        ]);

        // Table grade_grades.

        // Remove all data for target user.
        $DB->delete_records('grade_grades', ['itemid' => $qi->id, 'userid' => $targetuserid]);

        $qi = $DB->get_record('grade_grades', ['itemid' => $qi->id, 'userid' => $sourceuserid]);

        // Insert data for target user.
        if ($qi) {
            unset($qi->id);
            $qi->userid = $targetuserid;
            $DB->insert_record('grade_grades', $qi);
        }

        // Table quiz_grades.

        // Remove all data for target user.
        $DB->delete_records('quiz_grades', ['quiz' => $quizid, 'userid' => $targetuserid]);

        $qq = $DB->get_record('quiz_grades', ['quiz' => $quizid, 'userid' => $sourceuserid]);

        // Insert data for target user.
        if ($qq) {
            unset($qq->id);
            $qq->userid = $targetuserid;
            $DB->insert_record('quiz_grades', $qq);
        }
    }

    private static function tw_clone_questions($sourcequestionusageid, $targetquestionusageid, $sourceuserid, $targetuserid) {
        list($sourcequestionattempts, $targetquestionattempts) =
                self::tw_clone_question_attempts($sourcequestionusageid, $targetquestionusageid);

        $targetquestionattemptstepsids =
                self::tw_clone_question_attempt_steps($sourcequestionattempts, $targetquestionattempts, $sourceuserid,
                        $targetuserid);
    }

    private static function tw_clone_question_attempts($sourcequestionusageid, $targetquestionusageid) {
        global $DB;

        $params = [];
        $params['sourcequestionusageid'] = $sourcequestionusageid;

        $sql = "SELECT * FROM {question_attempts} WHERE questionusageid = :sourcequestionusageid";
        $sourcequestionattempts = $DB->get_records_sql($sql, $params);

        foreach ($sourcequestionattempts as $key => $sourcequestionattempt) {
            $targetquestionattempt = clone $sourcequestionattempt;
            $targetquestionattempt->questionusageid = $targetquestionusageid;

            if ($exist = $DB->get_record('question_attempts', array(
                    'questionusageid' => $targetquestionattempt->questionusageid,
                    'slot' => $targetquestionattempt->slot,
                    'questionid' => $targetquestionattempt->questionid,
            ))) {
                $targetquestionattempt->id = $exist->id;

                $DB->update_record('question_attempts', $targetquestionattempt);
            } else {
                $DB->insert_record('question_attempts', $targetquestionattempt);
            }
        }

        $params = [];
        $params['targetquestionusageid'] = $targetquestionusageid;

        $sql = "SELECT * FROM {question_attempts} WHERE questionusageid = :targetquestionusageid";
        $targetquestionattempts = $DB->get_records_sql($sql, $params);

        return [$sourcequestionattempts, $targetquestionattempts];
    }

    // Clone question_steps.
    private static function tw_clone_question_attempt_steps($sourcequestionattempts, $targetquestionattempts, $sourceuserid,
            $targetuserid) {
        global $DB;

        $targetquestionattemptstepsids = [];

        foreach ($sourcequestionattempts as $key => $sourcequestionattempt) {

            $targetquestionattemptid = self::find_key_by_slot($targetquestionattempts, $sourcequestionattempt->slot);

            $params = [];
            $params['questionattemptid'] = $sourcequestionattempt->id;
            $params['sourceuserid'] = $sourceuserid;

            $sql = "SELECT *
                FROM {question_attempt_steps}
                WHERE questionattemptid = :questionattemptid";

            $sourcequestionattemptssteps = $DB->get_records_sql($sql, $params);

            foreach ($sourcequestionattemptssteps as $sourcequestionattemptsstep) {

                $targetquestionattemptsstep = clone $sourcequestionattemptsstep;

                $sourceqasid = $targetquestionattemptsstep->id;

                $targetquestionattemptsstep->userid = $targetuserid;
                $targetquestionattemptsstep->questionattemptid = (string) $targetquestionattemptid;

                if ($DB->get_record('question_attempt_steps', array(
                        'questionattemptid' => $targetquestionattemptid,
                        'sequencenumber' => $targetquestionattemptsstep->sequencenumber,
                ))) {
                    $DB->delete_records('question_attempt_steps', [
                            'questionattemptid' => $targetquestionattemptid,
                            'sequencenumber' => $targetquestionattemptsstep->sequencenumber,
                    ]);
                }

                $targetqasid = $DB->insert_record('question_attempt_steps', $targetquestionattemptsstep);

                // EC-540 Duplicate file for this stepid if any.
                $sql = 'SELECT *
                        FROM {files}
                        WHERE `itemid` = ?
                            AND `userid` = ?
                            AND `filename` <> "."
                        ORDER BY id DESC';
                $params = [];
                $params[] = $sourceqasid;
                $params[] = $sourceuserid;
                if($file = $DB->get_record_sql($sql, $params, )) {
                    $newitem = new \stdClass();
                    $newitem->userid = $targetuserid;
                    $newitem->itemid = $targetqasid;
                    self::duplicate_file_by_id($file->id, $newitem);
                }

                self::tw_clone_question_attempt_step_data($sourceqasid, $targetqasid);
            }
        }

        return $targetquestionattemptstepsids;
    }

    // Clone question_attempt_step_data.
    private static function tw_clone_question_attempt_step_data($sourceqasid, $targetqasid) {
        global $DB;

        // Get src step_data.
        $srcstepdata = $DB->get_records('question_attempt_step_data', array('attemptstepid' => $sourceqasid));

        // Change attemptstepid to target.
        foreach ($srcstepdata as $data) {
            $data->attemptstepid = $targetqasid;

            // Reset id.
            unset($data->id);

            $DB->insert_record('question_attempt_step_data', $data);
        }
    }

    private static function find_key_by_slot($array, $slot) {

        foreach ($array as $key => $obj) {
            if ($obj->slot == $slot) {
                return $key;
            }
        }

        return null;
    }

    // EC-540.
    /**
     * Function to duplicate a file by ID.
     *
     * @param int $fileid The ID of the file to duplicate.
     * @param object $newitem An object containing properties to update in the resulting file record.
     *
     * @return mixed The duplicated file if successful, otherwise false.
     */
    static function duplicate_file_by_id($fileid, $newitem) {
        global $DB;

        $fs = get_file_storage();
        $filerecord = $DB->get_record('files', array('id' => $fileid));
        $storedfile = $fs->get_file_instance($filerecord);
        $newfilerecord = clone $filerecord;
        foreach ($newitem as $property => $value) {
            if (property_exists($newfilerecord, $property)) {
                $newfilerecord->$property = $value;
            }
        }
        $duplicatedfile = $fs->create_file_from_storedfile($newfilerecord, $storedfile);
        if ($duplicatedfile) {
            return $duplicatedfile;
        } else {
            return false;
        }
    }

}
