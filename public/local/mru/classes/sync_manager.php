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
 * Sync manager orchestrating all MRU sync operations.
 *
 * Central coordinator for student, marks, and course synchronisation.
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mru;

use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * High-level orchestrator for all sync operations.
 */
class sync_manager {

    /** @var student_manager */
    private student_manager $students;

    /** @var marks_manager */
    private marks_manager $marks;

    /** @var course_manager */
    private course_manager $courses;

    /**
     * Constructor.
     *
     * @param student_manager|null $students
     * @param marks_manager|null $marks
     * @param course_manager|null $courses
     */
    public function __construct(
        ?student_manager $students = null,
        ?marks_manager $marks = null,
        ?course_manager $courses = null
    ) {
        $this->students = $students ?? new student_manager();
        $this->marks    = $marks    ?? new marks_manager();
        $this->courses  = $courses  ?? new course_manager();
    }

    /**
     * Run a full sync cycle (courses, students, marks).
     *
     * @param int|null $initiatedby User ID of who initiated, null for cron.
     * @return stdClass Overall summary.
     */
    public function run_full_sync(?int $initiatedby = null): stdClass {
        $result = new stdClass();
        $result->courses = null;
        $result->students = null;
        $result->marks = null;
        $result->started = time();

        $config = (object) [
            'sync_courses'  => get_config('local_mru', 'sync_courses'),
            'sync_students' => get_config('local_mru', 'sync_students'),
            'sync_marks'    => get_config('local_mru', 'sync_marks'),
        ];

        // Step 1: Sync course mappings.
        if ($config->sync_courses) {
            $result->courses = $this->courses->auto_map_courses();
        }

        // Step 2: Import/verify students.
        if ($config->sync_students) {
            $result->students = $this->students->import_students();
        }

        // Step 3: Sync marks for all mapped courses.
        if ($config->sync_marks) {
            $result->marks = $this->sync_all_marks($initiatedby);
        }

        $result->finished = time();
        return $result;
    }

    /**
     * Sync marks for all mapped courses.
     *
     * @param int|null $initiatedby User ID.
     * @return array Summary per course.
     */
    public function sync_all_marks(?int $initiatedby = null): array {
        global $DB;

        $mappings = $DB->get_records('local_mru_course_map');
        $results = [];

        foreach ($mappings as $mapping) {
            try {
                $results[$mapping->courseid] = $this->marks->sync_marks_to_core(
                    $mapping->courseid,
                    $initiatedby
                );
            } catch (\Exception $e) {
                $result = new stdClass();
                $result->success = false;
                $result->errors = [$e->getMessage()];
                $results[$mapping->courseid] = $result;
            }
        }

        return $results;
    }

    /**
     * Get the latest sync log entries.
     *
     * @param int $limit Max records.
     * @return array Sync log records.
     */
    public function get_recent_sync_logs(int $limit = 20): array {
        global $DB;
        return $DB->get_records('local_mru_sync_log', null, 'timestarted DESC', '*', 0, $limit);
    }

    /**
     * Get a summary of the current integration status.
     *
     * @return stdClass Status summary.
     */
    public function get_status(): stdClass {
        global $DB;

        $status = new stdClass();
        $status->total_users_mapped = $DB->count_records('local_mru_user_map');
        $status->verified_students  = $DB->count_records('local_mru_user_map', ['verified' => 1, 'user_type' => 'student']);
        $status->unverified_students = $DB->count_records('local_mru_user_map', ['verified' => 0, 'user_type' => 'student']);
        $status->mapped_courses     = $DB->count_records('local_mru_course_map');
        $status->pending_syncs      = $DB->count_records('local_mru_marks_sync', ['sync_status' => 'pending']);
        $status->failed_syncs       = $DB->count_records('local_mru_marks_sync', ['sync_status' => 'failed']);

        // Last sync.
        $lastsync = $DB->get_records('local_mru_sync_log', null, 'timestarted DESC', '*', 0, 1);
        $status->last_sync = !empty($lastsync) ? reset($lastsync) : null;

        return $status;
    }
}
