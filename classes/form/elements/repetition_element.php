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
use qtype_questionpy\form\group_render_context;
use qtype_questionpy\form\render_context;
use qtype_questionpy\utils;

defined('MOODLE_INTERNAL') || die;

/**
 * Element repeating one or more nested elements a dynamic number of times.
 *
 * @see        \moodleform::repeat_elements()
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repetition_element extends form_element {
    /** @var int number of elements to show initially */
    public int $initialelements;
    /** @var int number of elements to add with each click of the button */
    public int $increment;
    /** @var string label for the button which adds additional blanks */
    public string $buttonlabel;

    /** @var form_element[] */
    public array $elements;

    /**
     * Initializes the element.
     *
     * @param int $initialelements number of elements to show initially
     * @param int $increment       number of elements to add with each click of the button
     * @param string $buttonlabel  label for the button which adds additional blanks
     * @param form_element[] $elements
     */
    public function __construct(int $initialelements, int $increment, string $buttonlabel, array $elements) {
        $this->initialelements = $initialelements;
        $this->increment = $increment;
        $this->buttonlabel = $buttonlabel;
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

        $options = [];

        foreach ($groupcontext->types as $name => $type) {
            utils::ensure_exists($options, $name)["type"] = $type;
        }
        foreach ($groupcontext->defaults as $name => $default) {
            utils::ensure_exists($options, $name)["default"] = $default;
        }
        foreach ($groupcontext->disableifs as $name => $condition) {
            utils::ensure_exists($options, $name)["disabledif"] = [
                $condition->name,
                ...$condition->to_mform_args()
            ];
        }
        foreach ($groupcontext->hideifs as $name => $condition) {
            utils::ensure_exists($options, $name)["hideif"] = [
                $condition->name,
                ...$condition->to_mform_args()
            ];
        }

        foreach ($groupcontext->rules as $name => $rules) {
            // There is only room for at most one rule in the options array, so for now we just ignore others.
            if ($rules) {
                utils::ensure_exists($options, $name)["rule"] = $rules[0];
            }
        }

        $context->moodleform->repeat_elements(
            $groupcontext->elements, $this->initialelements,
            $options, "repeats", "add_repeats",
            $this->increment, $this->buttonlabel, true
        );
    }
}

array_converter::configure(repetition_element::class, function (converter_config $config) {
    $config
        ->rename("initialelements", "initial_elements")
        ->rename("buttonlabel", "button_label")
        ->array_elements("elements", form_element::class);
});
