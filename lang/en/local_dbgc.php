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
 * Database Garbage Collector translations
 *
 * @package local_dbgc
 * @copyright Liip AG <https://www.liip.ch/>
 * @author Didier Raboud <didier.raboud@liip.ch>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = "Database Garbage Collector";
$string['settingspage'] = "Cleanup DB from cruft";
$string['schedule'] = "Schedule garbage collection";
$string['confirm_schedule'] = "The database garbage collection will be scheduled and run at a later point by the instance cron. Is this what you really want?";
$string['scheduled_correctly'] = "The garbage collection ad'hoc task has been correctly scheduled. Watch out the cron logs!";
$string['notneeded'] = 'There are no orphaned records in the database, all good!';
$string['needed'] = 'There are {$a} orphaned records in the database; they should be removed!';
$string['reporttitle'] = 'Detailed report';
$string['n_orphaned_records'] = '{$a} orphaned records';

$string['doitnow'] = "Proceed with garbage collection now";
$string['confirm_doit'] = "The database garbage collection will be run NOW within the webserver process. Is this what you really want?";
$string['donenow_correctly'] = "The garbage collection has been correctly executed.";
