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

use coding_exception;
use dml_exception;
use stdClass;

/**
 * Unit tests for {@see question_db_utils}.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_db_utils_test extends \advanced_testcase {

    /**
     * @throws dml_exception
     * @throws coding_exception
     */
    public function test_get_question_should_load_options() {
        list($hash, $statestr) = $this->setup_question();

        $result = question_db_utils::get_question(1);

        $this->assertEquals(
            (object)[
                "qpy_package_hash" => $hash,
                "qpy_state" => $statestr,
                "qpy_form_opt1" => "opt 1 value"
            ], $result
        );
    }

    /**
     * @throws dml_exception
     * @throws coding_exception
     */
    public function test_get_question_should_return_empty_object_when_no_record() {
        $this->assertEquals(new stdClass(), question_db_utils::get_question(42));
    }

    /**
     * @throws coding_exception
     * @throws dml_exception
     */
    private function setup_question(): array {
        $this->resetAfterTest();

        $hash = "902c466885bca84ac678f7d7345b93f5b3f4d9d3bc6d5d502aa3e178e90fcb57";
        $statestr = '
        {
          "opt1": "opt 1 value"
        }
        ';

        global $DB;
        $packageid = (new package(
            $hash, "test", ["en" => "Test"],
            "0.1.0", "QUESTION_TYPE"
        )
        )->store_in_db();
        $DB->insert_record("qtype_questionpy", [
            "id" => 1,
            "questionid" => 1,
            "feedback" => "",
            "packageid" => $packageid,
            "state" => $statestr
        ]);

        return [$hash, $statestr];
    }
}
