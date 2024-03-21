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
use qtype_questionpy\package\package_raw;
use TypeError;

/**
 * Helper class for communicating to the application server.
 *
 * @package    qtype_questionpy
 * @copyright  2022 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {

    /**
     * Retrieves QuestionPy packages from the application server.
     *
     * @return package_raw[]
     * @throws moodle_exception
     */
    public function get_packages(): array {
        $connector = connector::default();
        $response = $connector->get('/packages');
        $response->assert_2xx();
        $packages = $response->get_data();

        $result = [];
        foreach ($packages as $package) {
            try {
                $result[] = array_converter::from_array(package_raw::class, $package);
            } catch (TypeError $e) {
                // TODO: decide what to do with faulty package.
                debugging($e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Retrieves the package with the given hash, returns null if not found.
     *
     * @param string $hash the hash of the package to get
     * @return ?package_raw the package with the given hash or null if not found
     * @throws moodle_exception
     */
    public function get_package(string $hash): ?package_raw {
        $connector = connector::default();
        $response = $connector->get("/packages/$hash");

        if ($response->code === 404) {
            return null;
        }
        $response->assert_2xx();

        return array_converter::from_array(package_raw::class, $response->get_data());
    }

    /**
     * Retrieve the question edit form definition for a given package.
     *
     * @param string $packagehash   package whose form should be requested
     * @param string|null $questionstate current question state
     * @return question_edit_form_response
     * @throws moodle_exception
     */
    public function get_question_edit_form(string $packagehash, ?string $questionstate): question_edit_form_response {
        $connector = connector::default();

        $parts = [
            "main" => "{}",
        ];

        if ($questionstate !== null) {
            $parts["question_state"] = $questionstate;
        }

        $response = $connector->post("/packages/$packagehash/options", $parts);
        $response->assert_2xx();
        return array_converter::from_array(question_edit_form_response::class, $response->get_data());
    }

    /**
     * Create or update a question from form data and current state, if any.
     *
     * @param string $packagehash
     * @param string|null $currentstate current state string if the question already exists, null otherwise
     * @param object $formdata data from the question edit form
     * @return question_response
     * @throws moodle_exception
     */
    public function create_question(string $packagehash, ?string $currentstate, object $formdata): question_response {
        $connector = connector::default();

        $main = [
            "form_data" => $formdata,
            // TODO: Send an actual context.
            "context" => 1,
        ];
        $parts = [];

        if ($currentstate !== null) {
            $parts["question_state"] = $currentstate;
        }

        $parts["main"] = json_encode($main);

        $response = $connector->post("/packages/$packagehash/question", $parts);
        $response->assert_2xx();
        return array_converter::from_array(question_response::class, $response->get_data());
    }

    /**
     * Start an attempt at an existing question.
     *
     * @param string $packagehash
     * @param string $questionstate
     * @param int $variant variant which should be started (`1` for questions with only one variant)
     * @return attempt_started the attempt's state and metadata. Note that the attempt state never changes after the
     *                         attempt has been started.
     * @throws moodle_exception
     */
    public function start_attempt(string $packagehash, string $questionstate, int $variant): attempt_started {
        $connector = connector::default();

        $main = [
            "variant" => $variant,
        ];
        $parts = [
            "main" => json_encode($main),
            "question_state" => $questionstate,
        ];

        $response = $connector->post("/packages/$packagehash/attempt/start", $parts);
        $response->assert_2xx();
        return array_converter::from_array(attempt_started::class, $response->get_data());
    }

    /**
     * View a previously created attempt.
     *
     * @param string $packagehash
     * @param string $questionstate
     * @param string $attemptstate the attempt state previously returned from {@see start_attempt()}
     * @param string|null $scoringstate the last scoring state if this attempt has already been scored
     * @param array|null $response data currently entered by the student
     * @return attempt the attempt's metadata. The state is not returned since it never changes.
     * @throws moodle_exception
     */
    public function view_attempt(string $packagehash, string $questionstate, string $attemptstate,
                                 ?string $scoringstate = null, ?array $response = null): attempt {
        $connector = connector::default();

        $main = [
            "attempt_state" => $attemptstate,
            "response" => $response,
        ];

        if ($scoringstate) {
            $main["scoring_state"] = $scoringstate;
        }

        $parts = [
            "main" => json_encode($main),
            "question_state" => $questionstate,
        ];

        $response = $connector->post("/packages/$packagehash/attempt/view", $parts);
        $response->assert_2xx();
        return array_converter::from_array(attempt::class, $response->get_data());
    }

    /**
     * Score an attempt.
     *
     * @param string $packagehash
     * @param string $questionstate
     * @param string $attemptstate the attempt state previously returned from {@see start_attempt()}
     * @param string|null $scoringstate the last scoring state if this attempt had been scored before
     * @param array $response data submitted by the student
     * @return attempt_scored the attempt's metadata. The state is not returned since it never changes.
     * @throws moodle_exception
     */
    public function score_attempt(string $packagehash, string $questionstate, string $attemptstate,
                                  ?string $scoringstate, array $response): attempt_scored {
        $connector = connector::default();

        $main = [
            "attempt_state" => $attemptstate,
            "response" => $response,
            "generate_hint" => false,
        ];

        if ($scoringstate) {
            $main["scoring_state"] = $scoringstate;
        }

        $parts = [
            "main" => json_encode($main),
            "question_state" => $questionstate,
        ];

        $response = $connector->post("/packages/$packagehash/attempt/score", $parts);
        $response->assert_2xx();
        return array_converter::from_array(attempt_scored::class, $response->get_data());
    }

    /**
     * Get a {@see package_raw} from a file.
     *
     * @param string $filepath
     * @return package_raw
     * @throws moodle_exception
     */
    public static function extract_package_info(string $filepath): package_raw {
        $connector = connector::default();

        $data = [
            'package' => curl_file_create($filepath),
        ];

        $response = $connector->post("/package-extract-info", $data);
        $response->assert_2xx();
        return array_converter::from_array(package_raw::class, $response->get_data());
    }

    /**
     * Get the status and information from the server.
     *
     * @return status
     * @throws moodle_exception
     */
    public static function get_server_status(): status {
        $connector = connector::default();

        $response = $connector->get("/status");
        $response->assert_2xx();
        return array_converter::from_array(status::class, $response->get_data());
    }
}
