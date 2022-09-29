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

/**
 * Deserializes a hierarchy of classes using a discriminator field `kind`.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait kind_deserialize {
    /**
     * Returns an array mapping each possible kind value to the associated concrete class name.
     *
     * The `kind` field of an element's JSON representation serves as a descriptor field. {@see from_array_any()} uses
     * it to determine the concrete class to use for deserialization. This method should be implemented by the base
     * class of the hierarchy.
     */
    abstract protected static function kinds(): array;

    /**
     * Convert the given array to the concrete instance without checking the `kind` descriptor.
     * (Which is done by {@see from_array_any}.)
     *
     * This method should be implemented by the concrete implementations.
     *
     * @param array $array source array, probably parsed from JSON
     */
    abstract public static function from_array(array $array): self;

    /**
     * Convert this element except for the `kind` descriptor to an array suitable for json encoding.
     *
     * The default implementation just casts to an array, which is suitable only if the json field names match the
     * class property names.
     */
    public function to_array(): array {
        return (array)$this;
    }

    /**
     * Use the value of the `kind` descriptor to convert the given array to the correct concrete element,
     * delegating to the appropriate {@see from_array} implementation.
     *
     * @param array $array source array, probably parsed from JSON
     */
    final public static function from_array_any(array $array): self {
        $kind = $array["kind"];
        unset($array["kind"]);

        $class = self::kinds()[$kind] ?? null;
        if ($class === null) {
            throw new \RuntimeException("Unknown kind: " . $kind);
        }

        return $class::from_array($array);
    }

    /**
     * Serializes this element by calling {@see to_array} and adding its {@see kinds `kind`} to the result.
     */
    final public function jsonSerialize(): array {
        $kind = array_flip(self::kinds())[get_class($this)];
        return array_merge(
            ["kind" => $kind],
            $this->to_array()
        );
    }
}
