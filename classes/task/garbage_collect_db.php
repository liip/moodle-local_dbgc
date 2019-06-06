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
 * Adhoc task to garbage collect the Moodle DB
 *
 * @package   local_dbgc
 * @copyright Liip AG <https://www.liip.ch/>
 * @author    Didier Raboud <didier.raboud@liip.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dbgc\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Class handling garbage collecting the database.
 *
 * @package    local_dbgc
 * @copyright  Liip AG <https://www.liip.ch/>
 * @author     Didier Raboud <didier.raboud@liip.ch>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class garbage_collect_db extends \core\task\adhoc_task {
    /**
     * Run the migration task.
     */
    public function execute() {
        global $CFG, $DB;

        // Make sure our backup directory is ready.
        $backupdir = $CFG->dataroot . '/dbgc/';
        make_writable_directory($backupdir, false);
        $backupfile = $backupdir . sprintf('/dbgc_backup_%s.sql', date('Ymd_His'));

        // Get the complete currently-setup XML schema for that instance.
        $dbman = $DB->get_manager();
        $xmlschema = $dbman->get_install_xml_schema();

        $cleanedup = [];
        // Run through all tables, all keys, to check if we have mismatched entries.
        foreach ($xmlschema->getTables() as $table) {
            foreach ($table->getKeys() as $key) {
                if (in_array($key->getType(), [XMLDB_KEY_FOREIGN, XMLDB_KEY_FOREIGN_UNIQUE]) ) {
                    // We have a foreign key for another table.

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
                    $records = $DB->get_records_sql($sql);

                    if (count($records) > 0) {
                        mtrace(
                            sprintf(
                                '%s has %d records pointing to inexistant counterparts in table %s',
                                $tablename,
                                count($records),
                                $reftablename
                            )
                        );

                        mtrace(' → Create backup');
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
                            $tablename,
                            date('Ymd_His')
                        );
                        $insertsql .= sprintf("INSERT INTO %s%s (%s) VALUES \n",
                            $CFG->prefix,
                            $tablename,
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
                        file_put_contents($backupfile, $insertsql, FILE_APPEND);

                        mtrace(' → Delete');
                        // Now delete all these entries.
                        list($insql, $inparams) = $DB->get_in_or_equal(array_keys($records), SQL_PARAMS_NAMED);
                        $DB->delete_records_select($tablename, "id $insql", $inparams);
                        $cleanedup[] = count($records);
                    }
                }
            }
        }

        if (count($cleanedup) > 0) {
            mtrace(sprintf('%d tables cleaned up from %d orphaned records', count($cleanedup), array_sum($cleanedup)));
            mtrace(sprintf('A backup is to be found at : %s', $backupfile));
        }
    }
}