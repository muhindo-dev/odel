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
 * Database connection manager for the MRU core database.
 *
 * Provides a managed connection to the external mru_main database
 * separate from Moodle's $DB. Used for direct data import operations.
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mru;

use moodle_exception;
use mysqli;

defined('MOODLE_INTERNAL') || die();

/**
 * Manages connections to the MRU core MySQL database.
 */
class db_manager {

    /** @var mysqli|null Active database connection. */
    private ?mysqli $conn = null;

    /**
     * Get a connection to the MRU database.
     *
     * @return mysqli Active connection.
     * @throws moodle_exception If connection fails.
     */
    public function get_connection(): mysqli {
        if ($this->conn !== null && $this->conn->ping()) {
            return $this->conn;
        }

        $host   = get_config('local_mru', 'db_host') ?: 'localhost';
        $port   = (int) (get_config('local_mru', 'db_port') ?: 3306);
        $dbname = get_config('local_mru', 'db_name') ?: 'mru_main';
        $user   = get_config('local_mru', 'db_user');
        $pass   = get_config('local_mru', 'db_pass');
        $socket = get_config('local_mru', 'db_socket') ?: null;

        if (empty($user)) {
            throw new moodle_exception('error:db_not_configured', 'local_mru');
        }

        $this->conn = new mysqli($host, $user, $pass, $dbname, $port, $socket);

        if ($this->conn->connect_error) {
            $this->conn = null;
            throw new moodle_exception('error:db_connect_failed', 'local_mru');
        }

        $this->conn->set_charset('utf8mb4');
        return $this->conn;
    }

    /**
     * Execute a parameterised query against the MRU database.
     *
     * @param string $sql SQL with ? placeholders.
     * @param string $types mysqli bind types string (e.g. "ssi").
     * @param array $params Parameter values.
     * @return array Result rows as associative arrays.
     * @throws moodle_exception On failure.
     */
    public function query(string $sql, string $types = '', array $params = []): array {
        $conn = $this->get_connection();

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new moodle_exception('error:db_query_failed', 'local_mru', '', $conn->error);
        }

        if (!empty($types) && !empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new moodle_exception('error:db_query_failed', 'local_mru', '', $error);
        }

        $result = $stmt->get_result();
        $rows = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            $result->free();
        }

        $stmt->close();
        return $rows;
    }

    /**
     * Test the database connection.
     *
     * @return bool True if connection succeeds.
     */
    public function test_connection(): bool {
        try {
            $conn = $this->get_connection();
            return $conn->ping();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Close the database connection.
     */
    public function close(): void {
        if ($this->conn !== null) {
            $this->conn->close();
            $this->conn = null;
        }
    }

    /**
     * Destructor ensures connection is closed.
     */
    public function __destruct() {
        $this->close();
    }
}
