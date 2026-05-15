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
 * Marks manager for MRU integration.
 *
 * Handles synchronisation of grades/marks between Moodle gradebook
 * and the MRU core system.
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mru;

use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Manages marks/grades synchronisation between Moodle and MRU core.
 */
class marks_manager {

    /** @var api_client */
    private api_client $api;

    /**
     * Constructor.
     *
     * @param api_client|null $api
     */
    public function __construct(?api_client $api = null) {
        $this->api = $api ?? new api_client();
    }

    /**
     * Collect final grades from Moodle gradebook for a course and prepare for sync.
     *
     * @param int $courseid Moodle course ID.
     * @return array List of grade records ready for sync, each containing grade_item_id.
     */
    public function collect_course_grades(int $courseid): array {
        global $DB, $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        // Get course mapping.
        $coursemap = $DB->get_record('local_mru_course_map', ['courseid' => $courseid]);
        if (!$coursemap) {
            throw new \moodle_exception('error:course_not_mapped', 'local_mru');
        }

        // Get course total grade item.
        $gradeitem = $DB->get_record('grade_items', [
            'courseid' => $courseid,
            'itemtype' => 'course',
        ]);

        if (!$gradeitem) {
            throw new \moodle_exception('error:no_grade_item', 'local_mru');
        }

        // Get all final grades for enrolled students with verified MRU mappings.
        $sql = "SELECT gg.userid, gg.finalgrade, gg.rawgrademax, um.mru_id
                FROM {grade_grades} gg
                JOIN {local_mru_user_map} um ON um.userid = gg.userid AND um.user_type = 'student'
                WHERE gg.itemid = :itemid
                  AND gg.finalgrade IS NOT NULL
                  AND um.verified = 1";

        $grades = $DB->get_records_sql($sql, ['itemid' => $gradeitem->id]);

        $marks = [];
        foreach ($grades as $grade) {
            $marks[] = [
                'userid'          => $grade->userid,
                'mru_id'          => $grade->mru_id,
                'courseid'        => $courseid,
                'grade_item_id'   => $gradeitem->id,
                'mru_course_code' => $coursemap->mru_course_code,
                'moodle_grade'    => $grade->finalgrade,
                'max_grade'       => $grade->rawgrademax,
                'academic_year'   => $coursemap->academic_year,
                'semester'        => $coursemap->semester,
            ];
        }

        return $marks;
    }

