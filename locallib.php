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
 * Admin tool "APCu management" - Local library
 *
 * @package    tool_apcu
 * @copyright  2020 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Helper function to prefix given CSS code.
 *
 * @author Spooky
 * @copyright 2016 https://stackoverflow.com/a/35253188
 *
 * @param string $css
 * @param string $prefix
 *
 * @return string
 */
function tool_apcu_get_prefixed_css($css, $prefix) {
    $css = preg_replace('!/\*.*?\*/!s', '', $css);

    $parts = explode('}', $css);
    $mediaquerystarted = false;

    foreach ($parts as &$part) {
        $part = trim($part);
        if (empty($part)) {
            continue;
        } else {
            $partdetails = explode('{', $part);
            if (substr_count($part, "{") == 2) {
                $mediaquery = $partdetails[0]."{";
                $partdetails[0] = $partdetails[1];
                $mediaquerystarted = true;
            }

            $subparts = explode(',', $partdetails[0]);
            foreach ($subparts as &$subpart) {
                if (trim($subpart) === "@font-face") {
                    continue;
                } else {
                    $subpart = $prefix . ' ' . trim($subpart);
                }
            }

            if (substr_count($part, "{") == 2) {
                $part = $mediaquery."\n".implode(', ', $subparts)."{".$partdetails[2];
            } else if (empty($part[0]) && $mediaquerystarted) {
                $mediaquerystarted = false;
                $part = implode(', ', $subparts)."{".$partdetails[2]."}\n";
            } else {
                if (isset($partdetails[1])) {
                    $part = implode(', ', $subparts)."{".$partdetails[1];
                }
            }

            unset($partdetails, $mediaquery, $subparts);
        }

        unset($part);
    }

    return(preg_replace('/\s+/', ' ', implode("} ", $parts)));
}

/**
 * Helper function to download and store APCu management GUI file.
 *
 * @return bool
 */
function tool_apcu_do_guidrop() {
    global $CFG;

    // Require Filelib for using cURL.
    require_once($CFG->libdir.'/filelib.php');

    // Get the target directory and path.
    $targetdir = tool_apcu_get_guidrop_directory();
    $targetpath = tool_apcu_get_guidrop_path();

    // If the target directory does not exist yet.
    if (is_dir($targetdir) != true) {
        // Create it.
        $retmakedir = make_writable_directory($targetdir);

        // If something went wrong, return.
        if ($retmakedir != true) {
            return false;
        }
    }

    // If we would be unable to store the APCu GUI file now, return.
    if (is_writable($targetdir) != true) {
        return false;
    }

    // Try to download the APCu GUI file.
    $curl = new \curl;
    $downloadtmp = make_request_directory();
    $downloadto = $downloadtmp.'/apcu.php.inc';
    $downloadoptions = ['filepath' => $downloadto, 'timeout' => 60, 'followlocation' => true, 'maxredirs' => 0];
    $downloadsuccess = $curl->download_one(tool_apcu_get_guidrop_url(), null, $downloadoptions);

    // If the download was not successful, return.
    if ($downloadsuccess != true) {
        return false;
    }

    // Try to move the APCu GUI file to the target directory.
    $renamesuccess = rename($downloadto, $targetpath);

    // If the rename command was not successful, return.
    if ($renamesuccess != true) {
        return false;
    }

    // If, in the end, the APCu GUI file isn't at the target path or does not contain the right content, return.
    if (tool_apcu_verify_guidrop_file() != true) {
        return false;
    }

    // If we have reached this point, the APCu GUI management file should be placed correctly.
    return true;
}

/**
 * Helper function to get the URL where to download the APCu management GUI file.
 *
 * @return string
 */
function tool_apcu_get_guidrop_url() {
    // The source where the APCu GUI file is downloaded from.
    return 'https://raw.githubusercontent.com/krakjoe/apcu/master/apc.php';
}

/**
 * Helper function to get the directory where to store the APCu management GUI file.
 *
 * @return string
 */
function tool_apcu_get_guidrop_directory() {
    global $CFG;

    // The directory where the APCu GUI file should be stored.
    return $CFG->dataroot.'/tool_apcu';
}

/**
 * Helper function to get the directory and filename where to store the APCu management GUI file.
 *
 * @return string
 */
function tool_apcu_get_guidrop_path() {
    global $CFG;

    // The directory and filename where the APCu GUI file should be stored.
    return $CFG->dataroot.'/tool_apcu/apcu.php.inc';
}

/**
 * Helper function to verify if the APCu management GUI file exists and has the right content.
 *
 * @return bool
 */
function tool_apcu_verify_guidrop_file() {
    // Get the path where the file is expected.
    $targetpath = tool_apcu_get_guidrop_path();

    // If there isn't a readable file at the target path, return.
    if (file_exists ($targetpath) != true || is_readable($targetpath) != true) {
        return false;
    }

    // If the file does not contain characteristic code snippets, return.
    $filestring = file_get_contents($targetpath);
    if (strpos($filestring, 'Authors: Ralf Becker') === false ||
            strpos($filestring, 'General Cache Information') === false ||
            strpos($filestring, 'Cache Information') === false ||
            strpos($filestring, 'Runtime Settings') === false ||
            strpos($filestring, 'Host Status Diagrams') === false) {
        return false;
    }

    // If we have reached this point, the APCu GUI management file should be fine.
    return true;
}

/**
 * Helper function to remove the APCu management GUI file.
 *
 * @return string
 */
function tool_apcu_remove_guidrop_file() {
    // Get the directory where the file is expected.
    $directory = tool_apcu_get_guidrop_directory();

    // Remove the directory.
    $ret = remove_dir($directory);

    // Return the result of the removal.
    return $ret;
}
