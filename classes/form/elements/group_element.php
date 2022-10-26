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

use qtype_questionpy\array_converter\array_converter;
use qtype_questionpy\array_converter\converter_config;
use qtype_questionpy\form\form_conditions;
use qtype_questionpy\form\group_render_context;
use qtype_questionpy\form\render_context;

defined('MOODLE_INTERNAL') || die;

/**
 * Element grouping multiple elements and displaying them horizontally next to each other.
 *
 * @see        \MoodleQuickForm::addGroup
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group_element extends form_element {
    /** @var string */
    public string $name;
    /** @var string */
    public string $label;
    /** @var form_element[] */
    public array $elements;

    use form_conditions;

    /**
     * Initializes the element.
     *
     * @param string $name
     * @param string $label
     * @param form_element[] $elements
     */
    public function __construct(string $name, string $label, array $elements) {
        $this->name = $name;
        $this->label = $label;
        $this->elements = $elements;
    }

    /**
     * Render this item to the given context.
     *
     * @param render_context $context target context
     * @package qtype_questionpy
     */
    public function render_to(render_context $context): void {
        $groupcontext = new group_render_context($context);

        foreach ($this->elements as $element) {
            $element->render_to($groupcontext);
        }

        $context->add_element("group", $this->name, $this->label, $groupcontext->elements, null, false);

        foreach ($groupcontext->types as $name => $type) {
            $context->set_type($name, $type);
        }
        foreach ($groupcontext->defaults as $name => $default) {
            $context->set_default($name, $default);
        }
        foreach ($groupcontext->disableifs as $name => $condition) {
            $context->disable_if($name, $condition);
        }
        foreach ($groupcontext->hideifs as $name => $condition) {
            $context->hide_if($name, $condition);
        }

        $context->mform->addGroupRule($this->name, $groupcontext->rules);

        $this->render_conditions($context, $this->name);
    }
}

array_converter::configure(group_element::class, function (converter_config $config) {
    $config->array_elements("elements", form_element::class);
});
