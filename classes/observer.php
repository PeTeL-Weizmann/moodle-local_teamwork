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

class observer {

    /**
     * @param \core\event\course_module_deleted $event
     * @return bool
     * @throws \dml_exception
     * @throws \file_exception
     * @throws \file_reference_exception
     * @throws \stored_file_creation_exception
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event): bool {
        global $DB;

        $lt = $DB->get_record('local_teamwork', ['moduleid' => $event->contextinstanceid]);

        if (!empty($lt)) {
            $DB->delete_records('local_teamwork', ['id' => $lt->id]);

            $ltg = $DB->get_records('local_teamwork_groups', ['teamworkid' => $lt->id]);
            $DB->delete_records('local_teamwork_groups', ['teamworkid' => $lt->id]);

            foreach ($ltg as $item) {
                $DB->delete_records('local_teamwork_members', ['teamworkgroupid' => $item->id]);
            }
        }

        return true;
    }
}
