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
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Helper function to get the request parameters which trigger write operations in the APCu management GUI.
 *
 * These write operations are protected with a sesskey check by this plugin.
 * With each update of the APCu management GUI file, check if there are new write operations which have to be added here.
 *
 * @return array The request parameters as keys and the language string keys of the messages
 *               which are shown after the write operation as values. The parameter's value is passed to the language string.
 */
function tool_apcu_get_write_params() {
    // The request parameters of the APCu GUI which trigger write operations and the language strings for their messages.
    return [
        'CC' => 'writeactioncachecleared', // Clear cache.
        'DU' => 'writeactionentrydeleted', // Delete user cache entry.
    ];
}

/**
 * Helper function to get the write operations of the APCu management GUI which are requested in the given request parameters.
 *
 * @param array $request The request parameters, e.g. $_REQUEST or the APCu management GUI's $MYREQUEST.
 *
 * @return array The requested write operation parameters with their values.
 */
function tool_apcu_get_requested_write_actions($request) {
    // Initialize the list of requested write operations.
    $actions = [];

    // Iterate over all request parameters which trigger write operations.
    foreach (array_keys(tool_apcu_get_write_params()) as $param) {
        // If the parameter is given with a usable value, remember it as requested write operation.
        // The APCu GUI ignores empty values (like CC=0) and array values, so we do the same.
        if (!empty($request[$param]) && !is_array($request[$param])) {
            $actions[$param] = $request[$param];
        }
    }

    // Return the list of requested write operations.
    return $actions;
}

/**
 * Helper function to get the URL to redirect to after the APCu management GUI performed a write operation.
 *
 * The URL keeps the current view of the APCu management GUI, but drops all write operation parameters,
 * so that the write operation is not repeated if the page is reloaded.
 *
 * @param array $request The APCu management GUI's $MYREQUEST.
 *
 * @return moodle_url
 */
function tool_apcu_get_redirect_url($request) {
    // Get the request parameters which must not be kept in the URL.
    // Besides the write operation parameters, these are the APCu GUI's login and image parameters and the sesskey.
    $excludedparams = array_merge(array_keys(tool_apcu_get_write_params()), ['LO', 'IMG', 'sesskey']);

    // Initialize the list of request parameters to be kept.
    $params = [];

    // Iterate over all request parameters.
    foreach ($request as $name => $value) {
        // If the parameter is excluded or if it does not have a usable value, skip it.
        if (in_array($name, $excludedparams, true) || $value === null || $value === '' || is_array($value)) {
            continue;
        }

        // Otherwise, keep the parameter.
        $params[$name] = $value;
    }

    // Return the URL of the plugin's main page with the kept request parameters.
    return new moodle_url('/admin/tool/apcu/index.php', $params);
}

/**
 * Helper function to add the sesskey to all links in the given DOM which trigger write operations in the APCu management GUI.
 *
 * @param DOMDocument $doc
 */
function tool_apcu_add_sesskey_to_write_links($doc) {
    // Iterate over all links in the DOM.
    foreach ($doc->getElementsByTagName('a') as $link) {
        // Get the link target and its query string.
        $href = $link->getAttribute('href');
        $query = parse_url($href, PHP_URL_QUERY);

        // If the link target does not have a query string, it can't trigger any write operation, skip it.
        if (empty($query)) {
            continue;
        }

        // Parse the request parameters from the query string.
        parse_str($query, $params);

        // If the link triggers a write operation, add the sesskey to the link target.
        if (!empty(tool_apcu_get_requested_write_actions($params))) {
            $link->setAttribute('href', $href . '&sesskey=' . sesskey());
        }
    }
}

/**
 * Helper function to extract the body HTML code and the styles code from the buffered APCu management GUI output.
 *
 * @param string $output
 *
 * @return array An array with the keys 'html' and 'css'.
 * @throws moodle_exception If the output is empty or does not contain a body tag.
 */
