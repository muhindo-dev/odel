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
 * CLI script to run MRU sync operations.
 *
 * Usage:
 *   php local/mru/cli/sync.php --type=all
 *   php local/mru/cli/sync.php --type=students
 *   php local/mru/cli/sync.php --type=marks
 *   php local/mru/cli/sync.php --type=courses
 *   php local/mru/cli/sync.php --type=verify
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params(
    [
        'type'    => 'all',
        'help'    => false,
    ],
    [
        't' => 'type',
        'h' => 'help',
    ]
);

if ($options['help']) {
    cli_writeln("MRU Integration Sync Tool

Options:
  --type=TYPE    Sync type: all, students, marks, courses, verify (default: all)
  -h, --help     Show this help

Examples:
  php local/mru/cli/sync.php --type=all
  php local/mru/cli/sync.php --type=students
  php local/mru/cli/sync.php --type=marks
  php local/mru/cli/sync.php --type=courses
  php local/mru/cli/sync.php --type=verify
");
    exit(0);
}

$type = $options['type'];

cli_heading('MRU Sync: ' . $type);

$syncmanager = new \local_mru\sync_manager();
$studentmanager = new \local_mru\student_manager();
$marksmanager = new \local_mru\marks_manager();
$coursemanager = new \local_mru\course_manager();

switch ($type) {
    case 'all':
        $result = $syncmanager->run_full_sync();
        if ($result->courses) {
            cli_writeln("Courses: total={$result->courses['total']}, mapped={$result->courses['mapped']}, skipped={$result->courses['skipped']}");
        }
        if ($result->students) {
            cli_writeln("Students: total={$result->students['total']}, created={$result->students['created']}, existing={$result->students['existing']}");
            if (!empty($result->students['errors'])) {
                foreach ($result->students['errors'] as $err) {
                    cli_writeln("  ERROR: {$err}");
                }
            }
        }
        if ($result->marks && is_array($result->marks)) {
            foreach ($result->marks as $cid => $r) {
                cli_writeln("Course {$cid}: synced={$r->synced}, failed={$r->failed}");
            }
        }
        $duration = $result->finished - $result->started;
        cli_writeln("Completed in {$duration} seconds.");
        break;

    case 'students':
        $result = $studentmanager->import_students();
        cli_writeln("Total: {$result['total']}, Created: {$result['created']}, Existing: {$result['existing']}");
        foreach ($result['errors'] as $err) {
            cli_writeln("  ERROR: {$err}");
        }
        break;

    case 'marks':
        $results = $syncmanager->sync_all_marks();
        foreach ($results as $cid => $r) {
            $status = $r->success ? 'OK' : 'FAIL';
            cli_writeln("Course {$cid}: [{$status}] processed={$r->processed}, synced={$r->synced}, failed={$r->failed}");
        }
        break;

    case 'courses':
        $result = $coursemanager->auto_map_courses();
        cli_writeln("Total: {$result['total']}, Mapped: {$result['mapped']}, Skipped: {$result['skipped']}");
        break;

    case 'verify':
        $result = $studentmanager->bulk_verify_unverified();
        cli_writeln("Total: {$result['total']}, Verified: {$result['verified']}, Failed: {$result['failed']}");
        foreach ($result['errors'] as $err) {
            cli_writeln("  ERROR: {$err}");
        }
        break;

    default:
        cli_error("Unknown sync type: {$type}. Use --help for options.");
}

cli_writeln('Done.');
