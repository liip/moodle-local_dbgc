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
 * Renderer class for local_dbgc
 *
 * @package   local_dbgc
 * @copyright Liip AG <https://www.liip.ch/>
 * @author    Didier Raboud <didier.raboud@liip.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_dbgc\output;

defined('MOODLE_INTERNAL') || die;

use moodle_exception;
use plugin_renderer_base;

/**
 * Renderer class for local_dbgc
 *
 * @package   local_dbgc
 * @copyright Liip AG <https://www.liip.ch/>
 * @author    Didier Raboud <didier.raboud@liip.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {
    /**
     * Render the admin page
     *
     * @param array $report The gc report as spit by \local_dbgc\garbage_collector.
     *
     * @return string html for the page
     * @throws moodle_exception
     */
    public function admin_page(array $report = []) {
        $params = [];
        $params['notneeded'] = (count($report) == 0);
        $params['report'] = [];
        $totalrecords = 0;
        foreach ($report as $tablename => $entry) {
            $totalrecords += $entry["records"];
            $a = new \stdClass();
            $a->tablename = $tablename;
            $a->keyname = $entry["keyname"];
            $a->doitnow = 1;
            $params['report'][] = $entry + [
                'name' => $tablename,
                'fields_list' => implode(',', $entry['fields']),
                'reffields_list' => implode(',', $entry['reffields']),
                'cleanup_link' => new \moodle_url('/local/dbgc/index.php', (array)($a)),
                'cleanup_message' => new \lang_string('cleanup_partial', 'local_dbgc', $a)
            ];
        }

        $params['message'] = new \lang_string('needed', 'local_dbgc', $totalrecords);
        return parent::render_from_template('local_dbgc/admin_page', $params);
    }

    /**
     * Render the end of the cleanup 'doitnow' page
     *
     * @param string $tablename The table that was just cleaned up
     * @param string $keyname   The foreign key name that was just cleaned up
     *
     * @return string html for the page
     * @throws moodle_exception
     */
    public function finished_cleanup(string $tablename = '', string $keyname = '') {
        $params = [];
        if (!empty($tablename) && !empty($keyname)) {
            $a = new \stdClass();
            $a->tablename = $tablename;
            $a->keyname = $keyname;
            $params["message"] = new \lang_string('finished_cleanup_partial', 'local_dbgc', $a);
        } else {
            $params["message"] = new \lang_string('finished_cleanup_complete', 'local_dbgc');
        }
        return parent::render_from_template('local_dbgc/finished_cleanup', $params);
    }
}