function tool_apcu_parse_gui_output($output) {
    // If there is no output at all, we can't process it (and DOMDocument::loadHTML() would throw a ValueError).
    if (trim($output) === '') {
        throw new \moodle_exception('guioutputinvalid', 'tool_apcu');
    }

    // Take control over libXML error handling as the APCu GUI HTML code is not perfect.
    $previouslibxmlerrors = libxml_use_internal_errors(true);

    // Process APCu GUI HTML code into a DOMDocument object.
    $apcudoc = new DOMDocument();
    $apcudoc->loadHTML($output);

    // Protect the write operations of the APCu GUI with the sesskey.
    tool_apcu_add_sesskey_to_write_links($apcudoc);

    // Process DOM and extract the body tag.
    $apcubodytag = $apcudoc->getElementsByTagName('body')->item(0);
    $apcuguihtml = $apcubodytag !== null ? $apcudoc->saveHTML($apcubodytag) : '';

    // Process DOM and extract the style tag.
    // If there isn't any style tag, the APCu GUI is still usable, just without its own styles.
    $apcustyletag = $apcudoc->getElementsByTagName('style')->item(0);
    $apcustylescode = $apcustyletag !== null ? $apcustyletag->nodeValue : '';

    // Throw away any libXML errors which have been raised.
    libxml_clear_errors();

    // Finish control over libXML error handling.
    libxml_use_internal_errors($previouslibxmlerrors);

    // If there is no body tag, the output is not the APCu GUI which we expect.
    if ($apcuguihtml === '' || $apcuguihtml === false) {
        throw new \moodle_exception('guioutputinvalid', 'tool_apcu');
    }

    return ['html' => $apcuguihtml, 'css' => $apcustylescode];
}

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
                $mediaquery = $partdetails[0] . "{";
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
                $part = $mediaquery . "\n" . implode(', ', $subparts) . "{" . $partdetails[2];
            } else if (empty($part[0]) && $mediaquerystarted) {
                $mediaquerystarted = false;
                $part = implode(', ', $subparts) . "{" . $partdetails[2] . "}\n";
            } else {
                if (isset($partdetails[1])) {
                    $part = implode(', ', $subparts) . "{" . $partdetails[1];
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
    require_once($CFG->libdir . '/filelib.php');

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
    $curl = new \curl();
    $downloadtmp = make_request_directory();
    $downloadto = $downloadtmp . '/apcu.php.inc';
    $downloadoptions = ['filepath' => $downloadto, 'timeout' => 60, 'followlocation' => true, 'maxredirs' => 0];
    $downloadsuccess = $curl->download_one(tool_apcu_get_guidrop_url(), null, $downloadoptions);

    // If the download was not successful, return.
    // On failure, cURL returns the error message as string, so we must compare strictly here.
    if ($downloadsuccess !== true) {
        return false;
    }

    // If the downloaded APCu GUI file does not match the expected checksum, return.
    if (tool_apcu_verify_guidrop_checksum($downloadto) != true) {
        return false;
    }

    // Try to move the APCu GUI file to the target directory.
    $renamesuccess = rename($downloadto, $targetpath);

    // If the rename command was not successful, return.
    if ($renamesuccess != true) {
        return false;
    }

    // If, in the end, the APCu GUI file isn't at the target path or does not match the expected checksum, return.
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
    // This is pinned to a particular upstream commit to make sure that the file matches the checksum below.
    return 'https://raw.githubusercontent.com/krakjoe/apcu/e5132e4da010829c7359a6bda4dc985834ba2094/apc.php';
}

/**
 * Helper function to get the expected SHA-256 checksum of the APCu management GUI file.
 *
 * @return string
 */
function tool_apcu_get_guidrop_checksum() {
    // The SHA-256 checksum of the APCu GUI file at the upstream commit which is used in tool_apcu_get_guidrop_url().
    return '3adfd4947ab9a9ada8f8d54059ac79e86c5c7ea218721923121a55b77b26d2b0';
}

/**
 * Helper function to verify if the given file matches the expected checksum of the APCu management GUI file.
 *
 * @param string $filepath
 *
 * @return bool
 */
function tool_apcu_verify_guidrop_checksum($filepath) {
    // If there isn't a readable file at the given path, return.
    if (file_exists($filepath) != true || is_readable($filepath) != true) {
        return false;
    }

    // If the checksum can't be calculated, return.
    $checksum = hash_file('sha256', $filepath);
    if ($checksum === false) {
        return false;
    }

    // Compare the checksum with the expected checksum.
    return hash_equals(tool_apcu_get_guidrop_checksum(), $checksum);
}

/**
 * Helper function to get the directory where to store the APCu management GUI file.
 *
 * @return string
 */
function tool_apcu_get_guidrop_directory() {
    global $CFG;

    // The directory where the APCu GUI file should be stored.
    return $CFG->dataroot . '/tool_apcu';
}

/**
 * Helper function to get the directory and filename where to store the APCu management GUI file.
 *
 * @return string
 */
function tool_apcu_get_guidrop_path() {
    global $CFG;

    // The directory and filename where the APCu GUI file should be stored.
    return $CFG->dataroot . '/tool_apcu/apcu.php.inc';
}

/**
 * Helper function to verify if the APCu management GUI file exists and matches the expected checksum.
 *
 * @return bool
 */
function tool_apcu_verify_guidrop_file() {
    // Get the path where the file is expected.
    $targetpath = tool_apcu_get_guidrop_path();

    // If there isn't a readable file at the target path or if it does not match the expected checksum, return.
    if (tool_apcu_verify_guidrop_checksum($targetpath) != true) {
        return false;
    }

    // If we have reached this point, the APCu GUI management file should be fine.
    return true;
}

/**
 * Helper function to remove the APCu management GUI file.
 *
 * @return bool
 */
function tool_apcu_remove_guidrop_file() {
    // Get the directory where the file is expected.
    $directory = tool_apcu_get_guidrop_directory();

    // Remove the directory.
    $ret = remove_dir($directory);

    // Return the result of the removal.
    return $ret;
}
