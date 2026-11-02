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
 * Admin tool "APCu management" - Behat fixture page which stores a known entry in the APCu user cache
 *
 * The APCu user cache of the webserver can't be filled from the Behat CLI process, as the CLI process has its own APCu memory.
 * Thus, this page is visited by the Behat tests to store an entry which can then be found in the APCu management GUI.
 *
 * @package    tool_apcu
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../../../config.php');

defined('BEHAT_SITE_RUNNING') || die();

global $PAGE, $OUTPUT;

require_admin();

$PAGE->set_url('/admin/tool/apcu/tests/behat/fixtures/store_apcu_entry.php');
$PAGE->set_context(context_system::instance());

// Store the entry in the APCu user cache.
$stored = apcu_store('tool_apcu_behat_entry', 'Behat test entry');

echo $OUTPUT->header();
echo $OUTPUT->notification($stored ? 'APCu cache entry stored' : 'APCu cache entry not stored', $stored ? 'success' : 'error');
echo $OUTPUT->footer();
