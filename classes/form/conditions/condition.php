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

namespace qtype_questionpy\form\conditions;

use qtype_questionpy\deserializable;
use qtype_questionpy\kind_deserialize;

/**
 * Base class for QuestionPy form element conditions.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class condition implements deserializable, \JsonSerializable {

    /** @var string $name name of the target element */
    public string $name;

    /**
     * Initializes the condition.
     *
     * @param string $name name of the target element
     */
    public function __construct(string $name) {
        $this->name = $name;
    }

    /**
     * Return the `[$condition]` or `[$condition, $value]` tuple  to pass to {@see \MoodleQuickForm::disabledIf()} or
     * {@see \MoodleQuickForm::hideIf()} after the depended on element's name.
     *
     * @return array
     */
    abstract public function to_mform_args(): array;

    use kind_deserialize;

    /**
     * Returns an array mapping each possible kind value to the associated concrete class name.
     *
     * The `kind` field of an element's JSON representation serves as a descriptor field. {@see from_array_any()} uses
     * it to determine the concrete class to use for deserialization. This method should be implemented by the base
     * class of the hierarchy.
     */
    protected static function kinds(): array {
        return [
            "is_checked" => is_checked::class,
            "is_not_checked" => is_not_checked::class,
            "equals" => equals::class,
            "does_not_equal" => does_not_equal::class,
            "in" => in::class,
        ];
    }
}
