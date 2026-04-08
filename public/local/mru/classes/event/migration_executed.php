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
 * Event triggered when a migration action is executed.
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mru\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Migration executed event — logged for every migrate/rollback/reset action.
 */
class migration_executed extends \core\event\base {

    /**
     * Initialise the event.
     */
    protected function init() {
        $this->data['crud'] = 'u'; // Update.
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Get the event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event:migration_executed', 'local_mru');
    }

    /**
     * Get the event description.
     *
     * @return string
     */
    public function get_description() {
        $other = $this->other;
        $action = $other['action'] ?? 'unknown';
        $count = $other['count'] ?? 0;
        $errors = $other['errors'] ?? 0;

        $userid = $this->userid;
        $status = $errors > 0 ? "with {$errors} error(s)" : "successfully";

        return "User '{$userid}' executed migration action '{$action}': " .
               "{$count} migration(s) processed {$status}.";
    }

    /**
     * Get URL related to the event.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/mru/migrations.php');
    }
}
