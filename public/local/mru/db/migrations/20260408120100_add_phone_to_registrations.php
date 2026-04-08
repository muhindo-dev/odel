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
 * Migration: Add phone column to registrations table.
 *
 * Adds a phone number field to track student phone during registration.
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mru\migration;

use xmldb_field;

defined('MOODLE_INTERNAL') || die();

class _20260408120100_add_phone_to_registrations extends base_migration {

    public function description(): string {
        return 'Add phone column to local_mru_registrations for student contact number';
    }

    public function up(): void {
        $field = new xmldb_field('phone', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'lastname');
        $this->add_field('local_mru_registrations', $field);
    }

    public function down(): void {
        $this->drop_field('local_mru_registrations', 'phone');
    }
}
