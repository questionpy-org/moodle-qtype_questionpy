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

use coding_exception;
use qtype_questionpy\array_converter\array_converter;
use qtype_questionpy\array_converter\converter_config;
use qtype_questionpy\form\render_context;
use qtype_questionpy\form\root_render_context;

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
    /** @var string */
    public string $name;
    /** @var int number of elements to show initially */
    public int $initialelements;
    /** @var int number of elements to add with each click of the button */
    public int $increment;
    /** @var string|null label for the button which adds additional blanks, null to use default */
    public ?string $buttonlabel;

    /** @var form_element[] */
    public array $elements;

    /**
     * Initializes the element.
     *
     * @param string $name
     * @param int $initialelements     number of elements to show initially
     * @param int $increment           number of elements to add with each click of the button
     * @param string|null $buttonlabel label for the button which adds additional blanks, null to use default
     * @param form_element[] $elements
     */
    public function __construct(string $name, int $initialelements, int $increment, ?string $buttonlabel,
                                array  $elements) {
        $this->name = $name;
        $this->initialelements = $initialelements;
        $this->increment = $increment;
        $this->buttonlabel = $buttonlabel;
        $this->elements = $elements;
    }

    /**
     * Render this item to the given context.
     *
     * @param render_context $context target context
     * @throws coding_exception
     * @package qtype_questionpy
     */
    public function render_to(render_context $context): void {
        /*
         * Moodle implements this in moodleform::repeat_elements(), but that method is inconsistent in how it names
         * elements, so we implement it ourselves.
         * TODO: When editing a question, we need to get the current number of repeats from the form data.
         */
        $repetitionname = $context->mangle_name($this->name);

        $repeatsname = "qpy_repeats_" . $this->name;
        $addmorename = "qpy_repeat_add_more_" . $this->name;

        $repeats = $context->mform->optional_param($repeatsname, $this->initialelements, PARAM_INT);
        $addmore = $context->mform->optional_param($addmorename, "", PARAM_TEXT);

        if ($addmore) {
            $repeats += $this->increment;
        }

        $context->mform->addElement("hidden", $repeatsname, $repeats);
        $context->mform->setType($repeatsname, PARAM_INT);
        // Prevent repeats from being overridden with the submitted value.
        $context->mform->setConstant($repeatsname, $repeats);

        for ($i = 0; $i < $repeats; $i++) {
            $prefix = $repetitionname . "[$i]";
            $innercontext = new root_render_context(
                $context->moodleform, $context->mform,
                $prefix, $context->nextuniqueint
            );

            foreach ($this->elements as $element) {
                $element->render_to($innercontext);
            }

            $context->nextuniqueint = $innercontext->nextuniqueint;
        }

        $buttonlabel = $this->buttonlabel ?: get_string('addfields', 'form', $this->increment);
        $context->mform->addElement("submit", $addmorename, $buttonlabel);
        $context->mform->registerNoSubmitButton($addmorename);
    }
}

array_converter::configure(repetition_element::class, function (converter_config $config) {
    $config
        ->rename("initialelements", "initial_elements")
        ->rename("buttonlabel", "button_label")
        ->array_elements("elements", form_element::class);
});
