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
 * Migration runner — discovers, executes, and rolls back migrations.
 *
 * Tracks migration state in the local_mru_migrations table.
 * Supports: migrate (run pending), rollback (undo last batch),
 * status (list all), and reset (rollback everything + re-run all).
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mru\migration;

use coding_exception;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * Migration runner for local_mru.
 */
class runner {

    /** @var string Path to the migrations directory. */
    private string $migrations_path;

    /** @var \moodle_database DB instance. */
    private \moodle_database $db;

    /** @var string The migrations tracking table name (without prefix). */
    private const TABLE = 'local_mru_migrations';

    /** Migration statuses. */
    private const STATUS_PENDING = 'pending';
    private const STATUS_APPLIED = 'applied';
    private const STATUS_ROLLED_BACK = 'rolled_back';
    private const STATUS_FAILED = 'failed';

    /**
     * Constructor.
     */
    public function __construct() {
        global $DB;
        $this->db = $DB;
        $this->migrations_path = __DIR__ . '/../../db/migrations/';
    }

    /**
     * Ensure the migrations tracking table exists.
     *
     * @return void
     */
    public function ensure_table(): void {
        $dbman = $this->db->get_manager();
        $table = new \xmldb_table(self::TABLE);

        if ($dbman->table_exists($table)) {
            return;
        }

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('migration', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('batch', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'applied');
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('executed_by', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('execution_time_ms', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('error_message', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('checksum', XMLDB_TYPE_CHAR, '64', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        $table->add_index('ix_migration', XMLDB_INDEX_UNIQUE, ['migration']);
        $table->add_index('ix_batch', XMLDB_INDEX_NOTUNIQUE, ['batch']);
        $table->add_index('ix_status', XMLDB_INDEX_NOTUNIQUE, ['status']);

        $dbman->create_table($table);
    }

    /**
     * Get all migration files from the migrations directory.
     *
     * Files must follow the naming convention: YYYYMMDDHHMMSS_description.php
     *
     * @return array Sorted array of ['name' => string, 'path' => string].
     */
    public function discover_migrations(): array {
        $dir = $this->migrations_path;
        if (!is_dir($dir)) {
            return [];
        }

        $files = [];
        $pattern = '/^\d{14}_[a-z0-9_]+\.php$/';

        foreach (scandir($dir) as $file) {
            if (preg_match($pattern, $file)) {
                $files[] = [
                    'name' => pathinfo($file, PATHINFO_FILENAME),
                    'path' => $dir . $file,
                ];
            }
        }

        // Sort by filename (timestamp prefix ensures chronological order).
        usort($files, fn($a, $b) => strcmp($a['name'], $b['name']));
        return $files;
    }

    /**
     * Get list of already-applied migration names.
     *
     * @return array Migration names that have been applied (status = 'applied').
     */
    public function get_applied(): array {
        $this->ensure_table();

        $records = $this->db->get_records(self::TABLE, ['status' => self::STATUS_APPLIED], 'migration ASC');
        return array_column($records, 'migration');
    }

    /**
     * Get all pending migrations (discovered but not yet applied).
     *
     * @return array Array of ['name' => string, 'path' => string].
     */
    public function get_pending(): array {
        $applied = $this->get_applied();
        $all = $this->discover_migrations();

        return array_filter($all, fn($m) => !in_array($m['name'], $applied));
    }

    /**
     * Get next batch number.
     *
     * @return int
     */
    private function next_batch(): int {
        $max = $this->db->get_field_sql(
            "SELECT MAX(batch) FROM {" . self::TABLE . "}"
        );
        return ((int) $max) + 1;
    }

    /**
     * Load and instantiate a migration class from its file.
     *
     * @param string $name Migration name (filename without .php).
     * @param string $path Full path to the migration file.
     * @return base_migration
     * @throws coding_exception If the file doesn't contain a valid migration class.
     */
    private function load_migration(string $name, string $path): base_migration {
        require_once($path);

        // The class must be in the local_mru\migration namespace.
        // Class names start with underscore because PHP class names cannot start with a digit.
        $class = '\\local_mru\\migration\\_' . $name;

        if (!class_exists($class)) {
            throw new coding_exception("Migration class '{$class}' not found in {$path}");
        }

        $instance = new $class();
        if (!$instance instanceof base_migration) {
            throw new coding_exception("Migration class '{$class}' must extend base_migration");
        }

        return $instance;
    }

    /**
     * Compute a checksum for a migration file (integrity verification).
     *
     * @param string $path File path.
     * @return string SHA-256 hash.
     */
    private function checksum(string $path): string {
        return hash_file('sha256', $path);
    }

    /**
     * Get the current user ID (0 for CLI).
     *
     * @return int
     */
    private function current_user_id(): int {
        global $USER;
        return isset($USER->id) ? (int) $USER->id : 0;
    }

    /**
     * Insert or update a migration tracking record.
     *
     * If a record already exists for this migration (e.g. from a previous failed run),
     * update it instead of inserting a duplicate.
     *
     * @param object $record The record data (must have 'migration' field).
     */
    private function upsert_tracking(object $record): void {
        $existing = $this->db->get_record(self::TABLE, ['migration' => $record->migration]);
        if ($existing) {
            $record->id = $existing->id;
            $this->db->update_record(self::TABLE, $record);
        } else {
            $this->db->insert_record(self::TABLE, $record);
        }
    }

    /**
     * Run all pending migrations.
     *
     * @param callable|null $output Callback for status messages: fn(string $message).
     * @return array ['applied' => int, 'errors' => array].
     */
    public function migrate(?callable $output = null): array {
        $this->ensure_table();
        $pending = $this->get_pending();

        if (empty($pending)) {
            if ($output) {
                $output('Nothing to migrate. All migrations are up to date.');
            }
            return ['applied' => 0, 'errors' => []];
        }

        $batch = $this->next_batch();
        $applied = 0;
        $errors = [];

        if ($output) {
            $output("Running " . count($pending) . " pending migration(s) [batch {$batch}]...");
        }

        foreach ($pending as $migration) {
            $name = $migration['name'];
            $path = $migration['path'];

            if ($output) {
                $output("  Migrating: {$name}");
            }

            $start = microtime(true);

            try {
                $instance = $this->load_migration($name, $path);

                // Wrap in a transaction for atomicity.
                $transaction = $this->db->start_delegated_transaction();

                $instance->up();

                $transaction->allow_commit();

                $elapsed = (int) ((microtime(true) - $start) * 1000);

                // Record success.
                $this->upsert_tracking((object) [
                    'migration' => $name,
                    'batch' => $batch,
                    'status' => self::STATUS_APPLIED,
                    'description' => $instance->description(),
                    'executed_by' => $this->current_user_id(),
                    'execution_time_ms' => $elapsed,
                    'error_message' => null,
                    'checksum' => $this->checksum($path),
                    'timecreated' => time(),
                ]);

                $applied++;

                if ($output) {
                    $output("  Migrated:  {$name} ({$elapsed}ms)");
                }

            } catch (\Throwable $e) {
                $elapsed = (int) ((microtime(true) - $start) * 1000);

                // Record failure.
                $this->upsert_tracking((object) [
                    'migration' => $name,
                    'batch' => $batch,
                    'status' => self::STATUS_FAILED,
                    'description' => '',
                    'executed_by' => $this->current_user_id(),
                    'execution_time_ms' => $elapsed,
                    'error_message' => $e->getMessage() . "\n" . $e->getTraceAsString(),
                    'checksum' => $this->checksum($path),
                    'timecreated' => time(),
                ]);

                $errors[] = ['migration' => $name, 'error' => $e->getMessage()];

                if ($output) {
                    $output("  FAILED:    {$name} — " . $e->getMessage());
                }

                // Stop on first error to maintain consistency.
                break;
            }
        }

        // Log to Moodle events.
        $this->log_event('migrate', $applied, $errors);

        return ['applied' => $applied, 'errors' => $errors];
    }

    /**
     * Rollback the last batch of migrations.
     *
     * @param int $steps Number of batches to rollback (default: 1).
     * @param callable|null $output Callback for status messages.
     * @return array ['rolled_back' => int, 'errors' => array].
     */
    public function rollback(int $steps = 1, ?callable $output = null): array {
        $this->ensure_table();

        // Get the last N batches.
        $maxbatch = $this->db->get_field_sql(
            "SELECT MAX(batch) FROM {" . self::TABLE . "} WHERE status = ?",
            [self::STATUS_APPLIED]
        );

        if (!$maxbatch) {
            if ($output) {
                $output('Nothing to rollback.');
            }
            return ['rolled_back' => 0, 'errors' => []];
        }

        $minbatch = max(1, $maxbatch - $steps + 1);
        $records = $this->db->get_records_select(
            self::TABLE,
            "status = ? AND batch >= ?",
            [self::STATUS_APPLIED, $minbatch],
            'migration DESC'
        );

        if (empty($records)) {
            if ($output) {
                $output('Nothing to rollback.');
            }
            return ['rolled_back' => 0, 'errors' => []];
        }

        $rolledback = 0;
        $errors = [];
        $allfiles = $this->discover_migrations();
        $filemap = [];
        foreach ($allfiles as $f) {
            $filemap[$f['name']] = $f['path'];
        }

        if ($output) {
            $output("Rolling back " . count($records) . " migration(s)...");
        }

        foreach ($records as $record) {
            $name = $record->migration;
            $path = $filemap[$name] ?? null;

            if (!$path || !file_exists($path)) {
                $errors[] = ['migration' => $name, 'error' => "Migration file not found"];
                if ($output) {
                    $output("  SKIPPED: {$name} — file not found");
                }
                continue;
            }

            // Verify checksum hasn't changed.
            if ($record->checksum && $this->checksum($path) !== $record->checksum) {
                $errors[] = ['migration' => $name, 'error' => 'File modified since it was applied (checksum mismatch)'];
                if ($output) {
                    $output("  WARNING: {$name} — checksum mismatch, file was modified after apply");
                }
                // Still proceed but warn.
            }

            if ($output) {
                $output("  Rolling back: {$name}");
            }

            $start = microtime(true);

            try {
                $instance = $this->load_migration($name, $path);

                $transaction = $this->db->start_delegated_transaction();

                $instance->down();

                $transaction->allow_commit();

                $elapsed = (int) ((microtime(true) - $start) * 1000);

                // Update record.
                $record->status = self::STATUS_ROLLED_BACK;
                $record->execution_time_ms = $elapsed;
                $this->db->update_record(self::TABLE, $record);

                $rolledback++;

                if ($output) {
                    $output("  Rolled back: {$name} ({$elapsed}ms)");
                }

            } catch (\Throwable $e) {
                // Rollback the transaction if it was started.
                try {
                    $this->db->force_transaction_rollback();
                } catch (\Throwable $ignore) {
                    // Already rolled back or no transaction.
                }

                $errors[] = ['migration' => $name, 'error' => $e->getMessage()];
                if ($output) {
                    $output("  FAILED: {$name} — " . $e->getMessage());
                }
                // Continue with remaining migrations instead of stopping.
            }
        }

        $this->log_event('rollback', $rolledback, $errors);

        return ['rolled_back' => $rolledback, 'errors' => $errors];
    }

    /**
     * Get full migration status (for display).
     *
     * @return array Array of status records.
     */
    public function status(): array {
        $this->ensure_table();

        $applied = $this->db->get_records(self::TABLE, null, 'migration ASC');
        $appliedmap = [];
        foreach ($applied as $r) {
            $appliedmap[$r->migration] = $r;
        }

        $all = $this->discover_migrations();
        $status = [];

        foreach ($all as $m) {
            $name = $m['name'];
            if (isset($appliedmap[$name])) {
                $r = $appliedmap[$name];
                $status[] = [
                    'migration' => $name,
                    'batch' => $r->batch,
                    'status' => $r->status,
                    'description' => $r->description,
                    'executed_by' => $r->executed_by,
                    'execution_time_ms' => $r->execution_time_ms,
                    'error_message' => $r->error_message,
                    'timecreated' => $r->timecreated,
                ];
            } else {
                $status[] = [
                    'migration' => $name,
                    'batch' => null,
                    'status' => self::STATUS_PENDING,
                    'description' => '',
                    'executed_by' => null,
                    'execution_time_ms' => null,
                    'error_message' => null,
                    'timecreated' => null,
                ];
            }
        }

        return $status;
    }

    /**
     * Reset: rollback everything then re-run all migrations.
     *
     * WARNING: This can cause data loss. Only for development/staging.
     *
     * @param callable|null $output Callback for status messages.
     * @return array Combined results.
     */
    public function reset(?callable $output = null): array {
        if ($output) {
            $output("=== RESET: Rolling back all migrations ===");
        }

        // Rollback all batches.
        $maxbatch = $this->db->get_field_sql(
            "SELECT MAX(batch) FROM {" . self::TABLE . "} WHERE status = ?",
            [self::STATUS_APPLIED]
        );

        $rollback_result = ['rolled_back' => 0, 'errors' => []];
        if ($maxbatch) {
            $rollback_result = $this->rollback((int) $maxbatch, $output);
        }

        if (!empty($rollback_result['errors'])) {
            return $rollback_result;
        }

        // Clear all records so pending detection works.
        $this->db->delete_records(self::TABLE);

        if ($output) {
            $output("=== RESET: Re-running all migrations ===");
        }

        $migrate_result = $this->migrate($output);

        return [
            'rolled_back' => $rollback_result['rolled_back'],
            'applied' => $migrate_result['applied'],
            'errors' => array_merge($rollback_result['errors'], $migrate_result['errors']),
        ];
    }

    /**
     * Retry a specific failed migration.
     *
     * @param string $name Migration name.
     * @param callable|null $output Status callback.
     * @return bool True if retry succeeded.
     */
    public function retry(string $name, ?callable $output = null): bool {
        $this->ensure_table();

        $record = $this->db->get_record(self::TABLE, [
            'migration' => $name,
            'status' => self::STATUS_FAILED,
        ]);

        if (!$record) {
            if ($output) {
                $output("Migration '{$name}' is not in failed state.");
            }
            return false;
        }

        // Remove the failed record so it appears as pending.
        $this->db->delete_records(self::TABLE, ['id' => $record->id]);

        // Run migrate — it will pick this up as pending.
        $result = $this->migrate($output);
        return empty($result['errors']);
    }

    /**
     * Log migration events to Moodle's event system.
     *
     * @param string $action migrate|rollback|reset
     * @param int $count Number of migrations affected.
     * @param array $errors Any errors.
     * @return void
     */
    private function log_event(string $action, int $count, array $errors): void {
        $data = [
            'context' => \context_system::instance(),
            'other' => [
                'action' => $action,
                'count' => $count,
                'errors' => count($errors),
                'details' => json_encode($errors),
            ],
        ];

        // Use generic Moodle logging since we track details in our own table.
        $eventclass = '\\local_mru\\event\\migration_executed';
        if (class_exists($eventclass)) {
            $event = $eventclass::create($data);
            $event->trigger();
        }
    }
}
