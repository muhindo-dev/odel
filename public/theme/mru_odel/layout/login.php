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
 * MRU ODEL Theme — Login layout.
 *
 * @package    theme_mru_odel
 * @copyright  2026 Mutesar I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$bodyattributes = $OUTPUT->body_attributes();

$theme = theme_config::load('mru_odel');
$loginheading    = !empty($theme->settings->loginheading)    ? $theme->settings->loginheading    : 'Welcome Back';
$loginsubheading = !empty($theme->settings->loginsubheading) ? $theme->settings->loginsubheading : 'Sign in to your learning portal';

$templatecontext = [
    'sitename'        => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'output'          => $OUTPUT,
    'bodyattributes'  => $bodyattributes,
    'loginheading'    => $loginheading,
    'loginsubheading' => $loginsubheading,
    'siteyear'        => date('Y'),
    'wwwroot'         => $CFG->wwwroot,
];

echo $OUTPUT->render_from_template('theme_mru_odel/login', $templatecontext);
