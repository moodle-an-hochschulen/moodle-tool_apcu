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
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/'.$CFG->admin.'/tool/apcu/locallib.php');
global $CFG;

// Set up the plugin's main page as external admin page.
admin_externalpage_setup('tool_apcu');

// Page setup.
$title = get_string('pluginname', 'tool_apcu');
$PAGE->set_title($title);
$PAGE->set_heading($title);

// Output has to be buffered because we want to modify the APCu GUI HTML code and as the APCu GUI sends some HTTP headers.
ob_start();

// Include APCu GUI.
require_once(__DIR__ . '/lib/apcu-gui/apcu.php.inc');

// Get buffered content and finish buffering.
$output = ob_get_contents();
ob_end_clean();

// Take control over libXML error handling as the APCu GUI HTML code is not perfect.
libxml_use_internal_errors(true);

// Process APCu GUI HTML code into a DOMDocument object.
$apcudoc = new DOMDocument();
$apcudoc->loadHTML($output);

// Process DOM and extract the body tag.
$apcubodytag = $apcudoc->getElementsByTagName('body')->item(0);
$apcuguihtml = $apcudoc->saveHTML($apcubodytag);

// Process DOM and extract the style tag.
$apcustyletag = $apcudoc->getElementsByTagName('style')->item(0);
$apcustylescode = $apcustyletag->nodeValue;

// Throw away any libXML errors which have been raised.
libxml_clear_errors();

// Finish control over libXML error handling.
libxml_use_internal_errors(false);

// Remove the HTML comments from the style code as they would interfere with the upcoming prefixing.
$apcustylescode = str_replace('<!--', '', $apcustylescode);
$apcustylescode = str_replace('//-->', '', $apcustylescode);

// Prefix all styles to avoid any conflicts with Moodle styles.
$cssprefix = '#page-admin-tool-apcu-index #region-main';
$apcustylescode = tool_apcu_get_prefixed_css($apcustylescode, $cssprefix);

// Add the APCu GUI styles to the page.
$CFG->additionalhtmlhead .= '<style>'.$apcustylescode.'</style>';

// Page setup.
echo $OUTPUT->header();

// Output APCu GUI.
echo $apcuguihtml;

// Page setup.
echo $OUTPUT->footer();
