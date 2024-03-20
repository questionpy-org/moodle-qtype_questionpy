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
use qtype_questionpy\form\context\render_context;
use qtype_questionpy\form\context\repetition_render_context;

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
    /** @var int number of repetitions to show initially */
    public int $initialrepetitions;
    /** @var int minimum number of repetitions, which cannot be removed */
    public int $minimumrepetitions = 1;
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
     * @param int $initialrepetitions  number of repetitions to show initially
     * @param int $increment           number of repetitions to add with each click of the button
     * @param string|null $buttonlabel label for the button which adds additional blanks, null to use default
     * @param form_element[] $elements
     */
    public function __construct(string $name, int $initialrepetitions, int $increment, ?string $buttonlabel,
                                array  $elements) {
        $this->name = $name;
        $this->initialrepetitions = $initialrepetitions;
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
         * elements, so we implement our own.
         */
        $mangledname = str_replace(["[", "]"], "_", $context->mangle_name($this->name));
        $internalname = "qpy_repetition[$mangledname]";
        $repeatsname = "{$internalname}[repeats]";
        $addmorename = "{$internalname}[add_more]";
        $removenameprefix = "{$internalname}[remove]";
        $removednameprefix = "{$internalname}[removed]";

        $repeats = $context->moodleform->optional_param(
            $repeatsname,
            isset($context->data[$this->name])
                ? count($context->data[$this->name])
                : max($this->initialrepetitions, $this->minimumrepetitions),
            PARAM_INT
        );

        $addmore = $context->moodleform->optional_param($addmorename, "", PARAM_TEXT);
        if ($addmore) {
            $repeats += $this->increment;
        }

        $context->mform->addElement("hidden", $repeatsname, $repeats);
        $context->mform->setType($repeatsname, PARAM_INT);
        // Prevent repeats from being overridden with the submitted value.
        $context->mform->setConstant($repeatsname, $repeats);

        $removed = [];
        for ($i = 0; $i < $repeats; $i++) {
            /* When a repetition is removed ($removename button clicked), we create a hidden element named $removedname,
               in order to "remember" this while also preserving the indices of the following repetitions.
               The split is necessary because the button must be no-submit, while the hidden element must not prevent
               submission. */
            $context->mform->registerNoSubmitButton("{$removenameprefix}[$i]");
            $isremoved = $context->moodleform->optional_param("{$removenameprefix}[$i]", false, PARAM_RAW)
                || $context->moodleform->optional_param("{$removednameprefix}[$i]", false, PARAM_RAW);
            if ($isremoved !== false) {
                $removed[] = $i;
                $context->mform->addElement("hidden", "{$removednameprefix}[$i]", "removed");
                $context->mform->setType("{$removednameprefix}[$i]", PARAM_RAW);
            }
        }

        $allowremoval = $repeats - count($removed) > $this->minimumrepetitions;

        $removestring = get_string("remove");
        global $OUTPUT;
        $removeicon = $OUTPUT->pix_icon('i/delete', $removestring, 'core');

        // User-facing repetition number. Starts at 1 and doesn't count removed reps.
        $humanrepno = 0;
        for ($i = 0; $i < $repeats; $i++) {
            if (in_array($i, $removed)) {
                continue;
            }
            $humanrepno++;

            $context->mform->addElement("html", '<div class="qpy-repetition"><div class="qpy-repetition-content">');

            $innercontext = new repetition_render_context($context, $this->name, $i, $humanrepno);
            foreach ($this->elements as $element) {
                $element->render_to($innercontext);
            }

            $context->mform->addElement("html", '</div><div class="qpy-repetition-controls">');
            if ($allowremoval) {
                $context->mform->addElement("html", "<button name='{$removenameprefix}[$i]' type='submit'
                class='btn btn-secondary qpy-repetition-remove' value='remove'>$removeicon $removestring</button>");
            }
            $context->mform->addElement("html", '</div></div>');
        }

        $buttonlabel = $this->buttonlabel ?: get_string('addfields', 'form', $this->increment);
        $context->mform->addElement("submit", $addmorename, $buttonlabel, [], false);
        $context->mform->registerNoSubmitButton($addmorename);
    }
}

array_converter::configure(repetition_element::class, function (converter_config $config) {
    $config
        ->rename("initialrepetitions", "initial_repetitions")
        ->rename("minimumrepetitions", "minimum_repetitions")
        ->rename("buttonlabel", "button_label")
        ->array_elements("elements", form_element::class);
});
