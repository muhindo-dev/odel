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
 * Library functions for local_mru.
 *
 * Contains callbacks for navigation, output, and other integrations.
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extend the site navigation with MRU links.
 *
 * @param global_navigation $navigation
 */
function local_mru_extend_navigation(global_navigation $navigation): void {
    global $USER, $PAGE;

    if (!isloggedin() || isguestuser()) {
        return;
    }

    // Add MRU status link for students in their profile area.
    if (has_capability('local/mru:viewownstatus', \context_system::instance())) {
        $node = $navigation->find('myprofile', navigation_node::TYPE_ROOTNODE);
        if ($node) {
            $url = new moodle_url('/local/mru/status.php');
            $node->add(
                get_string('nav:mystatus', 'local_mru'),
                $url,
                navigation_node::TYPE_CUSTOM,
                null,
                'local_mru_status',
                new pix_icon('i/info', '')
            );
        }
    }
}

/**
 * Extend the navigation for a course with MRU sync options.
 *
 * @param navigation_node $navigation Course navigation node.
 * @param stdClass $course Course object.
 * @param context_course $context Course context.
 */
function local_mru_extend_navigation_course(navigation_node $navigation, stdClass $course, \context_course $context): void {
    if (has_capability('local/mru:syncmarks', $context)) {
        $url = new moodle_url('/local/mru/course_sync.php', ['id' => $course->id]);
        $navigation->add(
            get_string('nav:course_sync', 'local_mru'),
            $url,
            navigation_node::TYPE_CUSTOM,
            null,
            'local_mru_course_sync',
            new pix_icon('i/export', '')
        );
    }
}

/**
 * Add MRU Integration link to the admin settings navigation.
 *
 * @param settings_navigation $settingsnav
 * @param context $context
 */
function local_mru_extend_settings_navigation(settings_navigation $settingsnav, context $context): void {
    // Handled via settings.php — no extra nav needed here.
}

/**
 * Fragment output callback for AJAX student verification.
 *
 * @param array $args Arguments including userid and mru_id.
 * @return string HTML fragment.
 */
function local_mru_output_fragment_verify_student(array $args): string {
    global $OUTPUT;

    $userid = clean_param($args['userid'], PARAM_INT);
    $mruid = clean_param($args['mru_id'], PARAM_ALPHANUMEXT);

    $manager = new \local_mru\student_manager();
    $result = $manager->verify_student($userid, $mruid);

    $data = [
        'verified' => $result->verified,
        'message'  => $result->message,
    ];

    return $OUTPUT->render_from_template('local_mru/verification_result', $data);
}
