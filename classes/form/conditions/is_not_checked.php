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

/**
 * Condition checking whether the target form element value is an unchecked checkbox.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class is_not_checked extends condition {

    /**
     * Return the `[$condition]` or `[$condition, $value]` tuple  to pass to {@see \MoodleQuickForm::disabledIf()} or
     * {@see \MoodleQuickForm::hideIf()} after the depended on element's name.
     *
     * @return array
     */
    public function to_mform_args(): array {
        return ["notchecked"];
    }

    /**
     * Convert the given array to the concrete instance without checking the `kind` descriptor.
     * (Which is done by {@see from_array_any}.)
     *
     * This method should be implemented by the concrete implementations.
     *
     * @param array $array source array, probably parsed from JSON
     */
    public static function from_array(array $array): self {
        return new self($array["name"]);
    }
}
