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
 * Admin tool "APCu management" - Upgrade file
 *
 * @package    tool_apcu
 * @copyright  2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/'.$CFG->admin.'/tool/apcu/locallib.php');

/**
 * Function to upgrade tool_apcu.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool result
 */
function xmldb_tool_apcu_upgrade($oldversion) {
    global $OUTPUT;

    // Verify the existence of the APCu GUI file.
    // This is done everytime the plugin is upgraded, i.e. there is no oldversion check and no savepoint set.

    // If the APCu tool does not exist in MOODLEDATA.
    if (tool_apcu_verify_guidrop_file() != true) {

        // Try to re-download and store the APCu management GUI file.
        $guidropsuccessful = tool_apcu_do_guidrop();

        // Compose message.
        $messagepaths = ['source' => tool_apcu_get_guidrop_url(), 'target' => tool_apcu_get_guidrop_path()];
        $message = '<p>'.get_string('guidropupgradecheckfail', 'tool_apcu', $messagepaths).'</p>';
        if ($guidropsuccessful == true) {
            $message .= '<p>'.get_string('guidropsuccess', 'tool_apcu', $messagepaths).'</p>';
        } else {
            $message .= '<p>'.get_string('guidroperror', 'tool_apcu', $messagepaths).'</p>';
        }

        // Output message.
        if ($guidropsuccessful == true) {
            $notification = new \core\output\notification($message, \core\output\notification::NOTIFY_SUCCESS);
            $notification->set_show_closebutton(false);
            echo $OUTPUT->render($notification);
        } else {
            $notification = new \core\output\notification($message, \core\output\notification::NOTIFY_WARNING);
            $notification->set_show_closebutton(false);
            echo $OUTPUT->render($notification);
        }

        // Otherwise.
    } else {
        // Compose message.
        $message = get_string('guidropupgradechecksuccess', 'tool_apcu');

        // Output message.
        $notification = new \core\output\notification($message, \core\output\notification::NOTIFY_SUCCESS);
        $notification->set_show_closebutton(false);
        echo $OUTPUT->render($notification);
    }

    return true;
}