    /**
     * Sync marks from Moodle to the MRU core system for a course.
     *
     * Uses an upsert strategy: existing sync records are updated, new ones inserted.
     * Per-student API results are inspected so each record reflects the actual outcome.
     *
     * @param int      $courseid    Moodle course ID.
     * @param int|null $initiatedby User ID who initiated sync, null for scheduled.
     * @return stdClass Sync result with: success, processed, synced, failed, errors.
     */
    public function sync_marks_to_core(int $courseid, ?int $initiatedby = null): stdClass {
        global $DB;

        $result = new stdClass();
        $result->success   = false;
        $result->processed = 0;
        $result->synced    = 0;
        $result->failed    = 0;
        $result->errors    = [];

        $logid = $this->log_sync_start('marks', 'to_core', $initiatedby);

        try {
            $marks = $this->collect_course_grades($courseid);
            $result->processed = count($marks);

            if (empty($marks)) {
                $result->success = true;
                $this->log_sync_finish($logid, 'completed', 0, 0, 0);
                return $result;
            }

            // Build the payload for the API submission.
            $apimarks = [];
            foreach ($marks as $mark) {
                $apimarks[] = [
                    'student_id'    => $mark['mru_id'],
                    'course_code'   => $mark['mru_course_code'],
                    'mark'          => round($mark['moodle_grade'], 2),
                    'max_mark'      => round($mark['max_grade'], 2),
                    'academic_year' => $mark['academic_year'],
                    'semester'      => $mark['semester'],
                ];
            }

            // Submit to API and build a per-student result lookup.
            $apiresults = [];
            if ($this->api->is_configured()) {
                // Inline the response so no intermediate variable is needed.
                foreach ($this->api->submit_marks($apimarks)['results'] ?? [] as $apiresult) {
                    $sid = $apiresult['student_id'] ?? null;
                    if ($sid !== null) {
                        $apiresults[(string)$sid] = $apiresult;
                    }
                }
            }

            $now = time();
            foreach ($marks as $mark) {
                try {
                    // Determine per-student outcome from API response.
                    $apistatus = $apiresults[(string)$mark['mru_id']] ?? null;
                    if (!empty($apiresults)) {
                        // API was called; use its per-student verdict.
                        $syncstatus = (!empty($apistatus['success'])) ? 'synced' : 'failed';
                        $errmsg     = $apistatus['error'] ?? null;
                    } else {
                        // API not configured — mark as synced (DB-only mode).
                        $syncstatus = 'synced';
                        $errmsg     = null;
                    }

                    // Upsert: update existing record for this student/course/period, or insert.
                    $existing = $DB->get_record('local_mru_marks_sync', [
                        'courseid'      => $mark['courseid'],
                        'userid'        => $mark['userid'],
                        'academic_year' => $mark['academic_year'],
                        'semester'      => $mark['semester'],
                    ]);

                    if ($existing) {
                        $existing->mru_id          = $mark['mru_id'];
                        $existing->mru_course_code = $mark['mru_course_code'];
                        $existing->grade_item_id   = $mark['grade_item_id'];
                        $existing->moodle_grade    = $mark['moodle_grade'];
                        $existing->sync_direction  = 'to_core';
                        $existing->sync_status     = $syncstatus;
                        $existing->error_message   = $errmsg;
                        $existing->timesynced      = $now;
                        $DB->update_record('local_mru_marks_sync', $existing);
                    } else {
                        $syncrecord                   = new stdClass();
                        $syncrecord->courseid         = $mark['courseid'];
                        $syncrecord->userid           = $mark['userid'];
                        $syncrecord->mru_id           = $mark['mru_id'];
                        $syncrecord->mru_course_code  = $mark['mru_course_code'];
                        $syncrecord->grade_item_id    = $mark['grade_item_id'];
                        $syncrecord->moodle_grade     = $mark['moodle_grade'];
                        $syncrecord->sync_direction   = 'to_core';
                        $syncrecord->sync_status      = $syncstatus;
                        $syncrecord->error_message    = $errmsg;
                        $syncrecord->academic_year    = $mark['academic_year'];
                        $syncrecord->semester         = $mark['semester'];
                        $syncrecord->timecreated      = $now;
                        $syncrecord->timesynced       = $now;
                        $DB->insert_record('local_mru_marks_sync', $syncrecord);
                    }

                    if ($syncstatus === 'synced') {
                        $result->synced++;
                    } else {
                        $result->failed++;
                        if ($errmsg) {
                            $result->errors[] = "Student {$mark['mru_id']}: {$errmsg}";
                        }
                    }
                } catch (\Exception $e) {
                    $result->failed++;
                    $result->errors[] = "Student {$mark['mru_id']}: " . $e->getMessage();
                }
            }

            $result->success = ($result->failed === 0);
            $this->log_sync_finish($logid, $result->success ? 'completed' : 'failed',
                $result->processed, $result->synced, $result->failed,
                implode("\n", $result->errors) ?: null);

        } catch (\Exception $e) {
            $result->errors[] = $e->getMessage();
            $this->log_sync_finish($logid, 'failed', 0, 0, 0, $e->getMessage());
        }

        return $result;
    }

    /**
     * Get sync history for a course.
     *
     * @param int $courseid Moodle course ID.
     * @param int $limit    Max records to return.
     * @return array List of sync records ordered newest first.
     */
    public function get_course_sync_history(int $courseid, int $limit = 50): array {
        global $DB;
        return $DB->get_records('local_mru_marks_sync', ['courseid' => $courseid],
            'timecreated DESC', '*', 0, $limit);
    }

    /**
     * Get pending sync records.
     *
     * @return array Records with status 'pending'.
     */
    public function get_pending_syncs(): array {
        global $DB;
        return $DB->get_records('local_mru_marks_sync', ['sync_status' => 'pending']);
    }

    /**
     * Start a sync log entry.
     */
    private function log_sync_start(string $type, string $direction, ?int $initiatedby): int {
        global $DB;
        $log                   = new stdClass();
        $log->sync_type        = $type;
        $log->direction        = $direction;
        $log->status           = 'started';
        $log->records_processed = 0;
        $log->records_success  = 0;
        $log->records_failed   = 0;
        $log->initiated_by     = $initiatedby;
        $log->timestarted      = time();
        return $DB->insert_record('local_mru_sync_log', $log);
    }

    /**
     * Finish a sync log entry.
     */
    private function log_sync_finish(int $logid, string $status, int $processed,
            int $success, int $failed, ?string $errors = null): void {
        global $DB;
        $log                   = new stdClass();
        $log->id               = $logid;
        $log->status           = $status;
        $log->records_processed = $processed;
        $log->records_success  = $success;
        $log->records_failed   = $failed;
        $log->error_summary    = $errors;
        $log->timefinished     = time();
        $DB->update_record('local_mru_sync_log', $log);
    }
}
