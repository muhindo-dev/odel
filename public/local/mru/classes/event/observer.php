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
 * Event observer callbacks for local_mru.
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mru\event;

use core\event\user_loggedin;
use core\event\user_enrolment_created;
use core\event\user_graded;
use local_mru\student_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Handles Moodle events relevant to MRU integration.
 */
class observer {

    /** @var bool Prevent recursion while restoring locked email. */
    private static bool $restoringlockedemail = false;

    /**
     * Called when a user logs in.
     *
     * If verify_on_login is enabled and the user has an MRU mapping,
     * re-verify their status against the core system.
     *
     * @param user_loggedin $event
     */
    public static function user_loggedin(user_loggedin $event): void {
        if (!get_config('local_mru', 'verify_on_login')) {
            return;
        }

        $userid = $event->objectid;
        $manager = new student_manager();
        $mapping = $manager->get_user_mapping($userid);

        if ($mapping && $mapping->user_type === 'student') {
            try {
                $manager->verify_student($userid, $mapping->mru_id);
            } catch (\Exception $e) {
                debugging('MRU login verification failed for user ' . $userid . ': ' . $e->getMessage(),
                    DEBUG_DEVELOPER);
            }
        }
    }

    /**
     * Called when a user is enrolled in a course.
     *
     * If verify_on_enrol is enabled, verify the student record.
     *
     * @param user_enrolment_created $event
     */
    public static function user_enrolled(user_enrolment_created $event): void {
        if (!get_config('local_mru', 'verify_on_enrol')) {
            return;
        }

        $userid = $event->relateduserid;
        $manager = new student_manager();
        $mapping = $manager->get_user_mapping($userid);

        if ($mapping && $mapping->user_type === 'student' && !$mapping->verified) {
            try {
                $manager->verify_student($userid, $mapping->mru_id);
            } catch (\Exception $e) {
                debugging('MRU enrol verification failed for user ' . $userid . ': ' . $e->getMessage(),
                    DEBUG_DEVELOPER);
            }
        }
    }

    /**
     * Called when a user is graded.
     *
     * Flags the grade for sync with the core system.
     *
     * @param user_graded $event
     */
    public static function user_graded(user_graded $event): void {
        global $DB;

        if (!get_config('local_mru', 'sync_marks')) {
            return;
        }

        $userid = $event->relateduserid;
        $courseid = $event->courseid;

        // Check if course is mapped and user has MRU mapping.
        $coursemap = $DB->get_record('local_mru_course_map', ['courseid' => $courseid]);
        $usermap = $DB->get_record('local_mru_user_map', ['userid' => $userid, 'verified' => 1]);

        if (!$coursemap || !$usermap) {
            return;
        }

        // Check for existing pending sync record for this user/course.
        $existing = $DB->get_record('local_mru_marks_sync', [
            'courseid' => $courseid,
            'userid'   => $userid,
            'sync_status' => 'pending',
        ]);

        if ($existing) {
            // Update the pending record timestamp.
            $existing->timecreated = time();
            $DB->update_record('local_mru_marks_sync', $existing);
        } else {
            // Create a new pending sync record.
            $record = new \stdClass();
            $record->courseid = $courseid;
            $record->userid = $userid;
            $record->mru_id = $usermap->mru_id;
            $record->mru_course_code = $coursemap->mru_course_code;
            $record->sync_direction = 'to_core';
            $record->sync_status = 'pending';
            $record->academic_year = $coursemap->academic_year;
            $record->semester = $coursemap->semester;
            $record->timecreated = time();
            $DB->insert_record('local_mru_marks_sync', $record);
        }
    }

    /**
     * Called when a user profile is updated.
     *
     * If the user has a verified MRU mapping, keep the verified email immutable.
     *
     * @param \core\event\user_updated $event
     */
    public static function user_updated(\core\event\user_updated $event): void {
        global $DB;

        if (self::$restoringlockedemail) {
            return;
        }

        $userid = (int) $event->objectid;
        if ($userid <= 0) {
            return;
        }

        $mapping = $DB->get_record('local_mru_user_map', ['userid' => $userid, 'verified' => 1]);
        if (!$mapping) {
            return;
        }

        $lockedemail = \local_mru_get_locked_verified_email($mapping, $userid);
        if (empty($lockedemail)) {
            return;
        }

        $user = $DB->get_record('user', ['id' => $userid], 'id, email');
        if (!$user) {
            return;
        }

        $current = strtolower(trim((string)$user->email));
        if ($current === $lockedemail) {
            return;
        }

        self::$restoringlockedemail = true;
        $DB->set_field('user', 'email', $lockedemail, ['id' => $userid]);
        self::$restoringlockedemail = false;
    }
}
