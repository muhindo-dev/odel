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
 * Course sync page — sync marks for a specific course.
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('id', PARAM_INT);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
require_capability('local/mru:syncmarks', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/mru/course_sync.php', ['id' => $courseid]));
$PAGE->set_title(get_string('coursesync:title', 'local_mru'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

$marksmanager = new \local_mru\marks_manager();
$coursemanager = new \local_mru\course_manager();

// Handle sync action.
$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'sync' && confirm_sesskey()) {
    $result = $marksmanager->sync_marks_to_core($courseid, $USER->id);
    $type = $result->success
        ? \core\output\notification::NOTIFY_SUCCESS
        : \core\output\notification::NOTIFY_ERROR;
    $msg = $result->success
        ? get_string('coursesync:success', 'local_mru', $result->synced)
        : get_string('coursesync:failed', 'local_mru', implode(', ', $result->errors));
    redirect(new moodle_url('/local/mru/course_sync.php', ['id' => $courseid]), $msg, null, $type);
}

$mapping = $coursemanager->get_course_mapping($courseid);
$history = $marksmanager->get_course_sync_history($courseid, 20);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('coursesync:title', 'local_mru'));

$templatedata = [
    'courseid'          => $courseid,
    'has_mapping'       => $mapping !== false,
    'mru_course_code'   => $mapping->mru_course_code ?? '',
    'mru_programme'     => $mapping->mru_programme_code ?? '',
    'credit_units'      => $mapping->credit_units ?? '',
    'semester'          => $mapping->semester ?? '',
    'academic_year'     => $mapping->academic_year ?? '',
    'sync_url'          => (new moodle_url('/local/mru/course_sync.php', [
        'id' => $courseid, 'action' => 'sync', 'sesskey' => sesskey(),
    ]))->out(false),
    'history'           => array_values(array_map(function($record) {
        return [
            'mru_id'      => $record->mru_id,
            'grade'       => $record->moodle_grade !== null ? round($record->moodle_grade, 2) : '-',
            'status'      => $record->sync_status,
            'time'        => userdate($record->timecreated),
            'is_synced'   => $record->sync_status === 'synced',
            'is_pending'  => $record->sync_status === 'pending',
            'is_failed'   => $record->sync_status === 'failed',
        ];
    }, $history)),
    'has_history'       => !empty($history),
];

echo $OUTPUT->render_from_template('local_mru/course_sync', $templatedata);

echo $OUTPUT->footer();
