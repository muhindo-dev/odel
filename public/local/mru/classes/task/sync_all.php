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
 * Scheduled task: Full MRU sync.
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mru\task;

use core\task\scheduled_task;
use local_mru\sync_manager;

/**
 * Runs a full sync cycle between Moodle and MRU core system.
 */
class sync_all extends scheduled_task {

    /**
     * Get task name.
     * @return string
     */
    public function get_name(): string {
        return get_string('task:sync_all', 'local_mru');
    }

    /**
     * Execute the task.
     */
    public function execute(): void {
        if (!get_config('local_mru', 'sync_enabled')) {
            mtrace('MRU sync is disabled. Skipping.');
            return;
        }

        mtrace('Starting MRU full sync...');
        $manager = new sync_manager();
        $result = $manager->run_full_sync();

        if ($result->courses) {
            mtrace("Courses: mapped={$result->courses['mapped']}, skipped={$result->courses['skipped']}");
        }
        if ($result->students) {
            mtrace("Students: created={$result->students['created']}, existing={$result->students['existing']}");
        }
        if ($result->marks && is_array($result->marks)) {
            $totalsynced = 0;
            foreach ($result->marks as $r) {
                $totalsynced += $r->synced ?? 0;
            }
            mtrace("Marks: synced={$totalsynced} across " . count($result->marks) . " courses");
        }

        $duration = $result->finished - $result->started;
        mtrace("Full sync completed in {$duration} seconds.");
    }
}
