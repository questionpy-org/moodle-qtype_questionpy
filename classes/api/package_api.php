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

use moodle_exception;
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

    /** @var string */
    private string $hash;

    /** @var stored_file|null */
    private ?stored_file $file;

    /**
     * Initialize a new instance.
     *
     * @param string $hash           package hash
     * @param stored_file|null $file package file or null. If this is not provided and the package is not available to
     *                               the server, operations will fail
     */
    public function __construct(string $hash, ?stored_file $file = null) {
        $this->hash = $hash;
        $this->file = $file;
    }

    /**
     * Retrieve the question edit form definition.
     *
     * @param string|null $questionstate current question state
     * @return question_edit_form_response
     * @throws moodle_exception
     */
    public function get_form(?string $questionstate): question_edit_form_response {
        $parts = $this->create_request_parts([], $questionstate);

        $response = $this->post_and_maybe_retry("/options", $parts);
        return array_converter::from_array(question_edit_form_response::class, $response->get_data());
    }

    /**
     * Create or update a question from form data and current state, if any.
     *
     * @param string|null $currentstate current state string if the question already exists, null otherwise
     * @param object $formdata          data from the question edit form
     * @return question_response
     * @throws moodle_exception
     */
    public function create_question(?string $currentstate, object $formdata): question_response {
        $parts = $this->create_request_parts([
            "form_data" => $formdata,
            // TODO: Send an actual context.
            "context" => 1,
        ], $currentstate);

        $response = $this->post_and_maybe_retry("/question", $parts);
        return array_converter::from_array(question_response::class, $response->get_data());
    }

    /**
     * Creates the multipart parts array.
     *
     * @param array $main        main JSON part
     * @param string|null $state optional question state. If provided, the hash will be calculated and added to `main`.
     * @return array
     */
    private function create_request_parts(array $main, ?string $state): array {
        $parts = [];

        if ($state !== null) {
            $statehash = hash("sha256", $state);
            $main["question_state_hash"] = $statehash;
            // TODO: Don't send the question state unconditionally, try the hash first.
            $parts["question_state"] = $state;
        }

        $parts["main"] = json_encode($main);
        return $parts;
    }

    /**
     * Send a POST request and retry if the server doesn't have the package file cached but we have it available.
     *
     * @param string $subpath path relative to `/packages/hash...`
     * @param array $parts    array of multipart parts
     * @return http_response_container
     * @throws moodle_exception
     */
    private function post_and_maybe_retry(string $subpath, array $parts): http_response_container {
        $connector = connector::default();
        $path = "/packages/$this->hash$subpath";

        $response = $connector->post($path, $parts);
        if ($this->file && $response->code == 404) {
            $json = $response->get_data();
            if ($json["package_not_found"]) {
                // Add file to parts and resend.
                $fs = get_file_storage();
                $filepath = $fs->get_file_system()->get_local_path_from_storedfile($this->file);

                $parts["package"] = curl_file_create($filepath, "application/zip");

                $response = $connector->post($path, $parts);
            }
        }

        $response->assert_2xx();
        return $response;
    }
}
