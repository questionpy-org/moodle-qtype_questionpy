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

use HTML_QuickForm_select;
use qtype_questionpy\form\render_context;

class select_element extends form_element {
    public string $name;
    public string $label;
    /** @var option[] */
    public array $options;
    public bool $multiple = false;
    public bool $required = false;

    /**
     * @param option[] $options
     */
    public function __construct(string $name, string $label, array $options, bool $multiple = false,
                                bool   $required = false) {
        $this->name = $name;
        $this->label = $label;
        $this->options = $options;
        $this->multiple = $multiple;
        $this->required = $required;
    }

    protected static function kind(): string {
        return "select";
    }

    public static function from_array(array $array): self {
        return new self(
            $array["name"],
            $array["label"],
            array_map([option::class, "from_array"], $array["options"]),
            $array["multiple"] ?? false,
            $array["required"] ?? false,
        );
    }

    public function render_to(render_context $context): void {
        $selected = [];
        $optionsassociative = [];
        foreach ($this->options as $option) {
            $optionsassociative[$option->value] = $option->label;
            if ($option->selected) {
                $selected[] = $option->value;
            }
        }

        $attributes = [];
        if ($this->required) {
            $attributes["required"] = "required";
        }

        // phpcs:disable moodle.Commenting.InlineComment.DocBlock
        /** @var $element HTML_QuickForm_select */
        $element = $context->add_element("select", $this->name, $this->label, $optionsassociative, $attributes);

        $element->setMultiple($this->multiple);
        $element->setSelected($selected);
    }
}
