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
 * External functions for local_mru.
 *
 * Exposes MRU integration functions as Moodle web services
 * so they can be called via AJAX or external systems.
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mru\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;
use context_system;
use context_course;
use local_mru\student_manager;
use local_mru\marks_manager;
use local_mru\sync_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * External API for verifying a student.
 */
class verify_student extends external_api {

    /**
     * Describes the parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Moodle user ID'),
            'mru_id' => new external_value(PARAM_ALPHANUMEXT, 'MRU student number'),
        ]);
    }

    /**
     * Execute student verification.
     *
     * @param int $userid Moodle user ID.
     * @param string $mruid MRU student number.
     * @return array
     */
    public static function execute(int $userid, string $mruid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
            'mru_id' => $mruid,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/mru:verifystudents', $context);

        $manager = new student_manager();
        $result = $manager->verify_student($params['userid'], $params['mru_id']);

        return [
            'verified' => $result->verified,
            'message'  => $result->message,
            'data'     => $result->data ? json_encode($result->data) : '',
        ];
    }

    /**
     * Describes the return value.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'verified' => new external_value(PARAM_BOOL, 'Whether student is verified'),
            'message'  => new external_value(PARAM_TEXT, 'Status message'),
            'data'     => new external_value(PARAM_RAW, 'JSON student data from core system'),
        ]);
    }
}

/**
 * External API for syncing marks for a course.
 */
class sync_course_marks extends external_api {

    /**
     * Describes the parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Moodle course ID'),
        ]);
    }

    /**
     * Execute marks sync.
     *
     * @param int $courseid Moodle course ID.
     * @return array
     */
    public static function execute(int $courseid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
        ]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/mru:syncmarks', $context);

        $manager = new marks_manager();
        $result = $manager->sync_marks_to_core($params['courseid'], $USER->id);

        return [
            'success'   => $result->success,
            'processed' => $result->processed,
            'synced'    => $result->synced,
            'failed'    => $result->failed,
            'errors'    => implode('; ', $result->errors),
        ];
    }

    /**
     * Describes the return value.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'   => new external_value(PARAM_BOOL, 'Whether sync completed successfully'),
            'processed' => new external_value(PARAM_INT, 'Number of records processed'),
            'synced'    => new external_value(PARAM_INT, 'Number of records synced'),
            'failed'    => new external_value(PARAM_INT, 'Number of records failed'),
            'errors'    => new external_value(PARAM_RAW, 'Error messages'),
        ]);
    }
}

/**
 * External API for getting integration status.
 */
class get_status extends external_api {

    /**
     * Describes the parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Execute status retrieval.
     *
     * @return array
     */
    public static function execute(): array {
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/mru:viewreports', $context);

        $manager = new sync_manager();
        $status = $manager->get_status();

        return [
            'total_users_mapped'  => $status->total_users_mapped,
            'verified_students'   => $status->verified_students,
            'unverified_students' => $status->unverified_students,
            'mapped_courses'      => $status->mapped_courses,
            'pending_syncs'       => $status->pending_syncs,
            'failed_syncs'        => $status->failed_syncs,
        ];
    }

    /**
     * Describes the return value.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total_users_mapped'  => new external_value(PARAM_INT, 'Total mapped users'),
            'verified_students'   => new external_value(PARAM_INT, 'Verified students'),
            'unverified_students' => new external_value(PARAM_INT, 'Unverified students'),
            'mapped_courses'      => new external_value(PARAM_INT, 'Mapped courses'),
            'pending_syncs'       => new external_value(PARAM_INT, 'Pending sync operations'),
            'failed_syncs'        => new external_value(PARAM_INT, 'Failed sync operations'),
        ]);
    }
}
