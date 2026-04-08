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
 * Course manager for MRU integration.
 *
 * Handles course mapping and synchronisation between Moodle
 * and the MRU core system (faculties, programmes, courses).
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mru;

use moodle_exception;
use stdClass;
use core_course_category;

defined('MOODLE_INTERNAL') || die();

/**
 * Manages course mapping and sync between Moodle and MRU core.
 */
class course_manager {

    /** @var api_client */
    private api_client $api;

    /** @var db_manager */
    private db_manager $db;

    /**
     * Constructor.
     *
     * @param api_client|null $api
     * @param db_manager|null $db
     */
    public function __construct(?api_client $api = null, ?db_manager $db = null) {
        $this->api = $api ?? new api_client();
        $this->db  = $db  ?? new db_manager();
    }

    /**
     * Map a Moodle course to an MRU course code.
     *
     * @param int $courseid Moodle course ID.
     * @param string $mrcoursecode MRU course code.
     * @param string|null $programmecode MRU programme code.
     * @param int|null $credits Credit units.
     * @param int|null $semester Semester number.
     * @param string|null $academicyear Academic year.
     */
    public function map_course(int $courseid, string $mrcoursecode, ?string $programmecode = null,
            ?int $credits = null, ?int $semester = null, ?string $academicyear = null): void {
        global $DB;

        $now = time();
        $existing = $DB->get_record('local_mru_course_map', ['courseid' => $courseid]);

        if ($existing) {
            $existing->mru_course_code = $mrcoursecode;
            $existing->mru_programme_code = $programmecode;
            $existing->credit_units = $credits;
            $existing->semester = $semester;
            $existing->academic_year = $academicyear;
            $existing->timemodified = $now;
            $DB->update_record('local_mru_course_map', $existing);
        } else {
            $record = new stdClass();
            $record->courseid = $courseid;
            $record->mru_course_code = $mrcoursecode;
            $record->mru_programme_code = $programmecode;
            $record->credit_units = $credits;
            $record->semester = $semester;
            $record->academic_year = $academicyear;
            $record->timecreated = $now;
            $record->timemodified = $now;
            $DB->insert_record('local_mru_course_map', $record);
        }
    }

    /**
     * Get the MRU mapping for a Moodle course.
     *
     * @param int $courseid Moodle course ID.
     * @return stdClass|false Mapping record or false.
     */
    public function get_course_mapping(int $courseid) {
        global $DB;
        return $DB->get_record('local_mru_course_map', ['courseid' => $courseid]);
    }

    /**
     * Find the Moodle course ID for an MRU course code.
     *
     * @param string $mrcoursecode MRU course code.
     * @return int|false Moodle course ID or false.
     */
    public function find_course_by_mru_code(string $mrcoursecode) {
        global $DB;
        $record = $DB->get_record('local_mru_course_map', ['mru_course_code' => $mrcoursecode]);
        return $record ? (int) $record->courseid : false;
    }

    /**
     * Auto-map existing Moodle courses based on their idnumber (shortname).
     *
     * Courses created by the CLI import script use the MRU course code as
     * their idnumber. This method finds those and creates mappings.
     *
     * @return array Summary with keys: total, mapped, skipped.
     */
    public function auto_map_courses(): array {
        global $DB;

        $summary = ['total' => 0, 'mapped' => 0, 'skipped' => 0];

        // Get all courses with an idnumber (likely MRU course codes from import).
        $courses = $DB->get_records_select('course', "idnumber != '' AND idnumber IS NOT NULL");
        $summary['total'] = count($courses);

        foreach ($courses as $course) {
            // Skip if already mapped.
            $existing = $DB->get_record('local_mru_course_map', ['courseid' => $course->id]);
            if ($existing) {
                $summary['skipped']++;
                continue;
            }

            // Try to find course info from MRU database.
            try {
                $mrudata = $this->db->query(
                    "SELECT pc.coursecode, pc.progcode, c.creditunits, pc.semester
                     FROM acad_programmecourses pc
                     JOIN acad_course c ON c.coursecode = pc.coursecode
                     WHERE pc.coursecode = ?",
                    's',
                    [$course->idnumber]
                );

                if (!empty($mrudata)) {
                    $info = $mrudata[0];
                    $this->map_course(
                        $course->id,
                        $info['coursecode'],
                        $info['progcode'] ?? null,
                        isset($info['creditunits']) ? (int) $info['creditunits'] : null,
                        isset($info['semester']) ? (int) $info['semester'] : null
                    );
                    $summary['mapped']++;
                } else {
                    // No MRU data found — map with just idnumber.
                    $this->map_course($course->id, $course->idnumber);
                    $summary['mapped']++;
                }
            } catch (\Exception $e) {
                // If DB not available, map with just idnumber.
                $this->map_course($course->id, $course->idnumber);
                $summary['mapped']++;
            }
        }

        return $summary;
    }

    /**
     * Get all mapped courses.
     *
     * @return array List of mapping records with course info.
     */
    public function get_all_mappings(): array {
        global $DB;

        $sql = "SELECT cm.*, c.fullname, c.shortname, c.idnumber
                FROM {local_mru_course_map} cm
                JOIN {course} c ON c.id = cm.courseid
                ORDER BY c.fullname";

        return $DB->get_records_sql($sql);
    }

    /**
     * Get unmapped courses (courses without an MRU mapping).
     *
     * @return array List of course records.
     */
    public function get_unmapped_courses(): array {
        global $DB;

        $sql = "SELECT c.id, c.fullname, c.shortname, c.idnumber
                FROM {course} c
                LEFT JOIN {local_mru_course_map} cm ON cm.courseid = c.id
                WHERE cm.id IS NULL AND c.id != :siteid
                ORDER BY c.fullname";

        return $DB->get_records_sql($sql, ['siteid' => SITEID]);
    }
}
