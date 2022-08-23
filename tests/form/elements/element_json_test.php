<?php

namespace qtype_questionpy\form\elements;

class element_json_test extends \advanced_testcase
{
    /**
     * @dataProvider deserialize_provider
     */
    public function test_deserialize(string $json_file, $expected): void
    {
        $json = file_get_contents(__DIR__ . "/json/" . $json_file);

        $array = json_decode($json, true);
        $actual = form_element::from_array_any($array);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider serialize_provider
     */
    public function test_serialize(string $expected_json_file, $value): void
    {
        $actual_json = json_encode($value);

        $this->assertJsonStringEqualsJsonFile(__DIR__ . "/json/" . $expected_json_file, $actual_json);
    }

    public function deserialize_provider(): array
    {
        return [
            ...$this->serialize_provider(),
            // TODO: also test defaults
        ];
    }

    public function serialize_provider(): array
    {
        return [
            ["checkbox.json", new checkbox_element("my_checkbox", "Left", "Right", true, true)],
            ["checkbox_group.json", new checkbox_group_element(
                new checkbox_element(
                    "my_checkbox", "Left", "Right",
                    true, true
                )
            )],
            ["group.json", new group_element("my_group", "Name", new form_elements(
                new text_input_element("first_name", "", true, null, "Vorname"),
                new text_input_element("last_name", "", false, null, "Nachname (optional)"),
            ))],
            ["hidden.json", new hidden_element("my_hidden_value", "42")],
            ["radio_group.json", new radio_group_element(
                "my_radio", "Label", new options(
                new option("Option 1", "opt1", true),
                new option("Option 2", "opt2"),
            ), true
            )],
            ["select.json", new select_element(
                "my_select", "Label", new options(
                new option("Option 1", "opt1", true),
                new option("Option 2", "opt2"),
            ), true, true
            )],
            ["static_text.json", new static_text_element("Label", "Lorem ipsum dolor sit amet.")],
            ["text_input.json", new text_input_element("my_field", "Label", true, "default", "placeholder")],
        ];
    }
}
