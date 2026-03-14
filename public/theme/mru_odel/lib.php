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
 * MRU ODEL Theme functions.
 *
 * @package    theme_mru_odel
 * @copyright  2026 Mutesar I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Returns the main SCSS content for the theme.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_mru_odel_get_main_scss_content($theme) {
    global $CFG;

    $scss = '';
    $filename = !empty($theme->settings->preset) ? $theme->settings->preset : null;
    $fs = get_file_storage();

    $context = context_system::instance();
    if ($filename == 'default.scss') {
        $scss .= file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/default.scss');
    } else if ($filename == 'plain.scss') {
        $scss .= file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/plain.scss');
    } else if ($filename && ($presetfile = $fs->get_file($context->id, 'theme_mru_odel', 'preset', 0, '/', $filename))) {
        $scss .= $presetfile->get_content();
    } else {
        // Default preset.
        $scss .= file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/default.scss');
    }

    // Pre CSS - needed before the main bootstrap/moodle CSS.
    $pre = file_get_contents($CFG->dirroot . '/theme/mru_odel/scss/pre.scss');
    // Post CSS - applied after everything else.
    $post = file_get_contents($CFG->dirroot . '/theme/mru_odel/scss/post.scss');

    return $pre . "\n" . $scss . "\n" . $post;
}

/**
 * Get SCSS to prepend to the main SCSS.
 *
 * @param theme_config $theme The theme config object.
 * @return array
 */
function theme_mru_odel_get_pre_scss($theme) {
    global $CFG;

    $scss = '';

    // Color override from settings.
    $configurable = [
        'brandcolor' => ['primary'],
        'secondarycolor' => ['secondary'],
    ];

    foreach ($configurable as $configkey => $targets) {
        $value = isset($theme->settings->{$configkey}) ? $theme->settings->{$configkey} : null;
        if (empty($value)) {
            continue;
        }
        array_map(function($target) use (&$scss, $value) {
            $scss .= '$' . $target . ': ' . $value . ";\n";
        }, (array) $targets);
    }

    // MRU brand colors as defaults.
    $scss .= '$mru-primary: #174DA4;' . "\n";
    $scss .= '$mru-secondary: #CB9C2C;' . "\n";
    $scss .= '$mru-dark: #0d2d62;' . "\n";
    $scss .= '$mru-light: #f4f7fb;' . "\n";
    $scss .= '$mru-white: #ffffff;' . "\n";

    // Additional pre SCSS from settings.
    if (!empty($theme->settings->scsspre)) {
        $scss .= $theme->settings->scsspre;
    }

    return $scss;
}

/**
 * Inject additional SCSS.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_mru_odel_get_extra_scss($theme) {
    $content = '';

    // Background image.
    $imageurl = $theme->setting_file_url('backgroundimage', 'backgroundimage');
    if (!empty($imageurl)) {
        $content .= '@media (min-width: 768px) {';
        $content .= 'body { ';
        $content .= "background-image: url('$imageurl'); background-size: cover;";
        $content .= ' } }';
    }

    // Login background image.
    $loginbgurl = $theme->setting_file_url('loginbackgroundimage', 'loginbackgroundimage');
    if (!empty($loginbgurl)) {
        $content .= '.path-login #page { ';
        $content .= "background-image: url('$loginbgurl'); background-size: cover; background-position: center;";
        $content .= ' }';
    }

    // Extra SCSS from settings.
    if (!empty($theme->settings->scss)) {
        $content .= $theme->settings->scss;
    }

    return $content;
}

/**
 * Get compiled CSS.
 *
 * @return string the compiled CSS
 */
function theme_mru_odel_get_precompiled_css() {
    global $CFG;
    return file_get_contents($CFG->dirroot . '/theme/mru_odel/style/moodle.css');
}

/**
 * Serves any files associated with the theme settings.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function theme_mru_odel_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel == CONTEXT_SYSTEM && ($filearea === 'logo' || $filearea === 'backgroundimage' || $filearea === 'loginbackgroundimage')) {
        $theme = theme_config::load('mru_odel');
        if (!array_key_exists($filearea, $theme->setting_file_areas())) {
            send_file_not_found();
        }
        $filename = array_pop($args);
        if (!$file = get_file_storage()->get_file(
            $context->id, "theme_mru_odel", $filearea, 0, "/", $filename
        )) {
            send_file_not_found();
        }
        \core\session\manager::write_close();
        send_stored_file($file, null, 0, $forcedownload, $options);
    } else {
        send_file_not_found();
    }
}
