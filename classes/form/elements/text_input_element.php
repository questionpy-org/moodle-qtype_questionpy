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

class text_input_element extends form_element {
    public string $name;
    public string $label;
    public bool $required = false;
    public ?string $default = null;
    public ?string $placeholder = null;

    public function __construct(
        string  $name,
        string  $label,
        bool    $required = false,
        ?string $default = null,
        ?string $placeholder = null
    ) {
        $this->name = $name;
        $this->label = $label;
        $this->required = $required;
        $this->default = $default;
        $this->placeholder = $placeholder;
    }

    public static function from_array(array $array): self {
        return new self(
            $array["name"],
            $array["label"],
            $array["required"] ?? false,
            $array["default"] ?? null,
            $array["placeholder"] ?? null,
        );
    }

    protected static function kind(): string {
        return "text_input";
    }

    public function render_to(render_context $context): void {
        $attributes = [];
        if ($this->required) {
            $attributes["required"] = "required";
        }
        if ($this->default) {
            $attributes["value"] = $this->default;
        }
        if ($this->placeholder) {
            $attributes["placeholder"] = $this->placeholder;
        }

        $context->add_element("text", $this->name, $this->label, $attributes);
        $context->set_type($this->name, PARAM_TEXT);
    }
}
