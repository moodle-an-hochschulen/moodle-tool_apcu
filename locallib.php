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

defined('MOODLE_INTERNAL') || die();

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
