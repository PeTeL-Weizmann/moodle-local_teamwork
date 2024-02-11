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
 * Plugin event observers are registered here.
 *
 * @package    local_teamwork
 * @copyright  2018 Devlion <info@devlion.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// For more information about the Events API, please visit:
// https://docs.moodle.org/dev/Event_2.

$observers = array(

        // Common.
        array(
                'eventname' => '\core\event\course_module_deleted',
                'callback' => '\local_teamwork\observer::course_module_deleted',
                'schedule' => 'instant',
        ),

        // Quiz.
        array(
                'eventname' => '\mod_quiz\event\question_manually_graded',
                'callback' => '\local_teamwork\observer_quiz::question_manually_graded',
                'schedule' => 'instant',
        ),
        array(
                'eventname' => '\mod_quiz\event\attempt_submitted',
                'callback' => '\local_teamwork\observer_quiz::attempt_submitted',
                'schedule' => 'instant',
        ),

        // Assign.
        array(
                'eventname' => '\mod_assign\event\submission_graded',
                'callback' => '\local_teamwork\observer_assign::update_team_members_grades',
                'schedule' => 'instant',
        ),
        array(
                'eventname' => '\assignsubmission_file\event\submission_updated',
                'callback' => '\local_teamwork\observer_assign::update_team_memebers_submision_status_updated',
                'schedule' => 'instant',
        ),
        array(
                'eventname' => '\mod_assign\event\submission_created',
                'callback' => '\local_teamwork\observer_assign::update_team_memebers_submision_status_created',
                'schedule' => 'instant',
        ),
        array(
                'eventname' => '\assignsubmission_file\event\assessable_uploaded',
                'callback' => '\local_teamwork\observer_assign::update_team_memebers_submitted_files_uploaded',
                'schedule' => 'instant',
        ),
        array(
                'eventname' => '\assignsubmission_comments\event\comment_created',
                'callback' => '\local_teamwork\observer_assign::comment_created',
                'schedule' => 'instant',
        ),
        array(
                'eventname' => '\assignsubmission_comments\event\comment_deleted',
                'callback' => '\local_teamwork\observer_assign::comment_deleted',
                'schedule' => 'instant',
        ),
        array(
                'eventname' => '\mod_assign\event\grading_form_viewed',
                'callback' => '\local_teamwork\observer_assign::grading_form_viewed',
                'schedule' => 'instant',
        ),
        array(
                'eventname' => '\mod_assign\event\submission_status_updated',
                'callback' => '\local_teamwork\observer_assign::submission_status_updated',
                'schedule' => 'instant',
        )
);
