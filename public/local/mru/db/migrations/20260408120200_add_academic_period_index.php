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
 * Migration: Add composite index on marks_sync for academic year queries.
 *
 * Improves query performance for reports filtering by academic year + semester.
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mru\migration;

defined('MOODLE_INTERNAL') || die();

class _20260408120200_add_academic_period_index extends base_migration {

    public function description(): string {
        return 'Add composite index on marks_sync (academic_year, semester) for faster period queries';
    }

    public function up(): void {
        $this->add_index('local_mru_marks_sync', 'ix_academic_period', false, ['academic_year', 'semester']);
    }

    public function down(): void {
        $this->drop_index('local_mru_marks_sync', 'ix_academic_period', ['academic_year', 'semester']);
    }
}
