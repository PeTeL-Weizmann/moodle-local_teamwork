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
    global $PAGE, $DB;

    if (!in_array($PAGE->pagetype, ['mod-assign-view', 'mod-quiz-view'])) {
        return;
    }

    $courseid = $PAGE->context->get_course_context()->instanceid;
    $cmid = $PAGE->context->instanceid;

    $moduletype = \get_module_name($cmid);

    // Check if groups submittions is enabled.
    if ($moduletype == 'quiz') {
        if (!\local_teamwork\common::is_quiz_enable($cmid)) {
            return;
        }
    }

    if ($moduletype == 'assign') {
        if (!\local_teamwork\common::is_assign_submission_enable($PAGE->cm->instance)) {
            return;
        }

        $assign = $DB->get_record('assign', ['id' => $PAGE->cm->instance]);
        if (empty($assign) || $assign->teamsubmission == 1) {
            return;
        }
    }

    // Default value of select.
    $groups = view_groups_select($courseid, $cmid);

    $cm = $DB->get_record('course_modules', ['id' => $cmid]);
    $activegroup = groups_get_activity_group($cm);

    $groupid = optional_param('group', null, PARAM_INT);
    if ($groupid !== null) {
        $activegroup = $groupid;
    }

    if ($activegroup) {
        $selectgroupid = [$activegroup];
    } else {
        if (!empty($groups) && isset($groups[0])) {
            $selectgroupid = [$groups[0]->id];
        } else {
            $selectgroupid = [0];
        }
    }

    if ($moduletype == 'quiz' && $selectgroupid[0] == '-1') {
        $selectgroupid = [0];
    }

    $jsonselectgroupid = json_encode($selectgroupid);

    $PAGE->requires->js_call_amd('local_teamwork/init', 'init', [$courseid, $cmid, $moduletype, $jsonselectgroupid]);

    return;
}
