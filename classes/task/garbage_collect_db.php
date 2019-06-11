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

use local_dbgc\garbage_collector;

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
        $gc = new garbage_collector();
        $gc->cleanup();
    }
}