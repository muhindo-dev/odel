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

namespace local_mru\registration;

/**
 * Step 1: Introduction — explains the registration process and requirements.
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class step1 extends base_step {

    public function get_step_number(): int {
        return 1;
    }

    public function get_template(): string {
        return 'local_mru/register_step1';
    }

    public function handle_action(string $action): void {
        if ($action === 'startverify') {
            $this->regmanager->advance_step($this->session, 2);
            $this->redirect_to_wizard();
        }
    }

    public function get_template_data(): array {
        return [];
    }
}
