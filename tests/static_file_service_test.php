<?php
// This file is part of the QuestionPy Moodle plugin - https://questionpy.org
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

namespace qtype_questionpy;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . "/question/engine/tests/helpers.php");
require_once(__DIR__ . "/data_provider.php");

use coding_exception;
use core\di;
use dml_exception;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use invalid_dataroot_permissions;
use moodle_exception;
use qtype_questionpy\api\api;
use qtype_questionpy\api\package_api;
use qtype_questionpy\api\qpy_http_client;

/**
 * Tests QuestionPy static file access.
 *
 * Ideally, we'd call {@see file_pluginfile} to test the entire path, but that isn't possible since Moodle expects
 * our pluginfile function to die after serving and {@see send_file} isn't testable (It ends all levels of output
 * buffering).
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2024 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \qtype_questionpy\static_file_service
 * @covers \qtype_questionpy\api\package_api::download_static_file
 */
final class static_file_service_test extends \advanced_testcase {
    /** @var MockHandler */
    private MockHandler $mockhandler;

    /** @var array */
    private array $requesthistory;

    /** @var static_file_service */
    private static_file_service $staticfileservice;

    /**
     * Sets up mocks and the like before each test.
     *
     * @throws dml_exception
     */
    protected function setUp(): void {
        $this->mockhandler = new MockHandler();
        $this->requesthistory = [];
        $handlerstack = HandlerStack::create($this->mockhandler);
        $handlerstack->push(Middleware::history($this->requesthistory));
        $api = new api(new qpy_http_client([
            "handler" => $handlerstack,
        ]));
        di::set(api::class, $api);

        $packagefileservice = $this->createStub(package_file_service::class);
        $packagefileservice
            ->method("get_file_by_package_hash")
            ->willReturn(null);

        $this->staticfileservice = new static_file_service($api, $packagefileservice);
    }

    /**
     * Tests the happy-path of a static file download.
     *
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_dataroot_permissions
     */
    public function test_should_download_public_static_file(): void {
        $hash = random_string(64);
        $this->mockhandler->append(new Response(200, [
            "Content-Type" => "text/markdown",
        ], "Static file content"));

        [$path, $mimetype] = $this->staticfileservice->download_public_static_file(
            $hash,
            "local",
            "example",
            "/path/to/file.txt"
        );

        $this->assertStringEqualsFile($path, "Static file content");
        $this->assertEquals("text/markdown", $mimetype);

        $this->assertCount(1, $this->requesthistory);
        /** @var Request $req */
        $req = $this->requesthistory[0]["request"];
        $this->assertEquals("POST", $req->getMethod());
        $this->assertStringEndsWith("/packages/$hash/file/local/example/static/path/to/file.txt", $req->getUri());
        $this->assertEquals(0, $req->getBody()->getSize());
    }

    /**
     * Tests that we fall back to `octet-stream` and emit a warning when the response includes no Content-Type.
     *
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_dataroot_permissions
     */
    public function test_should_fall_back_and_warn_when_no_content_type(): void {
        $this->mockhandler->append(new Response(200, [], "Static file content"));

        [, $mimetype] = $this->staticfileservice->download_public_static_file(
            random_string(64),
            "local",
            "example",
            "/path/to/file.txt"
        );

        $this->assertEquals("application/octet-stream", $mimetype);
        $this->assertDebuggingCalled("Server did not send Content-Type header, falling back to application/octet-stream");
    }

    /**
     * Tests that null is returned when the server responds with 404, indicating that the static file doesn't exist.
     *
     * @return void
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_should_return_null_when_file_doesnt_exist(): void {
        $this->mockhandler->append(new Response(404, []));

        $result = $this->staticfileservice->download_public_static_file(
            random_string(64),
            "local",
            "example",
            "/path/to/file.txt"
        );

        $this->assertNull($result);
    }
}
