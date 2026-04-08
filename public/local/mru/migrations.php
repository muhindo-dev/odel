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
 * Web interface for managing local_mru database migrations.
 *
 * Requires local/mru:manage capability (site admin / manager role).
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/mru:manage', $context);

$PAGE->set_url(new moodle_url('/local/mru/migrations.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('migrations:title', 'local_mru'));
$PAGE->set_heading(get_string('migrations:title', 'local_mru'));
$PAGE->set_pagelayout('admin');

$action = optional_param('action', '', PARAM_ALPHA);
$sesskey = optional_param('sesskey', '', PARAM_RAW);

$runner = new \local_mru\migration\runner();
$messages = [];

// Handle POST actions.
if ($action && confirm_sesskey($sesskey)) {
    $output = function (string $msg) use (&$messages) {
        $messages[] = $msg;
    };

    switch ($action) {
        case 'migrate':
            $result = $runner->migrate($output);
            break;
        case 'rollback':
            $result = $runner->rollback(1, $output);
            break;
        default:
            $messages[] = 'Unknown action.';
    }

    // Redirect with messages stored in session.
    $SESSION->local_mru_migration_messages = $messages;
    redirect(new moodle_url('/local/mru/migrations.php'));
}

// Retrieve any stored messages from redirect.
$messages = $SESSION->local_mru_migration_messages ?? [];
unset($SESSION->local_mru_migration_messages);

// Get current status.
$statuses = $runner->status();

echo $OUTPUT->header();

// Navigation breadcrumb.
echo $OUTPUT->heading(get_string('migrations:title', 'local_mru'), 2);

// Show messages.
foreach ($messages as $msg) {
    // Strip ANSI codes for web display.
    $clean = preg_replace('/\033\[[0-9;]*m/', '', $msg);
    if (stripos($clean, 'FAILED') !== false) {
        echo $OUTPUT->notification($clean, 'error');
    } elseif (stripos($clean, 'WARNING') !== false) {
        echo $OUTPUT->notification($clean, 'warning');
    } else {
        echo $OUTPUT->notification($clean, 'info');
    }
}

// Action buttons.
$pending = array_filter($statuses, fn($s) => $s['status'] === 'pending');
$applied = array_filter($statuses, fn($s) => $s['status'] === 'applied');

echo '<div class="mb-3">';
if (!empty($pending)) {
    $migrateurl = new moodle_url('/local/mru/migrations.php', [
        'action' => 'migrate',
        'sesskey' => sesskey(),
    ]);
    echo $OUTPUT->single_button($migrateurl, get_string('migrations:run_pending', 'local_mru', count($pending)), 'post', [
        'class' => 'btn-primary',
    ]);
}
if (!empty($applied)) {
    $rollbackurl = new moodle_url('/local/mru/migrations.php', [
        'action' => 'rollback',
        'sesskey' => sesskey(),
    ]);
    echo $OUTPUT->single_button($rollbackurl, get_string('migrations:rollback_last', 'local_mru'), 'post', [
        'class' => 'btn-warning ml-2',
    ]);
}
echo '</div>';

// Status table.
if (empty($statuses)) {
    echo $OUTPUT->notification(get_string('migrations:none_found', 'local_mru'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('migrations:col_migration', 'local_mru'),
        get_string('migrations:col_status', 'local_mru'),
        get_string('migrations:col_batch', 'local_mru'),
        get_string('migrations:col_time', 'local_mru'),
        get_string('migrations:col_applied', 'local_mru'),
        get_string('migrations:col_description', 'local_mru'),
    ];
    $table->attributes['class'] = 'generaltable table-sm';

    foreach ($statuses as $s) {
        $status = $s['status'];
        switch ($status) {
            case 'applied':
                $badge = '<span class="badge badge-success bg-success">Applied</span>';
                break;
            case 'failed':
                $badge = '<span class="badge badge-danger bg-danger" title="' .
                    s($s['error_message'] ?? '') . '">Failed</span>';
                break;
            case 'pending':
                $badge = '<span class="badge badge-warning bg-warning">Pending</span>';
                break;
            case 'rolled_back':
                $badge = '<span class="badge badge-secondary bg-secondary">Rolled back</span>';
                break;
            default:
                $badge = '<span class="badge badge-info bg-info">' . s($status) . '</span>';
        }

        $time = $s['execution_time_ms'] !== null ? $s['execution_time_ms'] . 'ms' : '-';
        $applied = $s['timecreated'] ? userdate($s['timecreated'], '%Y-%m-%d %H:%M') : '-';
        $batch = $s['batch'] ?? '-';

        // Make migration name more readable.
        $name = $s['migration'];
        $shortname = preg_replace('/^\d{14}_/', '', $name);

        $table->data[] = [
            '<code>' . s($name) . '</code>',
            $badge,
            $batch,
            $time,
            $applied,
            s($s['description'] ?: $shortname),
        ];
    }

    echo html_writer::table($table);

    // Summary.
    $totalapplied = count(array_filter($statuses, fn($s) => $s['status'] === 'applied'));
    $totalfailed = count(array_filter($statuses, fn($s) => $s['status'] === 'failed'));
    echo '<p class="text-muted">' . count($statuses) . ' total, ' . count($pending) .
        ' pending, ' . $totalapplied . ' applied, ' . $totalfailed . ' failed.</p>';
}

// CLI hint.
echo '<div class="alert alert-info mt-3">';
echo '<strong>' . get_string('migrations:cli_hint_title', 'local_mru') . '</strong><br>';
echo '<code>php local/mru/cli/migrate.php --action=status</code><br>';
echo '<code>php local/mru/cli/migrate.php --action=migrate</code><br>';
echo '<code>php local/mru/cli/migrate.php --action=rollback</code><br>';
echo '<code>php local/mru/cli/migrate.php --action=create --name=your_migration_name</code>';
echo '</div>';

echo $OUTPUT->footer();
