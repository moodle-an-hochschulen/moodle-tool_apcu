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
 * Admin tool "APCu management" - Installation file
 *
 * @package    tool_apcu
 * @copyright  2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/'.$CFG->admin.'/tool/apcu/locallib.php');

/**
 * Function to install tool_apcu.
 *
 * @return bool
 */
function xmldb_tool_apcu_install() {
    global $OUTPUT;

    // Install the APCu GUI file from the web - either automatically or manually.

    // Try to download and store APCu management GUI file.
    $guidropsuccessful = tool_apcu_do_guidrop();

    // Compose message.
    $messagepaths = ['source' => tool_apcu_get_guidrop_url(), 'target' => tool_apcu_get_guidrop_path()];
    $message = '<p>'.get_string('guidropinstallintro', 'tool_apcu', $messagepaths).'</p>';
    if ($guidropsuccessful == true) {
        $message .= '<p>'.get_string('guidropsuccess', 'tool_apcu', $messagepaths).'</p>';
    } else {
        $message .= '<p>'.get_string('guidroperror', 'tool_apcu', $messagepaths).'</p>';
    }
    $message .= '<p>'.get_string('guidropinstalloutro', 'tool_apcu', $messagepaths).'</p>';

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

    return true;
}
