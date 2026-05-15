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
 * Returns the main SCSS content for the theme (the Boost preset only).
 *
 * Pre/post SCSS is handled exclusively by the prescsscallback and extrascsscallback
 * registered in config.php.  Including them here as well would cause double-application
 * of brand variables, component overrides, and login styles.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_mru_odel_get_main_scss_content($theme) {
    global $CFG;

    $filename = !empty($theme->settings->preset) ? $theme->settings->preset : null;
    $fs       = get_file_storage();
    $context  = context_system::instance();

    if ($filename === 'plain.scss') {
        return file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/plain.scss');
    }

    if ($filename && $filename !== 'default.scss') {
        $presetfile = $fs->get_file($context->id, 'theme_mru_odel', 'preset', 0, '/', $filename);
        if ($presetfile) {
            return $presetfile->get_content();
        }
    }

    // Default: Boost default preset.
    return file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/default.scss');
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

    // Admin brand-color overrides take highest priority (applied before pre.scss
    // so they win over the file's defaults when an admin has set them).
    $configurable = [
        'brandcolor'    => ['primary'],
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

    // Load the MRU design-system variables: brand palette + Bootstrap overrides
    // ($primary, $body-bg, $border-radius, $font-family-sans-serif, etc.).
    // These must be available before the Boost preset is compiled.
    $prefile = $CFG->dirroot . '/theme/mru_odel/scss/pre.scss';
    if (file_exists($prefile)) {
        $scss .= file_get_contents($prefile) . "\n";
    }

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
    global $CFG;

    // Load MRU custom stylesheet: component overrides, navbar, hero, cards, etc.
    // This runs after the Boost preset so our rules take precedence.
    $postfile = $CFG->dirroot . '/theme/mru_odel/scss/post.scss';
    $content = file_exists($postfile) ? file_get_contents($postfile) . "\n" : '';

    // Background image.
    $imageurl = $theme->setting_file_url('backgroundimage', 'backgroundimage');
    if (!empty($imageurl)) {
        $safeurl = addcslashes((string) $imageurl, "'\\");
        $content .= '@media (min-width: 768px) {';
        $content .= 'body { ';
        $content .= "background-image: url('" . $safeurl . "'); background-size: cover;";
        $content .= ' } }';
    }

    // Login background image.
    $loginbgurl = $theme->setting_file_url('loginbackgroundimage', 'loginbackgroundimage');
    if (!empty($loginbgurl)) {
        $safeloginurl = addcslashes((string) $loginbgurl, "'\\");
        $content .= '.path-login #page { ';
        $content .= "background-image: url('" . $safeloginurl . "'); background-size: cover; background-position: center;";
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
 * @noinspection PhpUnusedParameterInspection
 */
function theme_mru_odel_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    unset($course, $cm); // Required by callback signature.

    $allowedareas = ['logo', 'backgroundimage', 'loginbackgroundimage'];
    if ($context->contextlevel == CONTEXT_SYSTEM && in_array($filearea, $allowedareas)) {
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
