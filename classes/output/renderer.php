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
     * @return string html for the page
     * @throws moodle_exception
     */
    public function admin_page() {
        return parent::render_from_template('local_dbgc/admin_page', new \stdClass());
    }
}