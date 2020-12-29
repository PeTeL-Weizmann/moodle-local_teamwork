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
 * Local plugin "Team Work" - Library
 *
 * @package     local_teamwork
 * @category    local
 * @copyright   2019 Devlion  <info@devlion.co
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/teamwork/locallib.php');

function local_teamwork_extend_navigation() {
    global $CFG, $PAGE, $OUTPUT, $DB;

    $pagesworking = array('mod-assign-view');
    if (!in_array($PAGE->pagetype, $pagesworking)) {
        return;
    }

    $courseid = $PAGE->context->get_course_context()->instanceid;
    $activityid = $PAGE->context->instanceid;

    if (!\local_teamwork\common::is_submission_enable($PAGE->cm->instance)) {
        return;
    }

    // Check if groups submittions is enabled.
    $assign = $DB->get_record('assign', array('id' => $PAGE->cm->instance));
    if (empty($assign) || $assign->teamsubmission == 1) {
        return;
    }

    $moduletype = \get_module_name($activityid);

    // Default value of select.
    $groups = view_groups_select($courseid);

    if (!empty($groups) && isset($groups[0])) {
        $selectgroupid = array($groups[0]->id);
    } else {
        $selectgroupid = array(0);
    }
    $jsonselectgroupid = json_encode($selectgroupid);

    $PAGE->requires->js_call_amd('local_teamwork/init', 'init', array(
            $courseid, $activityid, $moduletype, $jsonselectgroupid
    ));

    return;
}
