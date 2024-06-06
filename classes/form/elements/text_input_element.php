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

use qtype_questionpy\form\context\render_context;
use qtype_questionpy\form\form_conditions;
use qtype_questionpy\form\form_help;

/**
 * Simple text input element.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2024 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class text_input_element extends form_element {

    /** @var string moodle form element name, overridden by {@see text_area_element} */
    protected const MFORM_ELEMENT = "text";

    /** @var string */
    public string $name;
    /** @var string */
    public string $label;
    /** @var bool */
    public bool $required = false;
    /** @var string|null */
    public ?string $default = null;
    /** @var string|null */
    public ?string $placeholder = null;

    use form_conditions, form_help;

    /**
     * Initializes the element.
     *
     * @param string $name
     * @param string $label
     * @param bool $required
     * @param string|null $default
     * @param string|null $placeholder
     */
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

    /**
     * Render this item to the given context.
     *
     * @param render_context $context target context
     */
    public function render_to(render_context $context): void {
        $attributes = $this->placeholder ? ["placeholder" => $context->contextualize($this->placeholder)] : [];

        $element = $context->add_element(
            get_class($this)::MFORM_ELEMENT, $this->name,
            $context->contextualize($this->label), $attributes
        );
        $context->set_type($this->name, PARAM_TEXT);

        if ($this->default) {
            $context->set_default($this->name, $context->contextualize($this->default));
        }
        if ($this->required) {
            $context->add_rule($this->name, null, "required");
        }

        $this->render_conditions($context, $this->name);
        $this->render_help($element);
    }
}
