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

/**
 * This file has data provider functions for unit tests.
 *
 * @package    qtype_questionpy
 * @copyright  2022 Alexander Schmitz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_questionpy;

use moodle_exception;
use qtype_questionpy\array_converter\array_converter;
use qtype_questionpy\form\conditions\does_not_equal;
use qtype_questionpy\form\conditions\equals;
use qtype_questionpy\form\conditions\in;
use qtype_questionpy\form\conditions\is_checked;
use qtype_questionpy\form\conditions\is_not_checked;
use qtype_questionpy\form\elements\checkbox_element;
use qtype_questionpy\form\elements\checkbox_group_element;
use qtype_questionpy\form\elements\group_element;
use qtype_questionpy\form\elements\hidden_element;
use qtype_questionpy\form\elements\option;
use qtype_questionpy\form\elements\radio_group_element;
use qtype_questionpy\form\elements\repetition_element;
use qtype_questionpy\form\elements\select_element;
use qtype_questionpy\form\elements\static_text_element;
use qtype_questionpy\form\elements\text_area_element;
use qtype_questionpy\form\elements\text_input_element;
use qtype_questionpy\package\package_raw;


/**
 * Returns a raw package object which can be modified by an array of attributes.
 *
 * The languages array gets generated when it is not set inside {@see $attributes}.
 *
 * @param array $attributes
 * @return package_raw
 * @throws moodle_exception
 */
function package_provider(array $attributes = []): package_raw {
    $data = array_merge([
        'short_name' => 'my_short_name',
        'namespace' => 'my_namespace',
        'name' => [
            'en' => 'en: My Name',
            'de' => 'de: My Name',
        ],
        'version' => '0.1.0',
        'type' => 'questiontype',
        'author' => 'John Doe',
        'url' => 'http://www.example.com/',
        'languages' => ['en', 'de'],
        'description' => [
            'en' => 'en: Lorem ipsum dolor sit amet.',
            'de' => 'de: Lorem ipsum dolor sit amet.',
        ],
        'icon' => 'https://placehold.jp/40e47e/598311/150x150.png',
        'license' => 'MIT',
        'tags' => ['my_tag_0', 'my_tag_1', 'my_tag_2'],
    ], $attributes);

    // Create 'languages' array based on provided 'name' and 'description' translations if none is provided.
    if ((isset($attributes['name']) || isset($attributes['description'])) && !isset($attributes['languages'])) {
        foreach (['name', 'description'] as $field) {
            $data['languages'] = array_merge($data['languages'], array_keys($data[$field]));
        }
        $data['languages'] = array_values(array_unique($data['languages']));
    }

    // Calculate package hash if none is provided.
    if (!isset($attributes['package_hash'])) {
        $data['package_hash'] = hash('sha256', $data['short_name'] . $data['namespace'] . $data['version']);
    }

    return array_converter::from_array(package_raw::class, $data);
}

/**
 * Provides a number of elements for tests.
 *
 * @return array[] array of [element kind, element] pairs
 */
function element_provider(): array {
    return [
        ["checkbox", (new checkbox_element("my_checkbox", "Left", "Right", true, true))
            ->disable_if(new is_checked("chk1"))
            ->help("Help text"),
        ],
        ["checkbox_group", new checkbox_group_element(
            (new checkbox_element(
                "my_checkbox",
                "Left",
                "Right",
                true,
                true
            ))->help("Help text")
        ),
        ],
        ["group", (new group_element("my_group", "Name", [
            new text_input_element("first_name", "", true, null, "Vorname"),
            new text_input_element("last_name", "", false, null, "Nachname (optional)"),
        ]))
            ->hide_if(new is_not_checked("chk1"))
            ->help("Help text"),
        ],
        ["hidden", (new hidden_element("my_hidden_value", "42"))->disable_if(new equals("input1", 7))],
        ["radio_group", (new radio_group_element("my_radio", "Label", [
            new option("Option 1", "opt1", true),
            new option("Option 2", "opt2"),
        ], true))->disable_if(new does_not_equal("input1", "")),
        ],
        ["repetition", new repetition_element("my_rep", 3, 2, null, [
            new text_input_element("item", "Label"),
        ]),
        ],
        ["select", (new select_element("my_select", "Label", [
            new option("Option 1", "opt1", true),
            new option("Option 2", "opt2"),
        ], true, true))->disable_if(new in("input1", ["valid", "also valid"])),
        ],
        ["static_text", new static_text_element("my_text", "Label", "Lorem ipsum dolor sit amet.")],
        ["input", new text_input_element("my_field", "Label", true, "default", "placeholder")],
        ["textarea", new text_area_element("my_field", "Label", true, "default", "placeholder")],
    ];
}
