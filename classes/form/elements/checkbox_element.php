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

use qtype_questionpy\form\render_context;

/**
 * Element displaying a labelled checkbox.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class checkbox_element extends form_element {
    /** @var string */
    public string $name;
    /** @var string|null */
    public ?string $leftlabel = null;
    /** @var string|null */
    public ?string $rightlabel = null;
    /** @var bool */
    public bool $required = false;
    /** @var bool */
    public bool $selected = false;

    /**
     * Initializes the element.
     *
     * @param string $name
     * @param string|null $leftlabel
     * @param string|null $rightlabel
     * @param bool $required
     * @param bool $selected
     */
    public function __construct(string $name, ?string $leftlabel = null, ?string $rightlabel = null,
                                bool   $required = false, bool $selected = false) {
        $this->name = $name;
        $this->leftlabel = $leftlabel;
        $this->rightlabel = $rightlabel;
        $this->required = $required;
        $this->selected = $selected;
    }

    /**
     * Convert the given array to the concrete element without checking the `kind` descriptor.
     * (Which is done by {@see from_array_any}.)
     *
     * @param array $array source array, probably parsed from JSON
     */
    public static function from_array(array $array): self {
        return new self(
            $array["name"],
            $array["left_label"] ?? null,
            $array["right_label"] ?? null,
            $array["required"] ?? false,
            $array["selected"] ?? false,
        );
    }

    /**
     * Convert this element except for the `kind` descriptor to an array suitable for json encoding.
     * The default implementation just casts to an array, which is suitable only if the json field names match the
     * class property names.
     */
    public function to_array(): array {
        return [
            "name" => $this->name,
            "left_label" => $this->leftlabel,
            "right_label" => $this->rightlabel,
            "required" => $this->required,
            "selected" => $this->selected,
        ];
    }

    /**
     * The `kind` field of an element's JSON representation serves as a descriptor field. {@see from_array_any()} uses
     * it to determine the concrete class to use for deserialization.
     *
     * @return string the value of this element's `kind` field.
     */
    protected static function kind(): string {
        return "checkbox";
    }

    /**
     * Render this item to the given context.
     *
     * @param render_context $context target context
     * @param int|null $group         passed by {@see checkbox_group_element::render_to} to the checkboxes belonging to
     *                                it
     */
    public function render_to(render_context $context, ?int $group = null): void {
        $context->add_element(
            "advcheckbox", $this->name, $this->leftlabel, $this->rightlabel,
            $group ? ["group" => $group] : null
        );

        if ($this->selected) {
            $context->set_default($this->name, "1");
        }
        if ($this->required) {
            $context->add_rule($this->name, get_string("required"), "required");
        }
    }
}
