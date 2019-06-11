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
     * @var int Whether to get the count, all the IDs or all the data.
     */
    const REPORT_GET_COUNT = 1;
    const REPORT_GET_IDS = 2;
    const REPORT_GET_DATA = 3;

    /**
     * @var int How many records to backup and delete at a time.
     */
    const CLEANUP_STEP = 1024*32;

    /**
     * @var string $_backupfilepath Where the garbage will be backed-up.
     */
    private $_backupfilepath;

    /**
     * @var \xmldb_structure $_xmlschema The complete XMLDB Schema.
     */
    private $_xmlschema;

    /**
     * @var \core\progress\base $_progress Progress object.
     */
    private $_progress;

    /**
     * Initializes what the GC needs.
     *
     * @param \core\progress\base $progress Progress handler
     *
     * @return void
     */
    public function __construct(\core\progress\base $progress = null) {
        global $DB;

        $this->_backupfilepath = $this->get_backup_file_path();

        // Get the complete currently-setup XML schema for that instance.
        $dbman = $DB->get_manager();
        $this->_xmlschema = $dbman->get_install_xml_schema();
        if (is_null($progress)) {
            $this->_progress = new \core\progress\none();
        } else {
            $this->_progress = $progress;
        }
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
        mtrace(sprintf('Starting cleanup; backup will be found at %s', $this->_backupfilepath));
        $tuples = $this->_get_all_foreign_key_tuples();

        $this->_progress->start_progress('dbgc_report', count($tuples));

        foreach ($tuples as $tupleid => $tuple) {
            $table = $tuple->table;
            $key = $tuple->key;
            // We have a foreign key for another table.
            // Get at most CLEANUP_STEP orphaned records for that table/key pair.
            while (
                    count(
                        $records = $this->_get_orphaned_records(
                            $table,
                            $key,
                            self::REPORT_GET_DATA,
                            self::CLEANUP_STEP
                        )
                    )
                    > 0
                ) {

                mtrace(
                    sprintf(
                        '%s still has %d+ records pointing to inexistant counterparts in table %s',
                        $table->getName(),
                        count($records),
                        $key->getRefTable()
                    )
                );

                mtrace(' → Create backup');
                $this->_backup_records($table, $key, $records);
                mtrace(' → Delete');
                $this->_delete_records($table, $records);
                $cleanedup[] = count($records);
            }
            $this->_progress->progress($tupleid);
        }

        if (count($cleanedup) > 0) {
            mtrace(sprintf('%d tables cleaned up from %d orphaned records', count($cleanedup), array_sum($cleanedup)));
            mtrace(sprintf('A backup is to be found at : %s', $this->_backupfilepath));
        }

        $this->_progress->end_progress();
    }


    /**
     * Get a report of how many orphaned entries exist in the concerned tables
     *
     * @return array of all the concerned records
     */
    public function get_report() {
        $report = [];
        $tuples = $this->_get_all_foreign_key_tuples();

        $this->_progress->start_progress('dbgc_report', count($tuples));
        foreach ($tuples as $tupleid => $tuple) {
            $table = $tuple->table;
            $key = $tuple->key;
            // We have a foreign key for another table.
            // Get count of orphaned records for that table/key pair.
            $records = $this->_get_orphaned_records($table, $key, self::REPORT_GET_COUNT);

            if ($records > 0) {
                $report[$table->getName()] = [
                    "fields" => $key->getFields(),
                    "reftablename" => $key->getRefTable(),
                    "reffields" => $key->getRefFields(),
                    "records" => $records
                ];
            }

            $this->_progress->progress($tupleid);
        }

        $this->_progress->end_progress();
        return $report;
    }

    /**
     * Get the full list of foreign key table/key tuples
     *
     * @return array of objects containing only table and key.
     */
    private function _get_all_foreign_key_tuples() {
        static $tuples = [];

        $tuples = [];
        // Run through all tables, all keys, to check if we have mismatched entries.
        foreach ($this->_xmlschema->getTables() as $table) {
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
     * @param \xmldb_table $table      source table object
     * @param \xmldb_key   $key        foreign key object
     * @param int          $whattoget  count, ids, or all
     * @param int          $maxrecords How many records to get maximally
     *
     * @return array of fieldset objets
     */
    private function _get_orphaned_records(
        \xmldb_table $table,
        \xmldb_key $key,
        int $whattoget = self::REPORT_GET_COUNT,
        int $maxrecords = -1
    ) {
        global $DB;

        $tablename = $table->getName();
        $tablefields = $key->getFields();
        $reftablename = $key->getRefTable();
        $reffields = $key->getRefFields();

        switch ($whattoget) {
        case self::REPORT_GET_COUNT:
        default:
            $selected = 'COUNT(t.id)';
            break;
        case self::REPORT_GET_IDS:
            $selected = 't.id';
            break;
        case self::REPORT_GET_DATA:
            $selected = 't.*';
            break;
        }

        // Build SQL to find the orphaned records.
        $sql = sprintf("SELECT %s
                        FROM {%s} t
                LEFT OUTER JOIN {%s} r",
            $selected,
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

        if ($maxrecords > 0) {
            $sql .= ' LIMIT '.$maxrecords;
        }

        switch ($whattoget) {
        case self::REPORT_GET_COUNT:
        default:
            return $DB->count_records_sql($sql);
        case self::REPORT_GET_IDS:
        case self::REPORT_GET_DATA:
            return $DB->get_records_sql($sql);
        }
    }


    /**
     * Given a table and a set of records; backup these in the designated backup file.
     *
     * @param \xmldb_table $table   source table object
     * @param \xmldb_key   $key     foreign key object
     * @param array        $records of fieldset objets
     *
     * @return void
     */
    private function _backup_records(\xmldb_table $table, \xmldb_key $key, array $records) {
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
            "-- Backup of %s entries deleted on %s\n-- %s (%s) → %s (%s)\n",
            $table->getName(),
            date('Ymd_His'),
            $table->getName(),
            implode(',', $key->getFields()),
            $key->getRefTable(),
            implode(',', $key->getRefFields())
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
        $insertsql .= ";\n";
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