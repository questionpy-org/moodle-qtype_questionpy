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

use qtype_questionpy\array_converter\array_converter;
use qtype_questionpy\form\conditions\does_not_equal;
use qtype_questionpy\form\conditions\equals;
use qtype_questionpy\form\conditions\in;
use qtype_questionpy\form\conditions\is_checked;
use qtype_questionpy\form\conditions\is_not_checked;

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
     * @param string $jsonfile path relative to `./json/` containing the JSON that should be parsed
     * @param mixed $expected  the value expected after deserialization
     * @dataProvider deserialize_provider
     * @covers       \qtype_questionpy\form\elements
     */
    public function test_deserialize(string $jsonfile, $expected): void {
        $json = file_get_contents(__DIR__ . "/json/" . $jsonfile);

        $array = json_decode($json, true);
        $actual = array_converter::from_array(form_element::class, $array);

        $this->assertEquals($expected, $actual);
    }

    /**
     * Deserializes values from files and compares resulting object to an expected value.
     *
     * @param string $expectedjsonfile path relative to `./json/` containing the JSON expected after serialization
     * @param mixed $value             the value to serialize
     * @dataProvider serialize_provider
     * @covers       \qtype_questionpy\form\elements
     */
    public function test_serialize(string $expectedjsonfile, $value): void {
        $array = array_converter::to_array($value);
        $actualjson = json_encode($array);

        $this->assertJsonStringEqualsJsonFile(__DIR__ . "/json/" . $expectedjsonfile, $actualjson);
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
        return [
            ["checkbox.json", (new checkbox_element("my_checkbox", "Left", "Right", true, true))->disable_if(
                new is_checked("chk1")
            )],
            ["checkbox_group.json", new checkbox_group_element(
                new checkbox_element(
                    "my_checkbox", "Left", "Right",
                    true, true
                )
            )],
            ["group.json", (new group_element("my_group", "Name", [
                new text_input_element("first_name", "", true, null, "Vorname"),
                new text_input_element("last_name", "", false, null, "Nachname (optional)"),
            ]))->hide_if(new is_not_checked("chk1"))],
            ["hidden.json", (new hidden_element("my_hidden_value", "42"))->disable_if(new equals("input1", 7))],
            ["radio_group.json", (new radio_group_element("my_radio", "Label", [
                new option("Option 1", "opt1", true),
                new option("Option 2", "opt2"),
            ], true))->disable_if(new does_not_equal("input1", ""))],
            ["repetition.json", new repetition_element(3, 2, "", [
                new text_input_element("item", ""),
            ])],
            ["select.json", (new select_element("my_select", "Label", [
                new option("Option 1", "opt1", true),
                new option("Option 2", "opt2"),
            ], true, true))->disable_if(new in("input1", ["valid", "also valid"]))],
            ["static_text.json", new static_text_element("my_text", "Label", "Lorem ipsum dolor sit amet.")],
            ["input.json", new text_input_element("my_field", "Label", true, "default", "placeholder")],
        ];
    }
}
