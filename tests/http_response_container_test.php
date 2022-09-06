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

namespace qtype_questionpy;


use moodle_exception;

/**
 * Unit tests for the questionpy question type class.
 *
 * @package    qtype_questionpy
 * @copyright  2022 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class http_response_container_test extends \advanced_testcase {

    /**
     * @covers \http_response_container::get_data
     * @return void
     * @throws moodle_exception
     */
    public function test_get_data_with_json(): void {
        $code = 200;
        $data = '{"test": "data"}';
        $dataarray = json_decode($data, true);

        $response = new http_response_container($code, $data);

        // Check if the data is string.
        $responsedata = $response->get_data(false);
        self::assertIsString($responsedata);
        self::assertEquals($data, $responsedata);

        // Check if the data is array.
        $responsedata = $response->get_data();
        self::assertIsArray($responsedata);
        self::assertEquals($dataarray, $responsedata);
    }

    /**
     * @covers \http_response_container::get_data
     * @return void
     * @throws moodle_exception
     */
    public function test_get_data_not_json() {
        $code = 200;
        $data = 'This is not a json.';

        $response = new http_response_container($code, $data);

        // Check if the data is string.
        $responsedata = $response->get_data(false);
        self::assertIsString($responsedata);
        self::assertEquals($data, $responsedata);

        // Check if parsing the data as json throws an exception.
        self::expectException(moodle_exception::class);
        $response->get_data();
    }

}
