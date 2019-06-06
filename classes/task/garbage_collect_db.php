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
        global $DB;
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
                    $sql = sprintf("SELECT t.id
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

                    $records = $DB->get_records_sql_menu($sql);
                            
                    if (count($records) > 0) {
                        mtrace(sprintf('%s has %d records pointing to inexistant counterparts in table %s, delete them now.', $tablename, count($records), $reftablename));
                        list($insql, $inparams) = $DB->get_in_or_equal(array_keys($records), SQL_PARAMS_NAMED);
                        $DB->delete_records_select($tablename, "id $insql", $inparams);

                        $cleanedup[] = $records;
                    }
                }
            }
        }

        if (count($cleanedup) > 0) {
            mtrace(sprintf('%d tables cleaned up from %d orphaned records', count($cleanedup), sum($cleanedup)));
        }
    }
}