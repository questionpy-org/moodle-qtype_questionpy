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

namespace qtype_questionpy\form\elements;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . "/../../data_provider.php");

use qtype_questionpy\array_converter\array_converter;
use function qtype_questionpy\element_provider;

/**
 * Tests of the (de)serialization of form elements.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element_json_test extends \advanced_testcase {
    /**
     * Serializes values and compares resulting JSON to an expected file.
     *
     * @param string $elementkind element kind, which the json file name is based on
     * @param mixed $expected     the value expected after deserialization
     * @dataProvider deserialize_provider
     * @covers       \qtype_questionpy\form\elements
     */
    public function test_deserialize(string $elementkind, $expected): void {
        $json = file_get_contents(__DIR__ . "/json/" . $elementkind . ".json");

        $array = json_decode($json, true);
        $actual = array_converter::from_array(form_element::class, $array);

        $this->assertEquals($expected, $actual);
    }

    /**
     * Deserializes values from files and compares resulting object to an expected value.
     *
     * @param string $elementkind element kind, which the json file name is based on
     * @param mixed $value        the value to serialize
     * @dataProvider serialize_provider
     * @covers       \qtype_questionpy\form\elements
     */
    public function test_serialize(string $elementkind, $value): void {
        $array = array_converter::to_array($value);
        $actualjson = json_encode($array);
        $expectedjsonfilename = __DIR__ . "/json/" . $elementkind . ".json";

        $this->assertJsonStringEqualsJsonFile($expectedjsonfilename, $actualjson);
    }

    /**
     * Provider of argument pairs for {@see test_deserialize}.
     */
    public function deserialize_provider(): array {
        return [
            ...$this->serialize_provider(),
            // TODO: also test defaults.
        ];
    }

    /**
     * Provider of argument pairs for {@see test_serialize}.
     */
    public function serialize_provider(): array {
        return element_provider();
    }
}
