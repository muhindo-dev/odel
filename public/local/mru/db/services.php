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
 * Web services definition for local_mru.
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    'local_mru_verify_student' => [
        'classname'     => 'local_mru\external\verify_student',
        'description'   => 'Verify a student against the MRU core system.',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'local/mru:verifystudents',
    ],

    'local_mru_sync_course_marks' => [
        'classname'     => 'local_mru\external\sync_course_marks',
        'description'   => 'Sync grades/marks for a course to the MRU core system.',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'local/mru:syncmarks',
    ],

    'local_mru_get_status' => [
        'classname'     => 'local_mru\external\get_status',
        'description'   => 'Get the MRU integration status summary.',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/mru:viewreports',
    ],
];

$services = [
    'MRU Integration Services' => [
        'functions'       => [
            'local_mru_verify_student',
            'local_mru_sync_course_marks',
            'local_mru_get_status',
        ],
        'restrictedusers' => 0,
        'enabled'         => 1,
        'shortname'       => 'local_mru',
    ],
];
