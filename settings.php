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
 * @package   local_teamwork
 * @copyright 2022 <anton@devlion.co> Devlion.co
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This line protects the file from being accessed by a URL directly.
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Create a settings page and add an enable setting for each metadata context type.
    $settings = new admin_settingpage('local_teamwork', get_string('pluginname', 'local_teamwork'));
    $ADMIN->add('localplugins', $settings);

    $langprefix = '_' . current_language();

    // Enabled.
    $name = 'local_teamwork/voice_enabled';
    $title = get_string('voice_enabled', 'local_teamwork');
    $description = get_string('voice_enabled_desc', 'local_teamwork');
    $default = 0;
    $setting = new admin_setting_configcheckbox($name, $title, $description, $default);
    $settings->add($setting);

    // Tokens.
    $name = 'local_teamwork/voice_ok_tokens' . $langprefix;
    $title = get_string('voice_ok_tokens', 'local_teamwork');
    $description = get_string('voice_ok_tokens_desc', 'local_teamwork');
    $default = get_string('voice_ok_tokens_default', 'local_teamwork');
    $setting = new admin_setting_configtextarea($name, $title, $description, $default);
    $settings->add($setting);

    // Schemes.
    $schemes = [
            'add_new_teamcard',
            'add_new_named_teamcard',
            'create_numbers_teamcard',
            'drag_student_card',
            'delete_teamcard',
            'read_users',
            'read_teams',
            'sing_a_song',
    ];

    $schemeprefix = 'scheme_';
    foreach ($schemes as $key => $scheme) {

        $name = 'local_teamwork/' . $schemeprefix . $scheme . $langprefix;
        $title = get_string($schemeprefix . $scheme, 'local_teamwork');
        $description = get_string($schemeprefix . $scheme . '_desc', 'local_teamwork');
        $default = get_string($schemeprefix . $scheme . '_default', 'local_teamwork');
        $setting = new admin_setting_configtextarea($name, $title, $description, $default);
        $settings->add($setting);

    }
}
