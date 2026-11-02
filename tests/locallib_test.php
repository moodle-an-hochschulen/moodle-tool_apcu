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
     * Data provider for test_do_guidrop_rejected_download().
     *
     * @return array
     */
    public static function do_guidrop_rejected_download_provider(): array {
        return [
            'download fails with an error message' => [
                'response' => 'Could not resolve host: raw.githubusercontent.com',
            ],
            'download succeeds but the file does not match the checksum' => [
                'response' => true,
            ],
        ];
    }

    /**
     * Test that the APCu GUI file is not placed if the download fails or if the downloaded file does not match the checksum.
     *
     * @dataProvider do_guidrop_rejected_download_provider
     * @covers ::tool_apcu_do_guidrop
     *
     * @param string|bool $response The mocked cURL response.
     */
    public function test_do_guidrop_rejected_download(string|bool $response): void {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        // Place a known file at the target path.
        make_writable_directory(tool_apcu_get_guidrop_directory());
        file_put_contents(tool_apcu_get_guidrop_path(), 'Existing file');

        // Mock the cURL response.
        // A mocked response does not write anything to the download file, so a successful download results in an empty file.
        \curl::mock_response($response);

        $this->assertFalse(tool_apcu_do_guidrop());

        // Verify that the existing file was not replaced.
        $this->assertSame('Existing file', file_get_contents(tool_apcu_get_guidrop_path()));
    }

    /**
     * Test that the APCu GUI file is stored in a directory of its own within the dataroot.
     *
     * @covers ::tool_apcu_get_guidrop_directory
     * @covers ::tool_apcu_get_guidrop_path
     */
    public function test_get_guidrop_directory_and_path(): void {
        global $CFG;

        $this->assertSame($CFG->dataroot . '/tool_apcu', tool_apcu_get_guidrop_directory());
        $this->assertSame(tool_apcu_get_guidrop_directory() . '/apcu.php.inc', tool_apcu_get_guidrop_path());
    }

    /**
     * Test that the APCu GUI file is downloaded from a particular upstream commit and that there is a valid checksum for it.
     *
     * @covers ::tool_apcu_get_guidrop_url
     * @covers ::tool_apcu_get_guidrop_checksum
     */
    public function test_get_guidrop_url_and_checksum(): void {
        // The URL must be pinned to a commit hash and not to a branch like master, otherwise the checksum would break.
        $this->assertMatchesRegularExpression(
            '#^https://raw\.githubusercontent\.com/krakjoe/apcu/[0-9a-f]{40}/apc\.php$#',
            tool_apcu_get_guidrop_url()
        );

        // The checksum must be a SHA-256 checksum.
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', tool_apcu_get_guidrop_checksum());
    }

    /**
     * Test that the APCu GUI file is removed together with its directory.
     *
     * @covers ::tool_apcu_remove_guidrop_file
     */
    public function test_remove_guidrop_file(): void {
        // Place a file at the target path.
        make_writable_directory(tool_apcu_get_guidrop_directory());
        file_put_contents(tool_apcu_get_guidrop_path(), 'Existing file');

        $this->assertTrue(tool_apcu_remove_guidrop_file());
        $this->assertFileDoesNotExist(tool_apcu_get_guidrop_path());
        $this->assertDirectoryDoesNotExist(tool_apcu_get_guidrop_directory());
    }

    /**
     * Data provider for test_get_prefixed_css().
     *
     * @return array
     */
    public static function get_prefixed_css_provider(): array {
        return [
            'empty code' => [
                'css' => '',
                'expected' => '',
            ],
            'single selector' => [
                'css' => "div { color: red; }",
                'expected' => '#apcu div{ color: red;} ',
            ],
            'multiple selectors' => [
                'css' => "h1, h2 { margin: 0; }",
                'expected' => '#apcu h1, #apcu h2{ margin: 0;} ',
            ],
            'comments are removed' => [
                'css' => "/* Comment */\np { margin: 0; }",
                'expected' => '#apcu p{ margin: 0;} ',
            ],
            'font-face is not prefixed' => [
                'css' => "@font-face { font-family: foo; }",
                'expected' => '@font-face { font-family: foo;} ',
            ],
            'media query' => [
                'css' => "@media (max-width: 600px) {\n  div { color: red; }\n}\np { margin: 0; }",
                'expected' => '@media (max-width: 600px) { #apcu div{ color: red;} } #apcu p{ margin: 0;} ',
            ],
        ];
    }

    /**
     * Test that the given CSS code is prefixed.
     *
     * @dataProvider get_prefixed_css_provider
     * @covers ::tool_apcu_get_prefixed_css
     *
     * @param string $css The CSS code.
     * @param string $expected The expected prefixed CSS code.
     */
    public function test_get_prefixed_css(string $css, string $expected): void {
        $this->assertSame($expected, tool_apcu_get_prefixed_css($css, '#apcu'));
    }

    /**
     * Test that the body HTML code and the styles code are extracted from the APCu GUI output.
     *
     * @covers ::tool_apcu_parse_gui_output
     * @covers ::tool_apcu_extract_gui_parts
     */
    public function test_parse_gui_output(): void {
        $output = "<html><head><style><!--\nbody { color: red; }\n//--></style></head>" .
            "<body><div class=\"head\">APCu</div></body></html>";

        $result = tool_apcu_extract_gui_parts(tool_apcu_parse_gui_output($output));

        $this->assertStringStartsWith('<body>', $result['html']);
        $this->assertStringContainsString('<div class="head">APCu</div>', $result['html']);
        $this->assertStringContainsString('body { color: red; }', $result['css']);
    }

    /**
     * Test that the APCu GUI output is still processed if it does not contain any style tag.
     *
     * @covers ::tool_apcu_parse_gui_output
     * @covers ::tool_apcu_extract_gui_parts
     */
    public function test_parse_gui_output_without_style(): void {
        $output = "<html><head></head><body><div class=\"head\">APCu</div></body></html>";

        $result = tool_apcu_extract_gui_parts(tool_apcu_parse_gui_output($output));

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

    /**
     * Data provider for test_get_requested_write_actions().
     *
     * @return array
     */
    public static function get_requested_write_actions_provider(): array {
        return [
            'no parameters' => [
                'request' => [],
                'expected' => [],
            ],
            'read-only parameters only' => [
                'request' => ['OB' => '2', 'SCOPE' => 'A', 'SORT1' => 'H', 'SEARCH' => 'foo'],
                'expected' => [],
            ],
            'clear cache' => [
                'request' => ['OB' => '1', 'CC' => '1'],
                'expected' => ['CC' => '1'],
            ],
            'clear cache with zero value' => [
                'request' => ['CC' => '0'],
                'expected' => [],
            ],
            'delete user cache entry' => [
                'request' => ['OB' => '2', 'DU' => 'core_foo/bar'],
                'expected' => ['DU' => 'core_foo/bar'],
            ],
            'array value' => [
                'request' => ['CC' => ['1']],
                'expected' => [],
            ],
            'null values as set by the APCu GUI' => [
                'request' => ['CC' => null, 'DU' => null, 'OB' => '1'],
                'expected' => [],
            ],
        ];
    }

    /**
     * Test that the requested write operations are detected in the request parameters.
     *
     * @dataProvider get_requested_write_actions_provider
     * @covers ::tool_apcu_get_requested_write_actions
     * @covers ::tool_apcu_get_write_params
     *
     * @param array $request The request parameters.
     * @param array $expected The expected write operations.
     */
    public function test_get_requested_write_actions(array $request, array $expected): void {
        $this->assertSame($expected, tool_apcu_get_requested_write_actions($request));
    }

    /**
     * Data provider for test_is_write_request().
     *
     * @return array
     */
    public static function is_write_request_provider(): array {
        return [
            'no request parameter sets' => [
                'requests' => [],
                'expected' => false,
            ],
            'empty request parameter sets' => [
                'requests' => [[], [], []],
                'expected' => false,
            ],
            'read-only parameters only' => [
                'requests' => [['OB' => '2'], ['SEARCH' => 'foo'], ['OB' => '2', 'SEARCH' => 'foo']],
                'expected' => false,
            ],
            'write operation in GET only, e.g. if $_REQUEST is empty' => [
                'requests' => [['CC' => '1'], [], []],
                'expected' => true,
            ],
            'write operation in POST only' => [
                'requests' => [[], ['DU' => 'core_foo/bar'], []],
                'expected' => true,
            ],
            'write operation in REQUEST only, hidden behind an empty value in GET' => [
                'requests' => [['CC' => '0'], [], ['CC' => '1']],
                'expected' => true,
            ],
        ];
    }

    /**
     * Test that a write operation is detected in any of the given request parameter sets.
     *
     * @dataProvider is_write_request_provider
     * @covers ::tool_apcu_is_write_request
     *
     * @param array $requests The request parameter sets.
     * @param bool $expected The expected result.
     */
    public function test_is_write_request(array $requests, bool $expected): void {
        $this->assertSame($expected, tool_apcu_is_write_request(...$requests));
    }

    /**
     * Test that the redirect URL keeps the view parameters and drops the write operation parameters.
     *
     * @covers ::tool_apcu_get_redirect_url
     */
    public function test_get_redirect_url(): void {
        $request = [
            'OB' => '2',
            'CC' => '1',
            'DU' => 'core_foo/bar',
            'SH' => null,
            'IMG' => null,
            'LO' => null,
            'COUNT' => '20',
            'SCOPE' => 'A',
            'SORT1' => 'H',
            'SORT2' => 'D',
            'SEARCH' => 'core_foo',
        ];

        $url = tool_apcu_get_redirect_url($request);

        $this->assertSame((new \moodle_url('/admin/tool/apcu/index.php'))->out(false), $url->out_omit_querystring());
        $this->assertSame(
            ['OB' => '2', 'COUNT' => '20', 'SCOPE' => 'A', 'SORT1' => 'H', 'SORT2' => 'D', 'SEARCH' => 'core_foo'],
            $url->params()
        );
    }

    /**
     * Test that the sesskey is added to the links which trigger write operations and only to them.
     *
     * @covers ::tool_apcu_add_sesskey_to_write_links
     */
    public function test_add_sesskey_to_write_links(): void {
        $output = "<html><head></head><body><ol class=menu>" .
            "<li><a href=\"/admin/tool/apcu/index.php?SCOPE=A&SORT1=H&OB=1&SH=\">Refresh Data</a></li>" .
            "<li><a href=\"/admin/tool/apcu/index.php?SCOPE=A&SORT1=H&OB=2\">User Cache Entries</a></li>" .
            "<li><a class=\"aright\" href=\"/admin/tool/apcu/index.php?SCOPE=A&SORT1=H&CC=1&OB=1\">Clear Cache</a></li>" .
            "</ol>" .
            "<a href=\"/admin/tool/apcu/index.php?SCOPE=A&OB=2&DU=core_foo%2Fbar&SEARCH=core_foo\">Delete Now</a>" .
            "<a href=\"/admin/tool/apcu/index.php?OB=2&DU=core_foo%2Fbar#key-1\">Delete With Fragment</a>" .
            "<a href=\"/admin/tool/apcu/index.php?OB=2&DU=core_foo%2Fbar&sesskey=old\">Delete With Sesskey</a>" .
            "<a href=\"/admin/tool/apcu/index.php?\">Empty Query</a>" .
            "<a href=\"#key-1\">Anchor</a>" .
            "</body></html>";

        $doc = tool_apcu_parse_gui_output($output);
        tool_apcu_add_sesskey_to_write_links($doc);
        $result = tool_apcu_extract_gui_parts($doc);

        // Parse the links from the resulting HTML code again.
        $doc = new \DOMDocument();
        $doc->loadHTML($result['html']);
        $hrefs = [];
        foreach ($doc->getElementsByTagName('a') as $link) {
            $hrefs[$link->textContent] = $link->getAttribute('href');
        }

        $this->assertStringEndsWith('&CC=1&OB=1&sesskey=' . sesskey(), $hrefs['Clear Cache']);
        $this->assertStringEndsWith('&SEARCH=core_foo&sesskey=' . sesskey(), $hrefs['Delete Now']);
        $this->assertStringNotContainsString('sesskey', $hrefs['Refresh Data']);
        $this->assertStringNotContainsString('sesskey', $hrefs['User Cache Entries']);
        $this->assertSame(
            '/admin/tool/apcu/index.php?OB=2&DU=core_foo%2Fbar&sesskey=' . sesskey() . '#key-1',
            $hrefs['Delete With Fragment']
        );
        $this->assertSame(
            '/admin/tool/apcu/index.php?OB=2&DU=core_foo%2Fbar&sesskey=' . sesskey(),
            $hrefs['Delete With Sesskey']
        );
        $this->assertSame('/admin/tool/apcu/index.php?', $hrefs['Empty Query']);
        $this->assertSame('#key-1', $hrefs['Anchor']);
    }
}
