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
 * Abstract base class for all local_mru database migrations.
 *
 * Each migration file must extend this class and implement up() and down().
 * Migrations are executed in chronological order based on their filename
 * timestamp prefix (YYYYMMDDHHMMSS_description.php).
 *
 * @package    local_mru
 * @copyright  2026 Mutesa I Royal University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mru\migration;

use xmldb_table;
use xmldb_field;
use xmldb_key;
use xmldb_index;

defined('MOODLE_INTERNAL') || die();

/**
 * Base migration class providing schema manipulation helpers.
 */
abstract class base_migration {

    /** @var \database_manager XMLDB manager. */
    protected \database_manager $dbman;

    /** @var \moodle_database Moodle DB instance. */
    protected \moodle_database $db;

    /**
     * Constructor.
     */
    public function __construct() {
        global $DB;
        $this->db = $DB;
        $this->dbman = $DB->get_manager();
    }

    /**
     * Apply this migration (forward).
     *
     * @return void
     */
    abstract public function up(): void;

    /**
     * Reverse this migration (rollback).
     *
     * @return void
     */
    abstract public function down(): void;

    /**
     * Human-readable description of what this migration does.
     *
     * @return string
     */
    abstract public function description(): string;

    // ---------------------------------------------------------------
    // Schema helper methods (wrap XMLDB for cleaner migration syntax).
    // ---------------------------------------------------------------

    /**
     * Check if a table exists.
     *
     * @param string $tablename Table name without prefix.
     * @return bool
     */
    protected function table_exists(string $tablename): bool {
        return $this->dbman->table_exists(new xmldb_table($tablename));
    }

    /**
     * Check if a field exists on a table.
     *
     * @param string $tablename Table name without prefix.
     * @param string $fieldname Field name.
     * @return bool
     */
    protected function field_exists(string $tablename, string $fieldname): bool {
        $table = new xmldb_table($tablename);
        $field = new xmldb_field($fieldname);
        return $this->dbman->field_exists($table, $field);
    }

    /**
     * Check if an index exists on a table.
     *
     * @param string $tablename Table name without prefix.
     * @param string $indexname Index name.
     * @param array $fields Fields in the index.
     * @param bool $unique Whether the index is unique.
     * @return bool
     */
    protected function index_exists(string $tablename, string $indexname, array $fields = [], bool $unique = false): bool {
        $table = new xmldb_table($tablename);
        $index = new xmldb_index($indexname, $unique ? XMLDB_INDEX_UNIQUE : XMLDB_INDEX_NOTUNIQUE, $fields);
        return $this->dbman->index_exists($table, $index);
    }

    /**
     * Create a new table.
     *
     * @param xmldb_table $table Fully configured XMLDB table.
     * @return void
     */
    protected function create_table(xmldb_table $table): void {
        if (!$this->dbman->table_exists($table)) {
            $this->dbman->create_table($table);
        }
    }

    /**
     * Drop a table if it exists.
     *
     * @param string $tablename Table name without prefix.
     * @return void
     */
    protected function drop_table(string $tablename): void {
        $table = new xmldb_table($tablename);
        if ($this->dbman->table_exists($table)) {
            $this->dbman->drop_table($table);
        }
    }

    /**
     * Add a field to an existing table.
     *
     * @param string $tablename Table name without prefix.
     * @param xmldb_field $field Configured field definition.
     * @return void
     */
    protected function add_field(string $tablename, xmldb_field $field): void {
        $table = new xmldb_table($tablename);
        if (!$this->dbman->field_exists($table, $field)) {
            $this->dbman->add_field($table, $field);
        }
    }

    /**
     * Drop a field from a table.
     *
     * @param string $tablename Table name without prefix.
     * @param string $fieldname Field name.
     * @return void
     */
    protected function drop_field(string $tablename, string $fieldname): void {
        $table = new xmldb_table($tablename);
        $field = new xmldb_field($fieldname);
        if ($this->dbman->field_exists($table, $field)) {
            $this->dbman->drop_field($table, $field);
        }
    }

