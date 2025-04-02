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

namespace qtype_questionpy\local\api;

use dml_exception;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;
use qtype_questionpy\exception\error_code;
use qtype_questionpy\exception\options_form_validation_error;
use qtype_questionpy\exception\request_error;

/**
 * Tests {@see qpy_http_client}.
 *
 * @covers \qtype_questionpy\local\api\qpy_http_client
 *
 * @package    qtype_questionpy
 * @author     Jan Britz
 * @copyright  2025 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class qpy_http_client_test extends \advanced_testcase {
    /**
     * Tests that the middleware transforms {@see RequestException}s to {@see request_error}s.
     *
     * @param error_code $code
     * @param bool $temporary
     * @param string|null $reason
     * @dataProvider valid_error_code_provider
     * @throws GuzzleException
     * @throws dml_exception
     */
    public function test_middleware_should_throw_request_error(error_code $code, bool $temporary, ?string $reason): void {
        $response = new Response(422, [], json_encode([
            'error_code' => $code->value,
            'temporary' => $temporary,
            'reason' => $reason,
        ]));
        $mock = new MockHandler([$response]);
        $client = new qpy_http_client(['mock' => HandlerStack::create($mock)]);

        try {
            $client->get('/');
        } catch (request_error $error) {
            $this->assertSame($code, $error->requesterrorcode);
            $this->assertSame($temporary, $error->temporary);
            $this->assertSame($reason, $error->reason);
            $this->assertInstanceof(GuzzleException::class, $error->origin);
            $this->assertSame($response, $error->origin->getResponse());
            return;
        }
        $this->fail('Expected "request_error" to be thrown.');
    }

    /**
     * Tests that the middleware transforms {@see RequestException}s with a body containing the
     * {@see error_code::invalid_options_form} to {@see options_form_validation_error}s.
     *
     * @return void
     * @throws GuzzleException
     * @throws dml_exception
     * @throws options_form_validation_error
     * @throws request_error
     */
    public function test_middleware_should_throw_options_form_validation_error(): void {
        $errors = ['my_hidden' => 'Required.'];
        $mock = new MockHandler([new Response(422, [], json_encode([
            'error_code' => error_code::invalid_options_form->value,
            'temporary' => true,
            'reason' => 'test',
            'errors' => $errors,
        ]))]);
        $client = new qpy_http_client(['mock' => HandlerStack::create($mock)]);

        try {
            $client->get('/');
        } catch (options_form_validation_error $error) {
            $this->assertEquals($errors, $error->errors);
            return;
        }
        $this->fail('Expected "options_form_validation_error" to be thrown.');
    }

    /**
     * Tests that the middleware respects the {@see RequestOptions::HTTP_ERRORS}-option.
     *
     * @return void
     * @throws GuzzleException
     * @throws dml_exception
     * @throws options_form_validation_error
     * @throws request_error
     */
    public function test_middleware_should_not_throw_if_appropriate_option_is_set(): void {
        $mock = new MockHandler([new Response(422, [], json_encode([
            'error_code' => error_code::invalid_request->value,
            'temporary' => true,
            'reason' => 'test',
        ]))]);
        $client = new qpy_http_client(['mock' => HandlerStack::create($mock)]);

        $client->get('/', [RequestOptions::HTTP_ERRORS => false]);
    }

    /**
     * Tests that the middleware throws the standard {@see GuzzleException} if there is an unknown body.
     *
     * @param string|null $body
     * @dataProvider unknown_body_provider
     * @throws GuzzleException
     * @throws options_form_validation_error
     * @throws dml_exception
     */
    public function test_middleware_should_throw_guzzle_exception_if_unknown_body(?string $body): void {
        $mock = new MockHandler([new Response(422, [], $body)]);
        $client = new qpy_http_client(['mock' => HandlerStack::create($mock)]);
        $this->expectException(GuzzleException::class);
        $client->get('/');
    }


    /**
     * Provides every {@see error_code}, alternating temporary-flag and a reason-string (or null).
     *
     * @return array
     */
    public static function valid_error_code_provider(): array {
        return array_map(
            function (error_code $code, int $index) {
                $temporary = $index % 2 === 0;
                return [$code, $temporary, $temporary ? null : 'reason'];
            },
            error_code::cases(),
            array_keys(error_code::cases()),
        );
    }

    /**
     * Provides request bodies which cannot be transformed into {@see request_error}s.
     *
     * @return array
     */
    public static function unknown_body_provider(): array {
        return [
            [null], // No request body.
            ['{'], // Invalid json body.
            ['string'], // Not an array.
            ['{}'], // No error code.
            ['{"error_code": "unknown"}'], // Unknown error code.
        ];
    }
}
