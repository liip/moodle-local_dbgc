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
 * Database Garbage Collector removal launcher
 *
 * @package local_dbgc
 * @copyright Liip AG <https://www.liip.ch/>
 * @author Didier Raboud <didier.raboud@liip.ch>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/adminlib.php');

// This is an admin page
admin_externalpage_setup('remove_garbage');

// Require site configuration capability
require_capability('moodle/site:config', context_system::instance());

// Get the submitted params
$schedule    = optional_param('schedule', 0, PARAM_BOOL);
$doit        = optional_param('doit', 0, PARAM_BOOL);

// Page settings
$PAGE->set_context(context_system::instance());

// Grab the renderer
$renderer = $PAGE->get_renderer('local_dbgc');

// Display the page
echo $OUTPUT->header();
echo $OUTPUT->heading(new lang_string('settingspage', 'local_dbgc'));
echo $renderer->admin_page();
echo $OUTPUT->footer();