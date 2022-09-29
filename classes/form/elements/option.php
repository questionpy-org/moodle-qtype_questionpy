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

/**
 * One option in a {@see radio_group_element} or a {@see select_element}.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class option implements deserializable {
    /** @var string */
    public string $label;
    /** @var string */
    public string $value;
    /** @var bool whether this option is selected by default */
    public bool $selected = false;

    /**
     * Initialize a new option.
     *
     * @param string $label
     * @param string $value
     * @param bool $selected whether this option is selected by default
     */
    public function __construct(
        string $label,
        string $value,
        bool   $selected = false
    ) {
        $this->label = $label;
        $this->value = $value;
        $this->selected = $selected;
    }

    /**
     * Convert the given array to an option.
     *
     * @param array $array source array, probably parsed from JSON
     */
    public static function from_array(array $array): self {
        return new self(
            $array["label"],
            $array["value"],
            $array["selected"] ?? false
        );
    }
}
