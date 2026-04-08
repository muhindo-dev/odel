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
 * CLI tool for managing local_mru database migrations.
 *
 * Usage:
 *   php local/mru/cli/migrate.php --action=status
 *   php local/mru/cli/migrate.php --action=migrate
 *   php local/mru/cli/migrate.php --action=rollback [--steps=1]
 *   php local/mru/cli/migrate.php --action=reset   (DANGEROUS)
 *   php local/mru/cli/migrate.php --action=retry --migration=20260408120000_some_migration
 *   php local/mru/cli/migrate.php --action=create --name=add_phone_to_registrations
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognised) = cli_get_params([
    'action'    => '',
    'steps'     => 1,
    'migration' => '',
    'name'      => '',
    'help'      => false,
], [
    'a' => 'action',
    's' => 'steps',
    'm' => 'migration',
    'n' => 'name',
    'h' => 'help',
]);

if ($unrecognised) {
    $unrecognised = implode(PHP_EOL . '  ', $unrecognised);
    cli_error("Unrecognised options:\n  {$unrecognised}\nUse --help for usage.");
}

if ($options['help'] || empty($options['action'])) {
    $help = <<<EOT
MRU Database Migration Tool

Usage:
  php local/mru/cli/migrate.php --action=<action> [options]

Actions:
  status      Show the status of all migrations (pending, applied, failed).
  migrate     Run all pending migrations.
  rollback    Rollback the last batch of migrations.
  reset       Rollback everything and re-run all migrations (DANGEROUS).
  retry       Retry a specific failed migration.
  create      Create a new empty migration file.

Options:
  --action, -a    The action to perform (required).
  --steps, -s     Number of batches to rollback (default: 1). Used with 'rollback'.
  --migration, -m Migration name for 'retry' action.
  --name, -n      Migration name for 'create' action (e.g. add_phone_to_registrations).
  --help, -h      Show this help.

Examples:
  php local/mru/cli/migrate.php -a status
  php local/mru/cli/migrate.php -a migrate
  php local/mru/cli/migrate.php -a rollback -s 2
  php local/mru/cli/migrate.php -a retry -m 20260408120000_add_phone_column
  php local/mru/cli/migrate.php -a create -n add_phone_to_registrations

EOT;
    cli_writeln($help);
    exit(0);
}

// Output helper.
$output = function (string $message) {
    cli_writeln($message);
};

$runner = new \local_mru\migration\runner();

switch ($options['action']) {

    case 'status':
        $statuses = $runner->status();

        if (empty($statuses)) {
            $output("No migration files found in db/migrations/.");
            break;
        }

        $output(str_pad('Migration', 55) . str_pad('Status', 15) . str_pad('Batch', 8) . str_pad('Time', 10) . 'Applied');
        $output(str_repeat('-', 110));

        foreach ($statuses as $s) {
            $time = $s['execution_time_ms'] !== null ? $s['execution_time_ms'] . 'ms' : '-';
            $applied = $s['timecreated'] ? userdate($s['timecreated'], '%Y-%m-%d %H:%M') : '-';
            $batch = $s['batch'] ?? '-';

            // Colour the status.
            $status = $s['status'];
            if ($status === 'applied') {
                $statusfmt = "\033[32m{$status}\033[0m"; // Green.
            } elseif ($status === 'failed') {
                $statusfmt = "\033[31m{$status}\033[0m"; // Red.
            } elseif ($status === 'pending') {
                $statusfmt = "\033[33m{$status}\033[0m"; // Yellow.
            } elseif ($status === 'rolled_back') {
                $statusfmt = "\033[36m{$status}\033[0m"; // Cyan.
            } else {
                $statusfmt = $status;
            }

            $output(
                str_pad($s['migration'], 55) .
                str_pad($statusfmt, 24) . // Extra padding for ANSI codes.
                str_pad((string) $batch, 8) .
                str_pad($time, 10) .
                $applied
            );
        }

        $pending = array_filter($statuses, fn($s) => in_array($s['status'], ['pending', 'rolled_back', 'failed']));
        $failed = array_filter($statuses, fn($s) => $s['status'] === 'failed');
        $output('');
        $output(count($statuses) . " total, " . count($pending) . " pending, " . count($failed) . " failed.");
        break;

    case 'migrate':
        $result = $runner->migrate($output);
        if (!empty($result['errors'])) {
            exit(1);
        }
        break;

    case 'rollback':
        $steps = max(1, (int) $options['steps']);
        $result = $runner->rollback($steps, $output);
        if (!empty($result['errors'])) {
            exit(1);
        }
        break;

    case 'reset':
        $output("\033[31mWARNING: This will rollback ALL migrations and re-run them.\033[0m");
        $output("This can cause DATA LOSS. Only use in development/staging.");
        $confirm = cli_input("Type 'yes' to confirm: ");
        if ($confirm !== 'yes') {
            $output("Aborted.");
            exit(0);
        }
        $result = $runner->reset($output);
        if (!empty($result['errors'])) {
            exit(1);
        }
        break;

    case 'retry':
        if (empty($options['migration'])) {
            cli_error("--migration is required for retry action.");
        }
        $success = $runner->retry($options['migration'], $output);
        if (!$success) {
            exit(1);
        }
        break;

    case 'create':
        if (empty($options['name'])) {
            cli_error("--name is required for create action. Example: --name=add_phone_to_registrations");
        }

        // Sanitize name.
        $name = preg_replace('/[^a-z0-9_]/', '_', strtolower($options['name']));
        $timestamp = date('YmdHis');
        $fullname = "{$timestamp}_{$name}";
        $filename = "{$fullname}.php";

        $dir = __DIR__ . '/../db/migrations/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filepath = $dir . $filename;
        if (file_exists($filepath)) {
            cli_error("Migration file already exists: {$filepath}");
        }

        $classname = "_{$fullname}";
        $template = <<<PHP
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
 * Migration: {$name}
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mru\migration;

use xmldb_table;
use xmldb_field;
use xmldb_key;
use xmldb_index;

defined('MOODLE_INTERNAL') || die();

class {$classname} extends base_migration {

    public function description(): string {
        return '{$name}';
    }

    public function up(): void {
        // TODO: Implement the forward migration.
    }

    public function down(): void {
        // TODO: Implement the rollback.
    }
}

PHP;

        file_put_contents($filepath, $template);
        $output("Created: db/migrations/{$filename}");
        break;

    default:
        cli_error("Unknown action: '{$options['action']}'. Use --help for usage.");
}
