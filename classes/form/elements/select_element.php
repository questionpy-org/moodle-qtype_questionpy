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
use qtype_questionpy\array_converter\array_converter;
use qtype_questionpy\array_converter\converter_config;
use qtype_questionpy\form\context\render_context;
use qtype_questionpy\form\form_conditions;
use qtype_questionpy\form\form_help;

defined('MOODLE_INTERNAL') || die;

/**
 * Select element, either a dropdown or a multi-select.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class select_element extends form_element {
    /** @var string */
    public string $name;
    /** @var string */
    public string $label;
    /** @var option[] */
    public array $options;
    /** @var bool */
    public bool $multiple = false;
    /** @var bool */
    public bool $required = false;

    use form_conditions, form_help;

    /**
     * Initializes the element.
     *
     * @param string $name
     * @param string $label
     * @param option[] $options
     * @param bool $multiple
     * @param bool $required
     */
    public function __construct(string $name, string $label, array $options, bool $multiple = false,
                                bool   $required = false) {
        $this->name = $name;
        $this->label = $label;
        $this->options = $options;
        $this->multiple = $multiple;
        $this->required = $required;
    }

    /**
     * Render this item to the given context.
     *
     * @param render_context $context target context
     */
    public function render_to(render_context $context): void {
        $selected = [];
        $optionsassociative = [];
        foreach ($this->options as $option) {
            $optionsassociative[$option->value] = $context->contextualize($option->label);
            if ($option->selected) {
                $selected[] = $option->value;
            }
        }

        // phpcs:disable moodle.Commenting.InlineComment.DocBlock
        /** @var $element HTML_QuickForm_select */
        $element = $context->add_element(
            "select", $this->name,
            $context->contextualize($this->label), $optionsassociative);

        $element->setMultiple($this->multiple);
        if ($selected) {
            $context->set_default($this->name, $this->multiple ? $selected : $selected[0]);
        }

        if ($this->required) {
            $context->add_rule($this->name, null, "required");
        }

        $this->render_conditions($context, $this->name);
        $this->render_help($element);
    }
}

array_converter::configure(select_element::class, function (converter_config $config) {
    $config->array_elements("options", option::class);
});
