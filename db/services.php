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
 * Core external functions and service definitions.
 *
 * The functions and services defined on this file are
 * processed and registered into the Moodle DB after any
 * install or upgrade operation. All plugins support this.
 *
 * For more information, take a look to the documentation available:
 *     - Webservices API: {@link http://docs.moodle.org/dev/Web_services_API}
 *     - External API: {@link http://docs.moodle.org/dev/External_functions_API}
 *     - Upgrade API: {@link http://docs.moodle.org/dev/Upgrade_API}
 *
 * @package    local_teamwork
 * @category   webservice
 * @copyright  2018 Devlion <info@devlion.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$functions = array(
        'local_teamwork_render_block_html_page' => array(
                'classname' => 'local_teamwork_external',
                'methodname' => 'render_block_html_page',
                'classpath' => 'local/teamwork/externallib.php',
                'description' => 'Render main block for title page',
                'type' => 'read',
                'ajax' => true,
                'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
        ),

        'local_teamwork_render_teamwork_html' => array(
                'classname' => 'local_teamwork_external',
                'methodname' => 'render_teamwork_html',
                'classpath' => 'local/teamwork/externallib.php',
                'description' => 'Render main block for popup',
                'type' => 'read',
                'ajax' => true,
                'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
        ),

        'local_teamwork_set_teamwork_enable' => array(
                'classname' => 'local_teamwork_external',
                'methodname' => 'set_teamwork_enable',
                'classpath' => 'local/teamwork/externallib.php',
                'description' => 'Set teamwork enable/disable',
                'type' => 'read',
                'ajax' => true,
                'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
        ),

        'local_teamwork_set_access_to_student' => array(
                'classname' => 'local_teamwork_external',
                'methodname' => 'set_access_to_student',
                'classpath' => 'local/teamwork/externallib.php',
                'description' => 'Set access to student',
                'type' => 'read',
                'ajax' => true,
                'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
        ),

        'local_teamwork_add_new_card' => array(
                'classname' => 'local_teamwork_external',
                'methodname' => 'add_new_card',
                'classpath' => 'local/teamwork/externallib.php',
                'description' => 'Add new card',
                'type' => 'read',
                'ajax' => true,
                'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
        ),

        'local_teamwork_delete_card' => array(
                'classname' => 'local_teamwork_external',
                'methodname' => 'delete_card',
                'classpath' => 'local/teamwork/externallib.php',
                'description' => 'Add new card',
                'type' => 'read',
                'ajax' => true,
                'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
        ),

        'local_teamwork_show_random_popup' => array(
                'classname' => 'local_teamwork_external',
                'methodname' => 'show_random_popup',
                'classpath' => 'local/teamwork/externallib.php',
                'description' => 'Show random popup',
                'type' => 'read',
                'ajax' => true,
                'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
        ),

        'local_teamwork_set_random_teams' => array(
                'classname' => 'local_teamwork_external',
                'methodname' => 'set_random_teams',
                'classpath' => 'local/teamwork/externallib.php',
                'description' => 'Set random team',
                'type' => 'read',
                'ajax' => true,
                'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
        ),

        'local_teamwork_set_new_team_name' => array(
                'classname' => 'local_teamwork_external',
                'methodname' => 'set_new_team_name',
                'classpath' => 'local/teamwork/externallib.php',
                'description' => 'Set name card.',
                'type' => 'read',
                'ajax' => true,
                'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
        ),

        'local_teamwork_render_student_settings_popup' => array(
                'classname' => 'local_teamwork_external',
                'methodname' => 'render_student_settings_popup',
                'classpath' => 'local/teamwork/externallib.php',
                'description' => 'Render student settings popup.',
                'type' => 'read',
                'ajax' => true,
                'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
        ),

        'local_teamwork_student_settings_popup_data' => array(
                'classname' => 'local_teamwork_external',
                'methodname' => 'student_settings_popup_data',
                'classpath' => 'local/teamwork/externallib.php',
                'description' => 'Save student settings popup data.',
                'type' => 'read',
                'ajax' => true,
                'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
        ),

        'local_teamwork_drag_student_card' => array(
                'classname' => 'local_teamwork_external',
                'methodname' => 'drag_student_card',
                'classpath' => 'local/teamwork/externallib.php',
                'description' => 'Drag student to/from card.',
                'type' => 'read',
                'ajax' => true,
                'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
        ),

        'local_teamwork_render_teams_card' => array(
                'classname' => 'local_teamwork_external',
                'methodname' => 'render_teams_card',
                'classpath' => 'local/teamwork/externallib.php',
                'description' => 'Render teams card.',
                'type' => 'read',
                'ajax' => true,
                'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
        ),

        'local_teamwork_render_student_list' => array(
                'classname' => 'local_teamwork_external',
                'methodname' => 'render_student_list',
                'classpath' => 'local/teamwork/externallib.php',
                'description' => 'Render student list.',
                'type' => 'read',
                'ajax' => true,
                'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
        ),

        'local_teamwork_save_rubrics_pdf' => array(
                'classname'     => 'local_teamwork_external',
                'methodname'    => 'save_rubrics_pdf',
                'classpath'     => 'local/teamwork/externallib.php',
                'description'   => 'Save rubrics to pdf.',
                'type'          => 'write',
                'ajax'          => true,
                'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
        ),
);
