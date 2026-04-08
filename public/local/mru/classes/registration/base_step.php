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
 * Base class for registration wizard steps.
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base_step {

    /** @var \local_mru\registration_manager */
    protected $regmanager;

    /** @var \stdClass Registration session record. */
    protected $session;

    /** @var int Step number. */
    abstract public function get_step_number(): int;

    /** @var string Mustache template name (without component prefix). */
    abstract public function get_template(): string;

    /**
     * Constructor.
     *
     * @param \local_mru\registration_manager $regmanager
     * @param \stdClass $session
     */
    public function __construct(\local_mru\registration_manager $regmanager, \stdClass $session) {
        $this->regmanager = $regmanager;
        $this->session = $session;
    }

    /**
     * Handle POST actions for this step.
     *
     * @param string $action The form action name.
     * @return void Should redirect on success/failure.
     */
    abstract public function handle_action(string $action): void;

    /**
     * Build template data for this step.
     *
     * @return array
     */
    abstract public function get_template_data(): array;

    /**
     * Helper: redirect back to the wizard.
     */
    protected function redirect_to_wizard(string $message = '', ?string $type = null): void {
        redirect(new \moodle_url('/local/mru/register.php'), $message, null, $type);
    }
}
