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
 * Student manager for MRU integration.
 *
 * Handles student verification, mapping, and data synchronisation
 * between Moodle and the MRU core system.
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mru;

use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Manages student verification and mapping operations.
 */
class student_manager {

    /** @var api_client */
    private api_client $api;

    /** @var db_manager */
    private db_manager $db;

    /**
     * Constructor.
     *
     * @param api_client|null $api API client instance.
     * @param db_manager|null $db Database manager instance.
     */
    public function __construct(?api_client $api = null, ?db_manager $db = null) {
        $this->api = $api ?? new api_client();
        $this->db  = $db  ?? new db_manager();
    }

    /**
     * Verify a student against the MRU core system.
     *
     * Checks both the core API and the MRU database to confirm the student
     * exists and their registration is active.
     *
     * @param int $userid Moodle user ID.
     * @param string $mruid MRU student number.
     * @return stdClass Verification result with properties: verified, data, message.
     */
    public function verify_student(int $userid, string $mruid): stdClass {
        global $DB;

        $result = new stdClass();
        $result->verified = false;
        $result->data = null;
        $result->message = '';

        // Try API first if configured.
        if ($this->api->is_configured()) {
            try {
                $apidata = $this->api->verify_student($mruid);
                if (!empty($apidata['verified']) && $apidata['verified'] === true) {
                    $result->verified = true;
                    $result->data = $apidata;
                    $result->message = get_string('verification:success_api', 'local_mru');
                    $this->save_user_mapping($userid, $mruid, 'student', true, $apidata);
                    return $result;
                }
            } catch (\Exception $e) {
                // Fall through to database check.
                debugging('API verification failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        // Fallback: check MRU database directly.
        try {
            $students = $this->db->query(
                "SELECT s.studentno, s.surname, s.other_names, s.gender, s.email,
                        s.progcode, s.status, s.acad_year
                 FROM acad_student s
                 WHERE s.studentno = ?",
                's',
                [$mruid]
            );

            if (!empty($students)) {
                $student = $students[0];
                if (strtolower($student['status'] ?? '') === 'active') {
                    $result->verified = true;
                    $result->data = $student;
                    $result->message = get_string('verification:success_db', 'local_mru');
                    $this->save_user_mapping($userid, $mruid, 'student', true, $student);
                } else {
                    $result->message = get_string('verification:inactive', 'local_mru');
                    $this->save_user_mapping($userid, $mruid, 'student', false, $student);
                }
            } else {
                $result->message = get_string('verification:not_found', 'local_mru');
            }
        } catch (\Exception $e) {
            $result->message = get_string('verification:error', 'local_mru');
            debugging('DB verification failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return $result;
    }

    /**
     * Get the MRU mapping for a Moodle user.
     *
     * @param int $userid Moodle user ID.
     * @return stdClass|false Mapping record or false.
     */
    public function get_user_mapping(int $userid) {
        global $DB;
        return $DB->get_record('local_mru_user_map', ['userid' => $userid]);
    }

    /**
     * Get the MRU mapping by MRU ID.
     *
     * @param string $mruid MRU student/staff number.
     * @return stdClass|false Mapping record or false.
     */
    public function get_mapping_by_mru_id(string $mruid) {
        global $DB;
        return $DB->get_record('local_mru_user_map', ['mru_id' => $mruid]);
    }

    /**
     * Save or update a user mapping between Moodle and MRU.
     *
     * @param int $userid Moodle user ID.
     * @param string $mruid MRU ID.
     * @param string $usertype 'student' or 'staff'.
     * @param bool $verified Whether verification passed.
     * @param array|null $coredata Data from core system to cache.
     */
    public function save_user_mapping(int $userid, string $mruid, string $usertype, bool $verified, ?array $coredata = null): void {
        global $DB;

        $now = time();
        $existing = $DB->get_record('local_mru_user_map', ['userid' => $userid]);

        if ($existing) {
            $existing->mru_id = $mruid;
            $existing->user_type = $usertype;
            $existing->verified = $verified ? 1 : 0;
            $existing->verified_at = $verified ? $now : $existing->verified_at;
            $existing->core_data = $coredata !== null ? json_encode($coredata) : $existing->core_data;
            $existing->timemodified = $now;
            $DB->update_record('local_mru_user_map', $existing);
        } else {
            $record = new stdClass();
            $record->userid = $userid;
            $record->mru_id = $mruid;
            $record->user_type = $usertype;
            $record->verified = $verified ? 1 : 0;
            $record->verified_at = $verified ? $now : null;
            $record->core_data = $coredata !== null ? json_encode($coredata) : null;
            $record->timecreated = $now;
            $record->timemodified = $now;
            $DB->insert_record('local_mru_user_map', $record);
        }
    }

    /**
     * Bulk verify all students who are not yet verified.
     *
     * @return array Summary with keys: total, verified, failed, errors.
     */
    public function bulk_verify_unverified(): array {
        global $DB;

        $summary = ['total' => 0, 'verified' => 0, 'failed' => 0, 'errors' => []];

        $records = $DB->get_records('local_mru_user_map', ['verified' => 0, 'user_type' => 'student']);
        $summary['total'] = count($records);

        foreach ($records as $record) {
            try {
                $result = $this->verify_student($record->userid, $record->mru_id);
                if ($result->verified) {
                    $summary['verified']++;
                } else {
                    $summary['failed']++;
                }
            } catch (\Exception $e) {
                $summary['failed']++;
                $summary['errors'][] = "User {$record->userid}: " . $e->getMessage();
            }
        }

        return $summary;
    }

    /**
     * Import students from the MRU database into Moodle.
     *
     * Creates user accounts and mappings for students that don't yet exist.
     *
     * @param string|null $programmecode Limit to a specific programme, or null for all.
     * @return array Summary with keys: total, created, existing, errors.
     */
    public function import_students(?string $programmecode = null): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/user/lib.php');

        $summary = ['total' => 0, 'created' => 0, 'existing' => 0, 'errors' => []];

        $sql = "SELECT s.studentno, s.surname, s.other_names, s.gender, s.email,
                       s.progcode, s.status
                FROM acad_student s
                WHERE s.status = 'Active'";
        $types = '';
        $params = [];

        if ($programmecode !== null) {
            $sql .= " AND s.progcode = ?";
            $types = 's';
            $params[] = $programmecode;
        }

        $students = $this->db->query($sql, $types, $params);
        $summary['total'] = count($students);

        foreach ($students as $student) {
            $studentno = trim($student['studentno']);

            // Check if mapping already exists.
            $existingmap = $DB->get_record('local_mru_user_map', ['mru_id' => $studentno]);
            if ($existingmap) {
                $summary['existing']++;
                continue;
            }

            // Check if user account already exists by username.
            $username = strtolower('mru_' . $studentno);
            $existinguser = $DB->get_record('user', ['username' => $username]);

            if ($existinguser) {
                // User exists but no mapping — create mapping.
                $this->save_user_mapping($existinguser->id, $studentno, 'student', true, $student);
                $summary['existing']++;
                continue;
            }

            // Create new Moodle user.
            try {
                $user = new stdClass();
                $user->username  = $username;
                $user->auth      = 'manual';
                $user->confirmed = 1;
                $user->mnethostid = $CFG->mnet_localhost_id;
                $user->firstname = ucwords(strtolower(trim($student['other_names'] ?? '')));
                $user->lastname  = ucwords(strtolower(trim($student['surname'] ?? '')));
                $user->email     = trim($student['email'] ?? $username . '@mru.ac.ug');
                $user->password  = hash_internal_user_password('Mru@' . $studentno . date('Y'));

                $userid = user_create_user($user, false, false);
                $this->save_user_mapping($userid, $studentno, 'student', true, $student);
                $summary['created']++;
            } catch (\Exception $e) {
                $summary['errors'][] = "Student {$studentno}: " . $e->getMessage();
            }
        }

        return $summary;
    }
}
