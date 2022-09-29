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

use qtype_questionpy\deserializable;
use qtype_questionpy\form\renderable;
use qtype_questionpy\kind_deserialize;

/**
 * Base class for QuestionPy form elements.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class form_element implements renderable, \JsonSerializable, deserializable {
    use kind_deserialize;

    /**
     * Returns an array mapping each possible kind value to the associated concrete class name.
     *
     * The `kind` field of an element's JSON representation serves as a descriptor field. {@see from_array_any()} uses
     * it to determine the concrete class to use for deserialization. This method should be implemented by the base
     * class of the hierarchy.
     */
    final protected static function kinds(): array {
        return [
            "checkbox" => checkbox_element::class,
            "checkbox_group" => checkbox_group_element::class,
            "group" => group_element::class,
            "hidden" => hidden_element::class,
            "radio_group" => radio_group_element::class,
            "select" => select_element::class,
            "static_text" => static_text_element::class,
            "text_input" => text_input_element::class,
        ];
    }
}
