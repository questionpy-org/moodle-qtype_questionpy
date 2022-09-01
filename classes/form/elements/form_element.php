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

use qtype_questionpy\form\renderable;

/**
 * Base class for QuestionPy form elements.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class form_element implements renderable, \JsonSerializable {
    private static array $elementclasses = [
        checkbox_element::class,
        checkbox_group_element::class,
        group_element::class,
        hidden_element::class,
        radio_group_element::class,
        select_element::class,
        static_text_element::class,
        text_input_element::class,
    ];

    /**
     * The `kind` field of an element's JSON representation serves as a descriptor field. {@see from_array_any()} uses
     * it to determine the concrete class to use for deserialization.
     *
     * @return string the value of this element's `kind` field.
     */
    abstract protected static function kind(): string;

    /**
     * Convert the given array to the concrete element without checking the `kind` descriptor.
     * (Which is done by {@see from_array_any}.)
     */
    abstract public static function from_array(array $array): self;

    /**
     * Convert this element except for the `kind` descriptor to an array suitable for json encoding.
     * The default implementation just casts to an array, which is suitable only if the json field names match the
     * class property names.
     */
    public function to_array(): array {
        return (array)$this;
    }

    /**
     * Use the value of the `kind` descriptor to convert the given array to the correct concrete element,
     * delegating to the appropriate {@see from_array} implementation.
     */
    final public static function from_array_any(array $array): self {
        $kind = $array["kind"];
        foreach (self::$elementclasses as $elementclass) {
            if ($elementclass::kind() == $kind) {
                return $elementclass::from_array($array);
            }
        }
        throw new \RuntimeException("Unknown form element kind: " . $kind);
    }

    public function jsonSerialize(): array {
        return array_merge(
            ["kind" => $this->kind()],
            $this->to_array()
        );
    }
}
