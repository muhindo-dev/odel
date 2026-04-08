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
 * Event observers for local_mru.
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [

    // When a user logs in, check verification status if configured.
    [
        'eventname' => '\core\event\user_loggedin',
        'callback'  => 'local_mru\event\observer::user_loggedin',
    ],

    // When a user is enrolled in a course, verify if configured.
    [
        'eventname' => '\core\event\user_enrolment_created',
        'callback'  => 'local_mru\event\observer::user_enrolled',
    ],

    // When grades are updated, flag for sync.
    [
        'eventname' => '\core\event\user_graded',
        'callback'  => 'local_mru\event\observer::user_graded',
    ],
];
