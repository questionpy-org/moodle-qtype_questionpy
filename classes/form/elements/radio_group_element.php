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
 * Group of radio buttons, only at most one of which can  be selected at once.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class radio_group_element extends form_element {
    /** @var string */
    public string $name;
    /** @var string */
    public string $label;
    /** @var option[] */
    public array $options;
    /** @var bool */
    public bool $required = false;

    /**
     * Initializes the element.
     *
     * @param string $name
     * @param string $label
     * @param option[] $options
     * @param bool $required
     */
    public function __construct(string $name, string $label, array $options, bool $required = false) {
        $this->name = $name;
        $this->label = $label;
        $this->options = $options;
        $this->required = $required;
    }

    /**
     * The `kind` field of an element's JSON representation serves as a descriptor field. {@see from_array_any()} uses
     * it to determine the concrete class to use for deserialization.
     *
     * @return string the value of this element's `kind` field.
     */
    protected static function kind(): string {
        return "radio_group";
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
            $array["label"],
            array_map([option::class, "from_array"], $array["options"]),
            $array["required"] ?? false,
        );
    }

    /**
     * Render this item to the given context.
     *
     * @param render_context $context target context
     */
    public function render_to(render_context $context): void {
        $default = null;
        $radioarray = [];
        foreach ($this->options as $option) {
            if ($option->selected) {
                $default = $option->value;
            }

            $radioarray[] = $context->mform->createElement("radio", $this->name, null, $option->label, $option->value);
        }

        $groupname = "qpy_radio_group_" . $this->name;
        $context->add_element("group", $groupname, $this->label, $radioarray, null, false);

        if ($default) {
            $context->set_default($this->name, $default);
        }
        if ($this->required) {
            $context->add_rule($groupname, null, "required");
        }
    }
}
