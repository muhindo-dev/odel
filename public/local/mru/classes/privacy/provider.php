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
 * Privacy provider for local_mru.
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mru\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use context_system;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy subsystem implementation for local_mru.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Describe the types of data stored.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_mru_user_map', [
            'userid'      => 'privacy:metadata:local_mru_user_map:userid',
            'mru_id'      => 'privacy:metadata:local_mru_user_map:mru_id',
            'verified'    => 'privacy:metadata:local_mru_user_map:verified',
        ], 'privacy:metadata:local_mru_user_map');

        $collection->add_database_table('local_mru_marks_sync', [
            'userid'       => 'privacy:metadata:local_mru_marks_sync:userid',
            'moodle_grade' => 'privacy:metadata:local_mru_marks_sync:moodle_grade',
        ], 'privacy:metadata:local_mru_marks_sync');

        $collection->add_external_location_link('core_system', [
            'student_id' => 'privacy:metadata:core_system:student_id',
            'marks'      => 'privacy:metadata:core_system:marks',
        ], 'privacy:metadata:core_system');

        return $collection;
    }

    /**
     * Get contexts for a user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                FROM {local_mru_user_map} um
                JOIN {context} ctx ON ctx.instanceid = 0 AND ctx.contextlevel = :contextlevel
                WHERE um.userid = :userid";

        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_SYSTEM,
            'userid'       => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Get users in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        $sql = "SELECT userid FROM {local_mru_user_map}";
        $userlist->add_from_sql('userid', $sql, []);

        $sql = "SELECT userid FROM {local_mru_marks_sync}";
        $userlist->add_from_sql('userid', $sql, []);
    }

    /**
     * Export user data.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        // Export user mapping.
        $mapping = $DB->get_record('local_mru_user_map', ['userid' => $userid]);
        if ($mapping) {
            writer::with_context(context_system::instance())->export_data(
                [get_string('pluginname', 'local_mru'), 'user_mapping'],
                (object) [
                    'mru_id'    => $mapping->mru_id,
                    'user_type' => $mapping->user_type,
                    'verified'  => $mapping->verified ? 'Yes' : 'No',
                ]
            );
        }

        // Export marks sync records.
        $syncs = $DB->get_records('local_mru_marks_sync', ['userid' => $userid]);
        if ($syncs) {
            $exportdata = [];
            foreach ($syncs as $sync) {
                $exportdata[] = (object) [
                    'course_code'  => $sync->mru_course_code,
                    'moodle_grade' => $sync->moodle_grade,
                    'sync_status'  => $sync->sync_status,
                    'time'         => userdate($sync->timecreated),
                ];
            }
            writer::with_context(context_system::instance())->export_data(
                [get_string('pluginname', 'local_mru'), 'marks_sync'],
                (object) ['syncs' => $exportdata]
            );
        }
    }

    /**
     * Delete all data for all users in context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        $DB->delete_records('local_mru_user_map');
        $DB->delete_records('local_mru_marks_sync');
    }

    /**
     * Delete data for a user.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $DB->delete_records('local_mru_user_map', ['userid' => $userid]);
        $DB->delete_records('local_mru_marks_sync', ['userid' => $userid]);
    }

    /**
     * Delete data for users in context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        list($insql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_mru_user_map', "userid {$insql}", $params);
        $DB->delete_records_select('local_mru_marks_sync', "userid {$insql}", $params);
    }
}
