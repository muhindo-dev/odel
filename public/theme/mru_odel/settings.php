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
 * MRU ODEL Theme settings page.
 *
 * @package    theme_mru_odel
 * @copyright  2026 Mutesar I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    $settings = new theme_boost_admin_settingspage_tabs('themesettingmru_odel', get_string('configtabtitle', 'theme_boost'));

    // ── General Settings Tab ──────────────────────────────────────────────────
    $page = new admin_settingpage('theme_mru_odel_general', get_string('generalsettings', 'theme_mru_odel'));

    // Preset chooser.
    $presets = [];
    $presets['default.scss'] = 'Default';
    $presets['plain.scss']   = 'Plain';

    $fs = get_file_storage();
    $context = context_system::instance();
    $files = $fs->get_area_files($context->id, 'theme_mru_odel', 'preset', 0, 'itemid, filepath, filename', false);
    foreach ($files as $file) {
        if ($file->get_filename() === '.') {
            continue;
        }
        $presets[$file->get_filename()] = $file->get_filename();
    }

    $setting = new admin_setting_configselect(
        'theme_mru_odel/preset',
        get_string('preset', 'theme_mru_odel'),
        get_string('preset_desc', 'theme_mru_odel'),
        'default.scss',
        $presets
    );
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Brand colour.
    $setting = new admin_setting_configcolourpicker(
        'theme_mru_odel/brandcolor',
        get_string('brandcolor', 'theme_mru_odel'),
        get_string('brandcolor_desc', 'theme_mru_odel'),
        '#174DA4'
    );
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Secondary colour.
    $setting = new admin_setting_configcolourpicker(
        'theme_mru_odel/secondarycolor',
        get_string('secondarycolor', 'theme_mru_odel'),
        get_string('secondarycolor_desc', 'theme_mru_odel'),
        '#CB9C2C'
    );
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Logo.
    $setting = new admin_setting_configstoredfile(
        'theme_mru_odel/logo',
        get_string('logo', 'theme_mru_odel'),
        get_string('logo_desc', 'theme_mru_odel'),
        'logo'
    );
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Background image.
    $setting = new admin_setting_configstoredfile(
        'theme_mru_odel/backgroundimage',
        get_string('backgroundimage', 'theme_mru_odel'),
        get_string('backgroundimage_desc', 'theme_mru_odel'),
        'backgroundimage'
    );
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Login background image.
    $setting = new admin_setting_configstoredfile(
        'theme_mru_odel/loginbackgroundimage',
        get_string('loginbackgroundimage', 'theme_mru_odel'),
        get_string('loginbackgroundimage_desc', 'theme_mru_odel'),
        'loginbackgroundimage'
    );
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Login page heading.
    $setting = new admin_setting_configtext(
        'theme_mru_odel/loginheading',
        get_string('loginheading', 'theme_mru_odel'),
        get_string('loginheading_desc', 'theme_mru_odel'),
        'Welcome to MRU ODEL'
    );
    $page->add($setting);

    // Login page sub-heading.
    $setting = new admin_setting_configtext(
        'theme_mru_odel/loginsubheading',
        get_string('loginsubheading', 'theme_mru_odel'),
        get_string('loginsubheading_desc', 'theme_mru_odel'),
        'Mutesar I Royal University — Open & Distance E-Learning'
    );
    $page->add($setting);

    // Footer text.
    $setting = new admin_setting_confightmleditor(
        'theme_mru_odel/footertext',
        get_string('footertext', 'theme_mru_odel'),
        get_string('footertext_desc', 'theme_mru_odel'),
        ''
    );
    $page->add($setting);

    $settings->add($page);

    // ── Advanced Settings Tab ─────────────────────────────────────────────────
    $page = new admin_settingpage('theme_mru_odel_advanced', get_string('advancedsettings', 'theme_mru_odel'));

    // Raw SCSS pre.
    $setting = new admin_setting_scsscode(
        'theme_mru_odel/scsspre',
        get_string('rawscsspre', 'theme_mru_odel'),
        get_string('rawscsspre_desc', 'theme_mru_odel'),
        '',
        PARAM_RAW
    );
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Raw SCSS.
    $setting = new admin_setting_scsscode(
        'theme_mru_odel/scss',
        get_string('rawscss', 'theme_mru_odel'),
        get_string('rawscss_desc', 'theme_mru_odel'),
        '',
        PARAM_RAW
    );
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $settings->add($page);
}