    /**
     * Rename a field.
     *
     * @param string $tablename Table name without prefix.
     * @param string $oldname Current field name.
     * @param string $newname New field name.
     * @param xmldb_field $field Field definition with the NEW name set.
     * @return void
     */
    protected function rename_field(string $tablename, string $oldname, string $newname, xmldb_field $field): void {
        $table = new xmldb_table($tablename);
        $oldfield = new xmldb_field($oldname);
        if ($this->dbman->field_exists($table, $oldfield)) {
            $this->dbman->rename_field($table, $oldfield, $newname);
        }
    }

    /**
     * Change a field definition (type, length, default, etc.).
     *
     * @param string $tablename Table name without prefix.
     * @param xmldb_field $field Field with updated definition.
     * @return void
     */
    protected function change_field(string $tablename, xmldb_field $field): void {
        $table = new xmldb_table($tablename);
        if ($this->dbman->field_exists($table, $field)) {
            $this->dbman->change_field_type($table, $field);
        }
    }

    /**
     * Add an index.
     *
     * @param string $tablename Table name without prefix.
     * @param string $indexname Index name.
     * @param bool $unique Whether the index is unique.
     * @param array $fields Array of field names.
     * @return void
     */
    protected function add_index(string $tablename, string $indexname, bool $unique, array $fields): void {
        $table = new xmldb_table($tablename);
        $index = new xmldb_index($indexname, $unique ? XMLDB_INDEX_UNIQUE : XMLDB_INDEX_NOTUNIQUE, $fields);
        if (!$this->dbman->index_exists($table, $index)) {
            $this->dbman->add_index($table, $index);
        }
    }

    /**
     * Drop an index.
     *
     * @param string $tablename Table name without prefix.
     * @param string $indexname Index name.
     * @param array $fields Array of field names (required by XMLDB to identify the index).
     * @param bool $unique Whether the index is unique.
     * @return void
     */
    protected function drop_index(string $tablename, string $indexname, array $fields, bool $unique = false): void {
        $table = new xmldb_table($tablename);
        $index = new xmldb_index($indexname, $unique ? XMLDB_INDEX_UNIQUE : XMLDB_INDEX_NOTUNIQUE, $fields);
        if ($this->dbman->index_exists($table, $index)) {
            $this->dbman->drop_index($table, $index);
        }
    }

    /**
     * Add a key.
     *
     * @param string $tablename Table name without prefix.
     * @param xmldb_key $key Configured key definition.
     * @return void
     */
    protected function add_key(string $tablename, xmldb_key $key): void {
        $table = new xmldb_table($tablename);
        $this->dbman->add_key($table, $key);
    }

    /**
     * Drop a key.
     *
     * @param string $tablename Table name without prefix.
     * @param xmldb_key $key Key to drop.
     * @return void
     */
    protected function drop_key(string $tablename, xmldb_key $key): void {
        $table = new xmldb_table($tablename);
        $this->dbman->drop_key($table, $key);
    }

    /**
     * Execute raw SQL (use sparingly — for data migrations or non-XMLDB operations).
     *
     * @param string $sql SQL statement.
     * @param array|null $params Optional parameters.
     * @return void
     */
    protected function execute_sql(string $sql, ?array $params = null): void {
        $this->db->execute($sql, $params ?? []);
    }

    /**
     * Insert a record.
     *
     * @param string $tablename Table name without prefix.
     * @param object|array $data Data to insert.
     * @return int Inserted ID.
     */
    protected function insert_record(string $tablename, $data): int {
        return $this->db->insert_record($tablename, (object) $data);
    }

    /**
     * Count records matching conditions.
     *
     * @param string $tablename Table name without prefix.
     * @param array $conditions Key-value conditions.
     * @return int
     */
    protected function count_records(string $tablename, array $conditions = []): int {
        return $this->db->count_records($tablename, $conditions);
    }
}
