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

use qtype_questionpy\form\form_conditions;
use qtype_questionpy\form\group_render_context;
use qtype_questionpy\form\render_context;

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
     * Convert the given array to the concrete element without checking the `kind` descriptor.
     * (Which is done by {@see from_array_any}.)
     *
     * @param array $array source array, probably parsed from JSON
     */
    public static function from_array(array $array): self {
        return (new self(
            $array["name"],
            $array["label"],
            array_map([form_element::class, "from_array_any"], $array["elements"])
        ))->deserialize_conditions($array);
    }

    /**
     * Convert this element except for the `kind` descriptor to an array suitable for json encoding.
     *
     * The default implementation just casts to an array, which is suitable only if the json field names match the
     * class property names.
     */
    public function to_array(): array {
        return $this->serialize_conditions(parent::to_array());
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
