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

namespace qtype_questionpy\api;

use core\http_client;
use dml_exception;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\HandlerStack;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use qtype_questionpy\exception\error_code;
use qtype_questionpy\exception\options_form_validation_error;
use qtype_questionpy\exception\request_error;

/**
 * Guzzle http client configured with Moodle's standards ({@see http_client}) and QPy-specific ones.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2024 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qpy_http_client extends http_client {
    /**
     * Initializes a new client.
     *
     * @param array $config Guzzle config options. Mock handlers and history middleware can be added here, see
     *                      {@link https://docs.guzzlephp.org/en/stable/testing.html#mock-handler}.
     * @throws dml_exception
     */
    public function __construct(array $config = []) {
        $config['base_uri'] ??= rtrim(get_config('qtype_questionpy', 'server_url'), '/') . '/';
        $config[RequestOptions::TIMEOUT] ??= get_config('qtype_questionpy', 'server_timeout');
        parent::__construct($config);
    }

    // phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found
    // We override the `get`- and `post`-method to include our custom `@throws` in the docstrings of these methods.
    /**
     * Create and send an HTTP GET request.
     *
     * Use an absolute path to override the base path of the client, or a relative path to append to the base path of the client.
     * The URL can contain the query string as well.
     *
     * @param UriInterface|string $uri
     * @param array $options
     * @return ResponseInterface
     * @throws GuzzleException
     * @throws request_error
     * @throws options_form_validation_error
     */
    public function get($uri, array $options = []): ResponseInterface {
        return parent::get($uri, $options);
    }

    /**
     * Create and send an HTTP POST request.
     *
     * Use an absolute path to override the base path of the client, or a relative path to append to the base path of the client.
     * The URL can contain the query string as well.
     *
     * @param UriInterface|string $uri
     * @param array $options
     * @return ResponseInterface
     * @throws GuzzleException
     * @throws request_error
     * @throws options_form_validation_error
     */
    public function post($uri, array $options = []): ResponseInterface {
        return parent::post($uri, $options);
    }
    // phpcs:enable

    /**
     * Get the handler stack according to the settings/options from client.
     *
     * @param array $settings The settings or options from client.
     * @return HandlerStack
     */
    protected function get_handlers(array $settings): HandlerStack {
        $handlerstack = parent::get_handlers($settings);
        /* This checks requests against Moodle's curlsecurityblockedhosts, which we don't want, since admins would need
           to ensure their QPy server isn't in this list otherwise. There may be ways to granularly allow the
           server_url, but this will do for now. */
        $handlerstack->remove('moodle_check_initial_request');
        $handlerstack->before('http_errors', $this->exception_middleware(), 'qpy_exception_middleware');
        return $handlerstack;
    }

    /**
     * This middleware transforms responses with a 4xx or 5xx status code to our custom {@see request_error}.
     *
     * @return callable middleware callback
     */
    private static function exception_middleware(): callable {
        return function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                return $handler($request, $options)->then(
                    function (ResponseInterface $response) {
                        return $response;
                    },
                    function (GuzzleException $exception) {
                        if (!($exception instanceof RequestException) || !$exception->hasResponse()) {
                            // TODO: also handle other guzzle exceptions like `ConnectException`?
                            throw $exception;
                        }

                        $body = $exception->getResponse()->getBody()->getContents();
                        $data = json_decode($body, associative: true);
                        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                            throw $exception;
                        }

                        $errorcode = error_code::tryFrom($data['error_code'] ?? '');
                        if ($errorcode === null) {
                            throw $exception;
                        }

                        if ($errorcode === error_code::invalid_options_form) {
                            throw new options_form_validation_error(
                                $exception,
                                $errorcode,
                                $data['temporary'] ?? false,
                                $data['reason'] ?? null,
                                $data['errors'] ?? [],
                            );
                        }

                        throw new request_error(
                            $exception,
                            $errorcode,
                            $data['temporary'] ?? false,
                            $data['reason'] ?? null,
                        );
                    },
                );
            };
        };
    }
}
