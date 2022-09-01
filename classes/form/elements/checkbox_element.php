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
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class checkbox_element extends form_element {
    public string $name;
    public ?string $leftlabel = null;
    public ?string $rightlabel = null;
    public bool $required = false;
    public bool $selected = false;

    public function __construct(string $name, ?string $leftlabel = null, ?string $rightlabel = null,
                                bool   $required = false, bool $selected = false) {
        $this->name = $name;
        $this->leftlabel = $leftlabel;
        $this->rightlabel = $rightlabel;
        $this->required = $required;
        $this->selected = $selected;
    }

    public static function from_array(array $array): self {
        return new self(
            $array["name"],
            $array["left_label"] ?? null,
            $array["right_label"] ?? null,
            $array["required"] ?? false,
            $array["selected"] ?? false,
        );
    }

    public function to_array(): array {
        return [
            "name" => $this->name,
            "left_label" => $this->leftlabel,
            "right_label" => $this->rightlabel,
            "required" => $this->required,
            "selected" => $this->selected,
        ];
    }

    protected static function kind(): string {
        return "checkbox";
    }

    public function render_to(render_context $context, ?int $group = null): void {
        $attributes = [
            "value" => $this->selected,
        ];
        if ($this->selected) {
            // FIXME: this seems to be broken within moodle, as the checked attribute never makes it into the HTML.
            $attributes["checked"] = "checked";
        }
        if ($this->required) {
            $attributes["required"] = "required";
        }

        if ($group) {
            $attributes["group"] = $group;
        }

        $context->add_element(
            "advcheckbox", $this->name, $this->leftlabel, $this->rightlabel, $attributes
        );
    }
}
