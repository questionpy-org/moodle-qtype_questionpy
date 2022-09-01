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

/**
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class radio_group_element extends form_element {
    public string $name;
    public string $label;
    /** @var option[] */
    public array $options;
    public bool $required = false;

    /**
     * @param option[] $options
     */
    public function __construct(string $name, string $label, array $options, bool $required = false) {
        $this->name = $name;
        $this->label = $label;
        $this->options = $options;
        $this->required = $required;
    }

    protected static function kind(): string {
        return "radio_group";
    }

    public static function from_array(array $array): form_element {
        return new self(
            $array["name"],
            $array["label"],
            array_map([option::class, "from_array"], $array["options"]),
            $array["required"] ?? false,
        );
    }

    public function render_to($context): void {
        $radioarray = [];
        foreach ($this->options as $option) {
            $attributes = [];
            if ($this->required) {
                $attributes["required"] = "required";
            }
            if ($option->selected) {
                // FIXME: this seems to be broken within moodle, as the checked attribute never makes it into the HTML.
                $attributes["checked"] = "checked";
            }

            $radioarray[] = $context->mform->createElement(
                "radio", $this->name, null, $option->label, $option->value, $attributes
            );
        }

        $context->add_element("group", "qpy_radio_group_" . $this->name, $this->label, $radioarray, null, false);
    }
}
