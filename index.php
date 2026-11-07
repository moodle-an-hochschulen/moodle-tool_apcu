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
 * Admin tool "APCu management" - Main page
 *
 * @package    tool_apcu
 * @copyright  2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/apcu/locallib.php');
global $CFG, $FULLME;

// Set up the plugin's main page as external admin page.
admin_externalpage_setup('tool_apcu');

// If a write operation of the APCu GUI is requested, make sure that the request is not forged.
// The APCu GUI's own authentication is disabled and would not protect against CSRF anyway.
// The APCu GUI uses $_REQUEST, but rebuilds it from $_GET and $_POST if it is empty, so we check all of them.
if (tool_apcu_is_write_request($_GET, $_POST, $_REQUEST)) {
    require_sesskey();
}

// Check if the APCu tool exists on disk so that we can require it.
if (tool_apcu_verify_guidrop_file() == true) {
    // Page setup.
    $title = get_string('pluginname', 'tool_apcu');
    $PAGE->set_title($title);
    $PAGE->set_heading($title);

    // Remember the request parameters as the APCu GUI gets them, before the APCu GUI is included and modifies them.
    $guirequest = tool_apcu_get_gui_request();

    // If the deletion of a user cache entry is requested, remember if the entry exists before the APCu GUI deletes it.
    // This way, we can report afterwards if the entry was really deleted.
    $requestedwriteactions = tool_apcu_get_requested_write_actions($guirequest);
    // If APCu is not available at all, the APCu GUI shows an error message itself, so we must not call any APCu function here.
    $entryexisted = isset($requestedwriteactions['DU']) && function_exists('apcu_exists') &&
            apcu_exists($requestedwriteactions['DU']);

    // Output has to be buffered because we want to modify the APCu GUI HTML code and as the APCu GUI sends some HTTP headers.
    ob_start();

    // Include APCu GUI.
    $toolpath = tool_apcu_get_guidrop_path();
    require_once($toolpath);

    // Get buffered content and finish buffering.
    $output = ob_get_contents();
    ob_end_clean();

    // If the APCu GUI has performed a write operation, redirect to the same view without the write operation parameters.
    // This way, the write operation is not repeated if the page is reloaded.
    // $MYREQUEST is set by the APCu GUI which we have just included in the global scope. It contains the request parameters
    // which the APCu GUI has validated and used, so we look at the same values which the APCu GUI has based its decision on.
    // The APCu GUI's variables are accessed via $GLOBALS as their upper-case names do not comply with the Moodle coding style.
    $writeactions = tool_apcu_get_requested_write_actions($GLOBALS['MYREQUEST'] ?? []);
    if (!empty($writeactions)) {
        $writeparams = tool_apcu_get_write_params();
        foreach ($writeactions as $param => $value) {
            // Check if the write operation was really performed.
            // $AUTHENTICATED is set by the APCu GUI as well and the APCu GUI performs write operations only if it is set.
            $succeeded = !empty($GLOBALS['AUTHENTICATED']);
            if ($param === 'DU') {
                $succeeded = $succeeded && $entryexisted && function_exists('apcu_exists') && !apcu_exists($value);
            }

            // Add a notification about the write operation to the stack.
            if ($succeeded) {
                core\notification::success(get_string($writeparams[$param]['success'], 'tool_apcu', s($value)));
            } else {
                core\notification::error(get_string($writeparams[$param]['failure'], 'tool_apcu', s($value)));
            }
        }
        redirect(tool_apcu_get_redirect_url($guirequest));
    }

    // Parse the APCu GUI HTML code.
    $apcudoc = tool_apcu_parse_gui_output($output);

    // Protect the write operations of the APCu GUI with the sesskey.
    tool_apcu_add_sesskey_to_write_links($apcudoc);

    // Extract the body HTML code and the styles code from the APCu GUI HTML code.
    $apcuguiparts = tool_apcu_extract_gui_parts($apcudoc);
    $apcuguihtml = $apcuguiparts['html'];
    $apcustylescode = $apcuguiparts['css'];

    // Remove the HTML comments from the style code as they would interfere with the upcoming prefixing.
    $apcustylescode = str_replace('<!--', '', $apcustylescode);
    $apcustylescode = str_replace('//-->', '', $apcustylescode);

    // Prefix all styles to avoid any conflicts with Moodle styles.
    $cssprefix = '#page-admin-tool-apcu-index #region-main';
    $apcustylescode = tool_apcu_get_prefixed_css($apcustylescode, $cssprefix);

    // Add the APCu GUI styles to the page.
    $CFG->additionalhtmlhead .= '<style>' . $apcustylescode . '</style>';

    // Page setup.
    echo $OUTPUT->header();

    // Output APCu GUI.
    echo $apcuguihtml;

    // Page setup.
    echo $OUTPUT->footer();

    // Otherwise.
} else {
    // Try to download and store APCu management GUI file on-the-fly.
    $guidropsuccessful = tool_apcu_do_guidrop();

    // If the download was successful.
    if ($guidropsuccessful) {
        // Add a notification to the stack.
        core\notification::success(get_string('guidropmissingonthefly', 'tool_apcu'));

        // Redirect to the same page again.
        redirect($FULLME);

        // Otherwise.
    } else {
        // Output message about missing APCu GUI file.
        throw new \moodle_exception('guidropmissing', 'tool_apcu');
    }
}
