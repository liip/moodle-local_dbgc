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
 * Class handling garbage collecting the database.
 *
 * @package   local_dbgc
 * @copyright Liip AG <https://www.liip.ch/>
 * @author    Didier Raboud <didier.raboud@liip.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dbgc;

defined('MOODLE_INTERNAL') || die();

use xmldb;

/**
 * Class handling garbage collecting the database.
 *
 * @package    local_dbgc
 * @copyright  Liip AG <https://www.liip.ch/>
 * @author     Didier Raboud <didier.raboud@liip.ch>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class garbage_collector {

    /**
     * Where the garbage will be backed-up.
     *
     * @return void
     */
    private $_backupfilepath;

    /**
     * Initializes what the GC needs.
     *
     * @return void
     */
    public function __construct() {
        $this->_backupfilepath = $this->get_backup_file_path();
    }

    /**
     * Get us a backup file path.
     *
     * @return string
     */
    private function get_backup_file_path() {
        global $CFG;

        // Make sure our backup directory is ready.
        $backupdir = $CFG->dataroot . '/dbgc/';
        make_writable_directory($backupdir, false);
        return $backupdir . sprintf('dbgc_backup_%s.sql', date('Ymd_His'));
    }

    /**
     * Run the actual DB cleanup.
     *
     * @return void
     */
    public function cleanup() {
        $cleanedup = [];
        foreach ($this->_get_all_foreign_key_tuples() as $tuple) {
            $table = $tuple->table;
            $key = $tuple->key;
            // We have a foreign key for another table.
            // Get orphaned records for that table/key pair.
            $records = $this->_get_orphaned_records($table, $key);
            $nrecords = count($records);

            if ($nrecords > 0) {
                mtrace(
                    sprintf(
                        '%s has %d records pointing to inexistant counterparts in table %s',
                        $table->getName(),
                        $nrecords,
                        $key->getRefTable()
                    )
                );

                mtrace(' → Create backup');
                $this->_backup_records($table, $records);
                mtrace(' → Delete');
                $this->_delete_records($table, $records);
                $cleanedup[] = $nrecords;
            }
        }

        if (count($cleanedup) > 0) {
            mtrace(sprintf('%d tables cleaned up from %d orphaned records', count($cleanedup), array_sum($cleanedup)));
            mtrace(sprintf('A backup is to be found at : %s', $this->_backupfilepath));
        }
    }


    /**
     * Get a report of how many orphaned entries exist in the concerned tables
     *
     * @return array of all the concerned records
     */
    public function report() {
        $report = [];
        foreach ($this->_get_all_foreign_key_tuples() as $tuple) {
            $table = $tuple->table;
            $key = $tuple->key;
            // We have a foreign key for another table.
            // Get orphaned records for that table/key pair.
            $records = $this->_get_orphaned_records($table, $key);
            $nrecords = count($records);

            if ($nrecords > 0) {
                $report[$table->getName()] = $records;
            }
        }
        return $report;
    }

    /**
     * Get the full list of foreign key table/key tuples
     *
     * @return array of objects containing only table and key.
     */
    private function _get_all_foreign_key_tuples() {
        static $tuples = [];

        global $DB;

        // Get the complete currently-setup XML schema for that instance.
        $dbman = $DB->get_manager();
        $xmlschema = $dbman->get_install_xml_schema();

        $tuples = [];
        // Run through all tables, all keys, to check if we have mismatched entries.
        foreach ($xmlschema->getTables() as $table) {
            foreach ($table->getKeys() as $key) {
                if (in_array($key->getType(), [XMLDB_KEY_FOREIGN, XMLDB_KEY_FOREIGN_UNIQUE]) ) {
                    $tuple = new \stdClass();
                    $tuple->table = $table;
                    $tuple->key = $key;
                    $tuples[] = $tuple;
                }
            }
        }
        return $tuples;
    }

    /**
     * Given a table and a key, get orphaned records for these.
     *
     * @param \xmldb_table $table source table object
     * @param \xmldb_key   $key   foreign key object
     *
     * @return array of fieldset objets
     */
    private function _get_orphaned_records(\xmldb_table $table, \xmldb_key $key) {
        global $DB;

        $tablename = $table->getName();
        $tablefields = $key->getFields();
        $reftablename = $key->getRefTable();
        $reffields = $key->getRefFields();

        // Build SQL to find the orphaned records.
        $sql = sprintf("SELECT t.*
                        FROM {%s} t
                LEFT OUTER JOIN {%s} r",
            $tablename,
            $reftablename
        );
        // For each field pair,:
        // - bind the LEFT OUTER JOIN to match on the field pairs;
        // - restrict the match to when the joined table has no counterparts
        // - restrict the match to non-zero entries; as these carry a special meaning.
        $onfields = [];
        $conditions = [];
        foreach ($tablefields as $i => $tablefield) {
            $onfields[] = sprintf("t.%s = r.%s", $tablefield, $reffields[$i]);
            $conditions[] = sprintf("r.%s IS NULL", $reffields[$i]);
            $conditions[] = sprintf("t.%s != 0", $tablefield);
        }
        $sql .= ' ON ' . implode(' AND ', $onfields);
        $sql .= ' WHERE ' . implode(' AND ', $conditions);

        // Get these orphaned records.
        return $DB->get_records_sql($sql);
    }


    /**
     * Given a table and a set of records; backup these in the designated backup file.
     *
     * @param \xmldb_table $table   source table object
     * @param array        $records of fieldset objets
     *
     * @return void
     */
    private function _backup_records(\xmldb_table $table, array $records) {
        global $CFG;
        // Create a backup INSERT for the lines-to-be-deleted.
        $fieldnames = [];
        foreach ($table->getFields() as $field) {
            $name = $field->getName();
            if ($name != 'id') {
                $fieldnames[] = $field->getName();
            }
        }
        $insertsql = sprintf(
            "-- Backup of the %s entries deleted on %s\n",
            $table->getName(),
            date('Ymd_His')
        );
        $insertsql .= sprintf("INSERT INTO %s%s (%s) VALUES \n",
            $CFG->prefix,
            $table->getName(),
            implode(',', $fieldnames)
        );
        $recordsqls = [];
        foreach ($records as $record) {
            $recordstruct = [];
            foreach ($fieldnames as $fieldname) {
                $recordstruct[] = sprintf("'%s'", addslashes($record->{$fieldname}));
            }
            $recordsqls[] = sprintf('  (%s)', implode(',', $recordstruct));
        }
        $insertsql .= implode(",\n", $recordsqls);
        $insertsql .= ';';
        // Write the INSERT lines in the backup file.
        return file_put_contents($this->_backupfilepath, $insertsql, FILE_APPEND);
    }

    /**
     * Given a table and a set of records; delete these.
     *
     * @param \xmldb_table $table   source table object
     * @param array        $records of fieldset objets
     *
     * @return void
     */
    private function _delete_records(\xmldb_table $table, array $records) {
        global $DB;

        // Delete all these entries.
        list($insql, $inparams) = $DB->get_in_or_equal(array_keys($records), SQL_PARAMS_NAMED);
        return $DB->delete_records_select($table->getName(), "id $insql", $inparams);
    }
}