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

define('NO_OUTPUT_BUFFERING', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/adminlib.php');

$selfpath = '/local/dbgc/index.php';

use \local_dbgc\garbage_collector;

// This is an admin page.
admin_externalpage_setup('remove_garbage');

// Require site configuration capability.
require_capability('moodle/site:config', context_system::instance());

// Get the submitted params.
$schedule    = optional_param('schedule', 0, PARAM_BOOL);
$doitnow     = optional_param('doitnow', 0, PARAM_BOOL);
$confirm     = optional_param('confirm', 0, PARAM_BOOL);
$notneeded   = optional_param('notneeded', 0, PARAM_BOOL);
$tablename   = optional_param('tablename', '', PARAM_ALPHAEXT);
$keyname     = optional_param('keyname', '', PARAM_ALPHAEXT);

// Page settings.
$PAGE->set_context(context_system::instance());

// Grab the renderer.
$renderer = $PAGE->get_renderer('local_dbgc');

$doitreally = false;

if ($schedule || $doitnow) {
    // One of the two alternatives was called.
    if (!$confirm or !data_submitted() or !confirm_sesskey()) {
        $optionsyes = array(
            'confirm' => 1,
            'sesskey' => sesskey()
        );
        if ($schedule) {
            $optionsyes['schedule'] = 1;
            $confirm = new lang_string('confirm_schedule', 'local_dbgc');
        } else if ($doitnow) {
            $optionsyes['doitnow'] = 1;
            $optionsyes['tablename'] = $tablename;
            $optionsyes['keyname'] = $keyname;
            $confirm = new lang_string('confirm_doit', 'local_dbgc');
        }
        echo $OUTPUT->header();
        echo $OUTPUT->heading(new lang_string('settingspage', 'local_dbgc'));
        echo $OUTPUT->confirm($confirm, new moodle_url('/local/dbgc/index.php', $optionsyes), new moodle_url($selfpath));
        echo $OUTPUT->footer();
        die;
    }
    if ($schedule) {
        // Schedule the adhoc task; will be run at next cron.
        $collector = new \local_dbgc\task\garbage_collect_db();
        \core\task\manager::queue_adhoc_task($collector);
        redirect(new moodle_url($selfpath), new lang_string('scheduled_correctly', 'local_dbgc'));
    } else if ($doitnow) {
        $doitreally = true;
        raise_memory_limit(MEMORY_EXTRA);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(new lang_string('settingspage', 'local_dbgc'));

if ($notneeded) {
    echo $renderer->admin_page([]);
} else {
    $progress = new \core\progress\display();

    $gc = new garbage_collector($progress);

    if ($doitreally) {
        echo "<pre>";
        $gc->cleanup($tablename, $keyname);
        echo "</pre>";
        echo $renderer->finished_cleanup($tablename, $keyname);
    } else {
        $report = $gc->get_report();
        if (empty($report)) {
            redirect(new moodle_url($selfpath, ['notneeded' => 1]));
        }
        echo $renderer->admin_page($report);
    }
}
echo $OUTPUT->footer();