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
 * MRU Integration dashboard page.
 *
 * Displays integration status, sync history, and controls for admins.
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/mru:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/mru/index.php'));
$PAGE->set_title(get_string('dashboard:title', 'local_mru'));
$PAGE->set_heading(get_string('dashboard:title', 'local_mru'));
$PAGE->set_pagelayout('admin');

$syncmanager = new \local_mru\sync_manager();
$status = $syncmanager->get_status();
$recentlogs = $syncmanager->get_recent_sync_logs(10);

// Handle manual sync trigger.
$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'sync' && confirm_sesskey()) {
    $result = $syncmanager->run_full_sync($USER->id);
    redirect(
        new moodle_url('/local/mru/index.php'),
        get_string('dashboard:sync_complete', 'local_mru'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('dashboard:title', 'local_mru'));

// Status overview cards.
$templatedata = [
    'total_users_mapped'  => $status->total_users_mapped,
    'verified_students'   => $status->verified_students,
    'unverified_students' => $status->unverified_students,
    'mapped_courses'      => $status->mapped_courses,
    'pending_syncs'       => $status->pending_syncs,
    'failed_syncs'        => $status->failed_syncs,
    'has_last_sync'       => $status->last_sync !== null,
    'last_sync_time'      => $status->last_sync ? userdate($status->last_sync->timestarted) : '',
    'last_sync_status'    => $status->last_sync->status ?? '',
    'sync_url'            => (new moodle_url('/local/mru/index.php', ['action' => 'sync', 'sesskey' => sesskey()]))->out(false),
    'settings_url'        => (new moodle_url('/admin/settings.php', ['section' => 'local_mru']))->out(false),
    'sync_enabled'        => (bool) get_config('local_mru', 'sync_enabled'),
    'logs'                => array_values(array_map(function($log) {
        return [
            'sync_type'    => $log->sync_type,
            'direction'    => $log->direction,
            'status'       => $log->status,
            'processed'    => $log->records_processed,
            'success'      => $log->records_success,
            'failed'       => $log->records_failed,
            'time'         => userdate($log->timestarted),
            'is_completed' => $log->status === 'completed',
            'is_failed'    => $log->status === 'failed',
        ];
    }, $recentlogs)),
    'has_logs'            => !empty($recentlogs),
];

echo $OUTPUT->render_from_template('local_mru/dashboard', $templatedata);

echo $OUTPUT->footer();
