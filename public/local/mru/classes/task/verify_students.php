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
 * Scheduled task: Bulk verify students.
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mru\task;

use core\task\scheduled_task;
use local_mru\student_manager;

/**
 * Verifies all unverified students against the MRU core system.
 */
class verify_students extends scheduled_task {

    /**
     * Get task name.
     * @return string
     */
    public function get_name(): string {
        return get_string('task:verify_students', 'local_mru');
    }

    /**
     * Execute the task.
     */
    public function execute(): void {
        if (!get_config('local_mru', 'sync_enabled')) {
            mtrace('MRU sync is disabled. Skipping verification.');
            return;
        }

        mtrace('Starting bulk student verification...');
        $manager = new student_manager();
        $result = $manager->bulk_verify_unverified();

        mtrace("Verification complete: total={$result['total']}, verified={$result['verified']}, failed={$result['failed']}");

        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $error) {
                mtrace("  ERROR: {$error}");
            }
        }
    }
}
