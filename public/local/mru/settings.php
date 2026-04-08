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
 * Settings for the MRU Integration plugin.
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $settings = new admin_settingpage('local_mru', get_string('pluginname', 'local_mru'));

    // --- Campus Dynamics API Settings ---
    $settings->add(new admin_setting_heading(
        'local_mru/api_heading',
        get_string('settings:api_heading', 'local_mru'),
        get_string('settings:api_heading_desc', 'local_mru')
    ));

    $settings->add(new admin_setting_configtext(
        'local_mru/api_base_url',
        get_string('settings:api_base_url', 'local_mru'),
        get_string('settings:api_base_url_desc', 'local_mru'),
        'https://eadmin.mru.ac.ug/API/v2',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'local_mru/api_username',
        get_string('settings:api_username', 'local_mru'),
        get_string('settings:api_username_desc', 'local_mru'),
        '',
        PARAM_RAW
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_mru/api_password',
        get_string('settings:api_password', 'local_mru'),
        get_string('settings:api_password_desc', 'local_mru'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_mru/api_timeout',
        get_string('settings:api_timeout', 'local_mru'),
        get_string('settings:api_timeout_desc', 'local_mru'),
        30,
        PARAM_INT
    ));

    // --- MRU Database Settings ---
    $settings->add(new admin_setting_heading(
        'local_mru/db_heading',
        get_string('settings:db_heading', 'local_mru'),
        get_string('settings:db_heading_desc', 'local_mru')
    ));

    $settings->add(new admin_setting_configtext(
        'local_mru/db_host',
        get_string('settings:db_host', 'local_mru'),
        get_string('settings:db_host_desc', 'local_mru'),
        'localhost',
        PARAM_HOST
    ));

    $settings->add(new admin_setting_configtext(
        'local_mru/db_port',
        get_string('settings:db_port', 'local_mru'),
        get_string('settings:db_port_desc', 'local_mru'),
        '3306',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_mru/db_name',
        get_string('settings:db_name', 'local_mru'),
        get_string('settings:db_name_desc', 'local_mru'),
        'mru_main',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_mru/db_user',
        get_string('settings:db_user', 'local_mru'),
        get_string('settings:db_user_desc', 'local_mru'),
        '',
        PARAM_RAW
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_mru/db_pass',
        get_string('settings:db_pass', 'local_mru'),
        get_string('settings:db_pass_desc', 'local_mru'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_mru/db_socket',
        get_string('settings:db_socket', 'local_mru'),
        get_string('settings:db_socket_desc', 'local_mru'),
        '',
        PARAM_RAW
    ));

    // --- Sync Settings ---
    $settings->add(new admin_setting_heading(
        'local_mru/sync_heading',
        get_string('settings:sync_heading', 'local_mru'),
        get_string('settings:sync_heading_desc', 'local_mru')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_mru/sync_enabled',
        get_string('settings:sync_enabled', 'local_mru'),
        get_string('settings:sync_enabled_desc', 'local_mru'),
        0
    ));

    $settings->add(new admin_setting_configselect(
        'local_mru/sync_frequency',
        get_string('settings:sync_frequency', 'local_mru'),
        get_string('settings:sync_frequency_desc', 'local_mru'),
        'daily',
        [
            'hourly'  => get_string('settings:freq_hourly', 'local_mru'),
            'daily'   => get_string('settings:freq_daily', 'local_mru'),
            'weekly'  => get_string('settings:freq_weekly', 'local_mru'),
        ]
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_mru/sync_students',
        get_string('settings:sync_students', 'local_mru'),
        get_string('settings:sync_students_desc', 'local_mru'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_mru/sync_marks',
        get_string('settings:sync_marks', 'local_mru'),
        get_string('settings:sync_marks_desc', 'local_mru'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_mru/sync_courses',
        get_string('settings:sync_courses', 'local_mru'),
        get_string('settings:sync_courses_desc', 'local_mru'),
        1
    ));

    // --- Student Verification Settings ---
    $settings->add(new admin_setting_heading(
        'local_mru/verification_heading',
        get_string('settings:verification_heading', 'local_mru'),
        get_string('settings:verification_heading_desc', 'local_mru')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_mru/verify_on_login',
        get_string('settings:verify_on_login', 'local_mru'),
        get_string('settings:verify_on_login_desc', 'local_mru'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_mru/verify_on_enrol',
        get_string('settings:verify_on_enrol', 'local_mru'),
        get_string('settings:verify_on_enrol_desc', 'local_mru'),
        1
    ));

    $ADMIN->add('localplugins', $settings);
}
