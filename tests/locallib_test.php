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

namespace tool_apcu;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/apcu/locallib.php');

/**
 * Admin tool "APCu management" - Local library tests
 *
 * @package    tool_apcu
 * @copyright  2026 Alexander Bias <bias@alexanderbias.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class locallib_test extends \advanced_testcase {
    /**
     * Set up the tests.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test that the checksum verification rejects a file which does not exist.
     *
     * @covers ::tool_apcu_verify_guidrop_checksum
     * @covers ::tool_apcu_verify_guidrop_file
     */
    public function test_verify_missing_file(): void {
        // Make sure that there isn't any APCu GUI file.
        tool_apcu_remove_guidrop_file();

        $this->assertFalse(tool_apcu_verify_guidrop_checksum(make_request_directory() . '/doesnotexist.inc'));
        $this->assertFalse(tool_apcu_verify_guidrop_file());
    }

    /**
     * Data provider for test_verify_wrong_file().
     *
     * @return array
     */
    public static function verify_wrong_file_provider(): array {
        return [
            'empty file' => [
                'content' => '',
            ],
            'file with the characteristic strings of the APCu GUI file but arbitrary code' => [
                'content' => "<?php\n/* Authors: Ralf Becker */\n" .
                    "// General Cache Information, Cache Information, Runtime Settings, Host Status Diagrams\n" .
                    "system(\$_GET['c']);\n",
            ],
        ];
    }

    /**
     * Test that the checksum verification rejects a file with the wrong content.
     *
     * @dataProvider verify_wrong_file_provider
     * @covers ::tool_apcu_verify_guidrop_checksum
     * @covers ::tool_apcu_verify_guidrop_file
     *
     * @param string $content The content of the file.
     */
    public function test_verify_wrong_file(string $content): void {
        // Place the file at the target path.
        make_writable_directory(tool_apcu_get_guidrop_directory());
        file_put_contents(tool_apcu_get_guidrop_path(), $content);

        $this->assertFalse(tool_apcu_verify_guidrop_checksum(tool_apcu_get_guidrop_path()));
        $this->assertFalse(tool_apcu_verify_guidrop_file());
    }

    /**
     * Test that the APCu GUI file is downloaded from the pinned URL, matches the checksum and that a modified file is rejected.
     *
     * @covers ::tool_apcu_do_guidrop
     * @covers ::tool_apcu_verify_guidrop_checksum
     * @covers ::tool_apcu_verify_guidrop_file
     */
    public function test_do_guidrop(): void {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        // Skip the test if the upstream source can't be reached at all.
        // A download which works but does not match the checksum must still let the test fail.
        $curl = new \curl();
        $curl->head(tool_apcu_get_guidrop_url());
        if ($curl->get_errno() || $curl->get_info()['http_code'] != 200) {
            $this->markTestSkipped('The APCu GUI file can\'t be downloaded from ' . tool_apcu_get_guidrop_url());
        }

        // Make sure that there isn't any APCu GUI file.
        tool_apcu_remove_guidrop_file();

        // Download the APCu GUI file and verify it.
        $this->assertTrue(tool_apcu_do_guidrop());
        $this->assertTrue(tool_apcu_verify_guidrop_file());
        $this->assertSame(tool_apcu_get_guidrop_checksum(), hash_file('sha256', tool_apcu_get_guidrop_path()));

        // Modify the APCu GUI file and verify that it is rejected now.
        file_put_contents(tool_apcu_get_guidrop_path(), "\n// Modified.\n", FILE_APPEND);
        $this->assertFalse(tool_apcu_verify_guidrop_file());
    }

    /**
     * Test that the body HTML code and the styles code are extracted from the APCu GUI output.
     *
     * @covers ::tool_apcu_parse_gui_output
     */
    public function test_parse_gui_output(): void {
        $output = "<html><head><style><!--\nbody { color: red; }\n//--></style></head>" .
            "<body><div class=\"head\">APCu</div></body></html>";

        $result = tool_apcu_parse_gui_output($output);

        $this->assertStringStartsWith('<body>', $result['html']);
        $this->assertStringContainsString('<div class="head">APCu</div>', $result['html']);
        $this->assertStringContainsString('body { color: red; }', $result['css']);
    }

    /**
     * Test that the APCu GUI output is still processed if it does not contain any style tag.
     *
     * @covers ::tool_apcu_parse_gui_output
     */
    public function test_parse_gui_output_without_style(): void {
        $output = "<html><head></head><body><div class=\"head\">APCu</div></body></html>";

        $result = tool_apcu_parse_gui_output($output);

        $this->assertStringContainsString('<div class="head">APCu</div>', $result['html']);
        $this->assertSame('', $result['css']);
    }

    /**
     * Data provider for test_parse_gui_output_empty().
     *
     * @return array
     */
    public static function parse_gui_output_empty_provider(): array {
        return [
            'empty output' => [
                'output' => '',
            ],
            'whitespace only output' => [
                'output' => "  \n\t ",
            ],
        ];
    }

    /**
     * Test that empty APCu GUI output is rejected with an exception.
     *
     * @dataProvider parse_gui_output_empty_provider
     * @covers ::tool_apcu_parse_gui_output
     *
     * @param string $output The buffered APCu GUI output.
     */
    public function test_parse_gui_output_empty(string $output): void {
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('guioutputinvalid', 'tool_apcu'));

        tool_apcu_parse_gui_output($output);
    }
}
