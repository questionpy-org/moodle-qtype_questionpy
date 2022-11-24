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

use moodle_exception;
use qtype_questionpy\array_converter\array_converter;
use qtype_questionpy\form\qpy_form;
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
     * Initializes new connector with current server url.
     *
     * @throws moodle_exception
     */
    private static function create_connector(): connector {
        // Get server configs.
        $serverurl = get_config('qtype_questionpy', 'server_url');
        $timeout = get_config('qtype_questionpy', 'server_timeout');
        return new connector($serverurl, $timeout);
    }

    /**
     * Retrieves QuestionPy packages from the application server.
     *
     * @return package[]
     * @throws moodle_exception
     */
    public static function get_packages(): array {
        // Retrieve packages from server.
        $connector = self::create_connector();
        $response = $connector->get('/packages');

        // TODO: check response code.
        $packages = $response->get_data();

        $result = [];

        foreach ($packages as $package) {
            try {
                $result[] = array_converter::from_array(package::class, $package);
            } catch (TypeError $e) {
                // TODO: decide what to do with faulty package.
                continue;
            }
        }

        return $result;
    }

    /**
     * Retrieve the question edit form definition for a given package.
     *
     * @param string $packagehash  package whose form should be requested
     * @param array $questionstate current question state, or an empty array for new questions
     * @return qpy_form
     * @throws moodle_exception
     */
    public static function get_question_edit_form(string $packagehash, array $questionstate): qpy_form {
        $connector = self::create_connector();

        // TODO: Don't send the question state unconditionally, try the hash first.
        $statestr = json_encode($questionstate);
        $statehash = hash("sha256", $statestr);

        $response = $connector->post("/packages/$packagehash/options", [
            "main" => json_encode([
                "question_state_hash" => $statehash,
                // TODO: Send an actual context.
                "context" => 1
            ]),
            "question_state" => $statestr
        ]);
        $response->assert_2xx();
        return array_converter::from_array(qpy_form::class, $response->get_data());
    }

    /**
     * Hello world example.
     *
     * @return string
     * @throws moodle_exception
     */
    public static function get_hello_world(): string {
        $connector = self::create_connector();
        $response = $connector->get('/helloworld');
        return $response->get_data(false);
    }

}
