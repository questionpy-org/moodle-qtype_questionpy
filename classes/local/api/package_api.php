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

use coding_exception;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use moodle_exception;
use Psr\Http\Message\ResponseInterface;
use qtype_questionpy\exception\error_code;
use qtype_questionpy\exception\request_error;
use stored_file;

/**
 * Contains operations on the given package.
 *
 * This class takes care of transparently sending the package when it is not cached by the server,
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2023 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class package_api {
    /**
     * Initialize a new instance.
     *
     * @param qpy_http_client $client Guzzle client
     * @param string $hash package hash
     * @param stored_file|null $file package file or null. If this is not provided and the package is not available to
     *                               the server, operations will fail
     */
    public function __construct(
        /** @var qpy_http_client $client */
        private readonly qpy_http_client $client,
        /** @var string $hash */
        private readonly string $hash,
        /** @var stored_file|null $file */
        private readonly ?stored_file $file = null
    ) {
    }

    /**
     * Retrieve the question edit form definition.
     *
     * @param string|null $questionstate current question state
     * @return question_edit_form_response
     * @throws GuzzleException
     * @throws request_error
     * @throws moodle_exception
     */
    public function get_question_edit_form(?string $questionstate): question_edit_form_response {
        $options['multipart'] = $this->transform_to_multipart([], $questionstate);
        $response = $this->post_and_maybe_retry('/options', $options);
        return api_utils::convert_response_to_class($response, question_edit_form_response::class);
    }

    /**
     * Create or update a question from form data and current state, if any.
     *
     * @param string|null $currentstate current state string if the question already exists, null otherwise
     * @param object $formdata data from the question edit form
     * @return question_response
     * @throws GuzzleException
     * @throws request_error
     * @throws moodle_exception
     */
    public function create_question(?string $currentstate, object $formdata): question_response {
        $options['multipart'] = $this->transform_to_multipart(
            [
                'form_data' => $formdata,
                // TODO: Send an actual context.
                'context' => 1,
            ],
            $currentstate,
        );
        $response = $this->post_and_maybe_retry('/question', $options);
        return api_utils::convert_response_to_class($response, question_response::class);
    }

    /**
     * Start an attempt at an existing question.
     *
     * @param string $questionstate
     * @param int $variant variant which should be started (`1` for questions with only one variant)
     * @return attempt_started the attempt's state and metadata. Note that the attempt state never changes after the
     *                         attempt has been started.
     * @throws GuzzleException
     * @throws request_error
     * @throws moodle_exception
     */
    public function start_attempt(string $questionstate, int $variant): attempt_started {
        $options['multipart'] = $this->transform_to_multipart(['variant' => $variant], $questionstate);
        $response = $this->post_and_maybe_retry('/attempt/start', $options);
        return api_utils::convert_response_to_class($response, attempt_started::class);
    }

    /**
     * View a previously created attempt.
     *
     * @param string $questionstate
     * @param string $attemptstate the attempt state previously returned from {@see start_attempt()}
     * @param string|null $scoringstate the last scoring state if this attempt has already been scored
     * @param object|null $response data currently entered by the student
     * @return attempt the attempt's metadata. The state is not returned since it never changes.
     * @throws GuzzleException
     * @throws request_error
     * @throws moodle_exception
     */
    public function view_attempt(string $questionstate, string $attemptstate, ?string $scoringstate = null,
                                 ?object $response = null): attempt {
        $options['multipart'] = $this->transform_to_multipart(
            [
                'attempt_state' => $attemptstate,
                'scoring_state' => $scoringstate,
                'response' => $response,
            ],
            $questionstate,
        );
        $httpresponse = $this->post_and_maybe_retry('/attempt/view', $options);
        return api_utils::convert_response_to_class($httpresponse, attempt::class);
    }

    /**
     * Score an attempt.
     *
     * @param string $questionstate
     * @param string $attemptstate the attempt state previously returned from {@see start_attempt()}
     * @param string|null $scoringstate the last scoring state if this attempt had been scored before
     * @param object $response data submitted by the student
     * @return attempt_scored the attempt's metadata. The state is not returned since it never changes.
     * @throws GuzzleException
     * @throws request_error
     * @throws moodle_exception
     */
    public function score_attempt(string $questionstate, string $attemptstate, ?string $scoringstate,
                                  object $response): attempt_scored {
        $options['multipart'] = $this->transform_to_multipart(
            [
                'attempt_state' => $attemptstate,
                'scoring_state' => $scoringstate,
                'response' => $response,
                'generate_hint' => false,
            ],
            $questionstate
        );
        $httpresponse = $this->post_and_maybe_retry('/attempt/score', $options);
        return api_utils::convert_response_to_class($httpresponse, attempt_scored::class);
    }

    /**
     * Downloads the given static file to the given path.
     *
     * In the case of non-public static files, access control must be done by the caller.
     *
     * @param string $namespace namespace of the package from which to retrieve the file
     * @param string $shortname short name of the package from which to retrieve the file
     * @param string $kind `static` for now
     * @param string $path path of the static file in the package
     * @param string $targetpath path where the file should be downloaded to. Anything here will be overwritten.
     * @return string|null the mime type as reported by the server or null if the file wasn't found
     * @throws GuzzleException
     * @throws request_error
     * @throws coding_exception
     */
    public function download_static_file(string $namespace, string $shortname, string $kind, string $path,
                                         string $targetpath): ?string {
        try {
            $res = $this->post_and_maybe_retry(
                "/file/$namespace/$shortname/$kind/$path",
                ['sink' => $targetpath]
            );
        } catch (BadResponseException $e) {
            if ($e->getResponse()->getStatusCode() == 404) {
                // The static file was not found.
                return null;
            }

            throw new coding_exception(
                "Request to '{$e->getRequest()->getUri()}' unexpectedly returned status code " .
                "'{$e->getResponse()->getStatusCode()}'"
            );
        }

        if ($res->hasHeader('Content-Type')) {
            return $res->getHeader('Content-Type')[0];
        } else {
            debugging('Server did not send Content-Type header, falling back to application/octet-stream');
            return 'application/octet-stream';
        }
    }

    /**
     * Send a POST request and retry if the server doesn't have the package file cached, but we have it available.
     *
     * @param string $uri relative to the base url
     * @param array $options request options as per
     *                       {@link https://docs.guzzlephp.org/en/stable/request-options.html Guzzle docs}
     * @param bool $allowretry if set to false, retry won't be attempted if the package file isn't cached, instead
     *                         throwing a {@see coding_exception}
     * @return ResponseInterface
     * @throws GuzzleException
     * @throws request_error
     */
    private function post_and_maybe_retry(string $uri, array $options = [], bool $allowretry = true): ResponseInterface {
        $fulluri = "/packages/$this->hash/" . ltrim($uri, '/');

        try {
            return $this->client->post($fulluri, $options);
        } catch (request_error $e) {
            if (!$allowretry || !$this->file || $e->requesterrorcode !== error_code::package_not_found) {
                throw $e;
            }

            $fd = $this->file->get_content_file_handle();
            try {
                $options['multipart'][] = [
                    'name' => 'package',
                    'contents' => $fd,
                ];
                return $this->post_and_maybe_retry($uri, $options, allowretry: false);
            } finally {
                @fclose($fd);
            }
        }
    }

    /**
     * Creates the multipart parts array.
     *
     * NOTE:
     *  - Empty arrays at the two top levels of `$main` are serialized as JSON objects instead of arrays.
     *  - Null values are ignored in the final multipart array.
     *
     * @param array $main main JSON part
     * @param string|null $questionstate optional question state
     * @return array
     */
    private function transform_to_multipart(array $main, ?string $questionstate): array {
        if (!is_null($questionstate)) {
            $multipart[] = [
                'name' => 'question_state',
                'contents' => $questionstate,
            ];
        }

        $transformed = [];
        foreach ($main as $key => $value) {
            if (is_null($value)) {
                continue;
            }

            // Cast arrays to objects so that empty arrays get serialized to JSON objects, not arrays.
            $transformed[$key] = is_array($value) ? (object) $value : $value;
        }

        $multipart[] = [
            'name' => 'main',
            'contents' => json_encode((object) $transformed),
        ];

        return $multipart;
    }
}
