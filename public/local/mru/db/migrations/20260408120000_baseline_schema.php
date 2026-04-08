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
 * Baseline migration — marks the existing schema as the starting point.
 *
 * This migration does NOT create any tables (they already exist via install.xml).
 * It exists solely to establish the migration tracking baseline so that future
 * migrations have a known starting state.
 *
 * Tables covered by this baseline:
 *   - local_mru_user_map
 *   - local_mru_marks_sync
 *   - local_mru_course_map
 *   - local_mru_sync_log
 *   - local_mru_registrations
 *   - local_mru_migrations
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mru\migration;

defined('MOODLE_INTERNAL') || die();

class _20260408120000_baseline_schema extends base_migration {

    public function description(): string {
        return 'Baseline: existing schema with 6 tables (user_map, marks_sync, course_map, sync_log, registrations, migrations)';
    }

    public function up(): void {
        // Verify all expected tables exist. If any are missing, something is wrong.
        $expected = [
            'local_mru_user_map',
            'local_mru_marks_sync',
            'local_mru_course_map',
            'local_mru_sync_log',
            'local_mru_registrations',
            'local_mru_migrations',
        ];

        foreach ($expected as $table) {
            if (!$this->table_exists($table)) {
                throw new \coding_exception(
                    "Baseline check failed: table '{$table}' does not exist. " .
                    "Run the Moodle upgrade (admin/cli/upgrade.php) first."
                );
            }
        }
        // All tables verified — baseline is established.
    }

    public function down(): void {
        // The baseline migration cannot be rolled back because the tables
        // were created by install.xml, not by this migration.
        throw new \coding_exception(
            'Cannot rollback the baseline migration. Use Moodle\'s uninstall mechanism instead.'
        );
    }
}
