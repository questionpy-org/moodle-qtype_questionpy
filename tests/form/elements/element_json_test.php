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
     * @dataProvider deserialize_provider
     * @covers       \qtype_questionpy\form\elements
     */
    public function test_deserialize(string $jsonfile, $expected): void {
        $json = file_get_contents(__DIR__ . "/json/" . $jsonfile);

        $array = json_decode($json, true);
        $actual = form_element::from_array_any($array);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider serialize_provider
     * @covers       \qtype_questionpy\form\elements
     */
    public function test_serialize(string $expectedjsonfile, $value): void {
        $actualjson = json_encode($value);

        $this->assertJsonStringEqualsJsonFile(__DIR__ . "/json/" . $expectedjsonfile, $actualjson);
    }

    public function deserialize_provider(): array {
        return [
            ...$this->serialize_provider(),
            // TODO: also test defaults.
        ];
    }

    public function serialize_provider(): array {
        return [
            ["checkbox.json", new checkbox_element("my_checkbox", "Left", "Right", true, true)],
            ["checkbox_group.json", new checkbox_group_element(
                new checkbox_element(
                    "my_checkbox", "Left", "Right",
                    true, true
                )
            )],
            ["group.json", new group_element("my_group", "Name", [
                new text_input_element("first_name", "", true, null, "Vorname"),
                new text_input_element("last_name", "", false, null, "Nachname (optional)"),
            ])],
            ["hidden.json", new hidden_element("my_hidden_value", "42")],
            ["radio_group.json", new radio_group_element("my_radio", "Label", [
                new option("Option 1", "opt1", true),
                new option("Option 2", "opt2"),
            ], true)],
            ["select.json", new select_element("my_select", "Label", [
                new option("Option 1", "opt1", true),
                new option("Option 2", "opt2"),
            ], true, true)],
            ["static_text.json", new static_text_element("Label", "Lorem ipsum dolor sit amet.")],
            ["text_input.json", new text_input_element("my_field", "Label", true, "default", "placeholder")],
        ];
    }
}
