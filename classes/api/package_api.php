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

use coding_exception;
use core\http_client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\InvalidArgumentException;
use GuzzleHttp\Utils;
use moodle_exception;
use Psr\Http\Message\ResponseInterface;
use qtype_questionpy\array_converter\array_converter;
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
     * @throws moodle_exception
     */
    public function get_question_edit_form(?string $questionstate): question_edit_form_response {
        $parts = $this->create_request_parts([
            'main' => '{}',
        ], $questionstate);

        $response = $this->post_and_maybe_retry('/options', $parts);
        return array_converter::from_array(question_edit_form_response::class, $response->get_data());
    }

    /**
     * Create or update a question from form data and current state, if any.
     *
     * @param string|null $currentstate current state string if the question already exists, null otherwise
     * @param object $formdata data from the question edit form
     * @return question_response
     * @throws moodle_exception
     */
    public function create_question(?string $currentstate, object $formdata): question_response {
        $parts = $this->create_request_parts([
            'form_data' => $formdata,
            // TODO: Send an actual context.
            'context' => 1,
        ], $currentstate);

        $response = $this->post_and_maybe_retry('/question', $parts);
        return array_converter::from_array(question_response::class, $response->get_data());
    }

    /**
     * Start an attempt at an existing question.
     *
     * @param string $questionstate
     * @param int $variant variant which should be started (`1` for questions with only one variant)
     * @return attempt_started the attempt's state and metadata. Note that the attempt state never changes after the
     *                         attempt has been started.
     * @throws moodle_exception
     */
    public function start_attempt(string $questionstate, int $variant): attempt_started {
        $parts = $this->create_request_parts([
            'variant' => $variant,
        ], $questionstate);

        $response = $this->post_and_maybe_retry('/attempt/start', $parts);
        return array_converter::from_array(attempt_started::class, $response->get_data());
    }

    /**
     * View a previously created attempt.
     *
     * @param string $questionstate
     * @param string $attemptstate the attempt state previously returned from {@see start_attempt()}
     * @param string|null $scoringstate the last scoring state if this attempt has already been scored
     * @param array|null $response data currently entered by the student
     * @return attempt the attempt's metadata. The state is not returned since it never changes.
     * @throws moodle_exception
     */
    public function view_attempt(string $questionstate, string $attemptstate, ?string $scoringstate = null,
                                 ?array $response = null): attempt {
        $main = ['attempt_state' => $attemptstate];
        // Cast to object so empty responses are serialized as JSON objects, not arrays.
        if ($response !== null) {
            $main['response'] = (object)$response;
        }

        if ($scoringstate) {
            $main['scoring_state'] = $scoringstate;
        }
        $parts = $this->create_request_parts($main, $questionstate);
        $httpresponse = $this->post_and_maybe_retry('/attempt/view', $parts);
        return array_converter::from_array(attempt::class, $httpresponse->get_data());
    }

    /**
     * Score an attempt.
     *
     * @param string $questionstate
     * @param string $attemptstate the attempt state previously returned from {@see start_attempt()}
     * @param string|null $scoringstate the last scoring state if this attempt had been scored before
     * @param array $response data submitted by the student
     * @return attempt_scored the attempt's metadata. The state is not returned since it never changes.
     * @throws moodle_exception
     */
    public function score_attempt(string $questionstate, string $attemptstate, ?string $scoringstate,
                                  array $response): attempt_scored {
        $main = [
            'attempt_state' => $attemptstate,
            // Cast to object so empty responses are serialized as JSON objects, not arrays.
            'response' => (object)$response,
            'generate_hint' => false,
        ];

        if ($scoringstate) {
            $main['scoring_state'] = $scoringstate;
        }

        $parts = $this->create_request_parts($main, $questionstate);
        $httpresponse = $this->post_and_maybe_retry('/attempt/score', $parts);
        return array_converter::from_array(attempt_scored::class, $httpresponse->get_data());
    }

    /**
     * Send a POST request and retry if the server doesn't have the package file cached, but we have it available.
     *
     * @param string $uri can be absolute or relative to the base url
     * @param array $options request options as per
     *                       {@link https://docs.guzzlephp.org/en/stable/request-options.html Guzzle docs}
     * @param bool $allowretry if set to false, retry won't be attempted if the package file isn't cached, instead
     *                         throwing a {@see coding_exception}
     * @return ResponseInterface
     * @throws coding_exception if the request is unsuccessful for any other reason
     * @see post_and_maybe_retry
     */
    private function guzzle_post_and_maybe_retry(string $uri, array $options = [], bool $allowretry = true): ResponseInterface {
        try {
            return $this->client->post($uri, $options);
        } catch (BadResponseException $e) {
            if (!$allowretry || !$this->file || $e->getResponse()->getStatusCode() != 404) {
                throw $e;
            }

            try {
                $json = Utils::jsonDecode($e->getResponse()->getBody(), assoc: true);
            } catch (InvalidArgumentException) {
                // Not valid JSON, so the problem probably isn't a missing package file.
                throw $e;
            }

            if ($json['what'] ?? null !== 'PACKAGE') {
                throw $e;
            }

            // Add file to parts and resend.

            $fd = $this->file->get_content_file_handle();
            try {
                $options["multipart"][] = [
                    "name" => "package",
                    "contents" => $fd,
                ];

                return $this->guzzle_post_and_maybe_retry($uri, $options, allowretry: false);
            } finally {
                @fclose($fd);
            }
        } catch (GuzzleException $e) {
            throw new coding_exception("Request to QPy server failed: " . $e->getMessage());
        }
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
     * @throws coding_exception
     */
    public function download_static_file(string $namespace, string $shortname, string $kind, string $path,
                                         string $targetpath): ?string {
        try {
            $res = $this->guzzle_post_and_maybe_retry(
                "/packages/$this->hash/file/$namespace/$shortname/$kind/$path",
                ["sink" => $targetpath]
            );
        } catch (BadResponseException $e) {
            if ($e->getResponse()->getStatusCode() == 404) {
                return null;
            }

            throw new coding_exception(
                "Request to '{$e->getRequest()->getUri()}' unexpectedly returned status code " .
                "'{$e->getResponse()->getStatusCode()}'"
            );
        }

        if ($res->hasHeader("Content-Type")) {
            return $res->getHeader("Content-Type")[0];
        } else {
            debugging("Server did not send Content-Type header, falling back to application/octet-stream");
            return "application/octet-stream";
        }
    }

    /**
     * Creates the multipart parts array.
     *
     * @param array $main main JSON part
     * @param string|null $questionstate optional question state
     * @return array
     */
    private function create_request_parts(array $main, ?string $questionstate): array {
        $parts = [];

        if ($questionstate !== null) {
            $parts['question_state'] = $questionstate;
        }

        $parts['main'] = json_encode($main);
        return $parts;
    }

    /**
     * Send a POST request and retry if the server doesn't have the package file cached, but we have it available.
     *
     * @param string $subpath path relative to `/packages/hash...`
     * @param array $parts    array of multipart parts
     * @return http_response_container
     * @throws moodle_exception
     * @see guzzle_post_and_maybe_retry
     */
    private function post_and_maybe_retry(string $subpath, array $parts): http_response_container {
        $connector = connector::default();
        $path = "/packages/$this->hash/" . ltrim($subpath, '/');

        $response = $connector->post($path, $parts);
        if ($this->file && $response->code == 404) {
            $json = $response->get_data();
            if ($json['what'] === 'PACKAGE') {
                // Add file to parts and resend.
                $fs = get_file_storage();
                $filepath = $fs->get_file_system()->get_local_path_from_storedfile($this->file, true);

                $parts['package'] = curl_file_create($filepath, 'application/zip');

                $response = $connector->post($path, $parts);
            }
        }

        $response->assert_2xx();
        return $response;
    }
}
