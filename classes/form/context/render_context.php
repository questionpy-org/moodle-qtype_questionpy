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

namespace qtype_questionpy\form\context;

use moodleform;
use MoodleQuickForm;
use qtype_questionpy\utils;

/**
 * Abstracts away the differences in rendering elements in a group and outside of a group.
 *
 * In a group, the element is created, and added as part of the group element. Outside of a group, the element is added
 * directly. This class abstracts away the differences so that {@see qpy_renderable::render_to} implementations needn't be
 * aware of where they are being rendered. It does this while still allowing for checkbox controllers, which use an
 * entirely different method.
 *
 * Render contexts form a hierarchy with a single {@see root_render_context} at the top and 0 or more
 * {@see section_render_context}s and {@see array_render_context}s below it.
 *
 * @see        mform_render_context
 * @see        array_render_context
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class render_context {
    /** @var moodleform target {@see moodleform} instance, such as {@see \qtype_questionpy_edit_form} */
    public moodleform $moodleform;
    /**
     * @var MoodleQuickForm target {@see MoodleQuickForm} instance, as passed to
     *                      {@see \question_edit_form::definition_inner}
     */
    public MoodleQuickForm $mform;

    /** @var string prefix for rendered element names */
    public string $prefix;

    /** @var array the current form data */
    public array $data;

    /**
     * Initializes a new render context.
     *
     * @param moodleform $moodleform  target {@see moodleform} instance, such as {@see \qtype_questionpy_edit_form}
     * @param MoodleQuickForm $mform  target {@see MoodleQuickForm} instance, as passed to
     *                                {@see \question_edit_form::definition_inner}
     * @param string $prefix          prefix for the names of elements in this context
     * @param array $data             the current form data (as of last save)
     */
    public function __construct(moodleform $moodleform, MoodleQuickForm $mform, string $prefix, array $data) {
        $this->moodleform = $moodleform;
        $this->mform = $mform;
        $this->prefix = $prefix;
        $this->data = $data;
    }

    /**
     * Create, add and return an element.
     *
     * @param string $type   the type name of the element, as per the Moodle docs.
     * @param string $name   the name of the generated form element.
     * @param mixed ...$args remaining arguments specific to the element type.
     * @return object the created element. Really an instance of {@see \HTML_QuickForm_element}, but the return type of
     *                       {@see MoodleQuickForm::addElement} is also an object.
     * @see MoodleQuickForm::addElement
     */
    abstract public function add_element(string $type, string $name, ...$args): object;

    /**
     * Sets the type of an element which has been (or will be) added independently.
     *
     * @param string $name the name of the target element.
     * @param string $type one of the {@see PARAM_INT}, {@see PARAM_TEXT}, etc. constants.
     * @see MoodleQuickForm::setType
     */
    abstract public function set_type(string $name, string $type): void;

    /**
     * Sets the default of an element which has been (or will be) added independently.
     *
     * @param string $name   the name of the target element.
     * @param mixed $default default value for the element.
     * @see MoodleQuickForm::setDefault
     */
    abstract public function set_default(string $name, $default): void;

    /**
     * Adds a validation rule an element which has been added independently.
     *
     * Must be called *after* the element was added using {@see add_element}.
     *
     * @param string $name            the name of the target element.
     * @param string|null $message    message to display for invalid data.
     * @param string $type            rule type, use getRegisteredRules() to get types.
     * @param string|null $format     required for extra rule data.
     * @param string|null $validation where to perform validation: "server", "client".
     * @param bool $reset             client-side validation: reset the form element to its original value if there is
     *                                an error?
     * @param bool $force             force the rule to be applied, even if the target form element does not exist.
     * @see MoodleQuickForm::addRule
     */
    abstract public function add_rule(string  $name, ?string $message, string $type, ?string $format = null,
                                      ?string $validation = "server", bool $reset = false, bool $force = false): void;

    /**
     * Adds a condition which will disable the named element if met.
     *
     * @param string $dependant name of the element which has the dependency on another element
     * @param string $dependency  absolute name of the element which is depended on
     * @param string $operator  one of a fixed set of conditions, as in {@see MoodleQuickForm::disabledIf}
     * @param mixed $value      for conditions requiring it, the value to compare with. Ignored otherwise.
     * @see MoodleQuickForm::disabledIf
     */
    abstract public function disable_if(string $dependant, string $dependency, string $operator, $value = null): void;

    /**
     * Adds a condition which will hide the named element if met.
     *
     * @param string $dependant name of the element which has the dependency on another element
     * @param string $dependency  absolute name of the element which is depended on
     * @param string $operator  one of a fixed set of conditions, as in {@see MoodleQuickForm::hideIf}
     * @param mixed $value      for conditions requiring it, the value to compare with. Ignored otherwise.
     * @see MoodleQuickForm::hideIf
     */
    abstract public function hide_if(string $dependant, string $dependency, string $operator, $value = null): void;

    /**
     * Append the given local name to the prefix of this context.
     *
     * @param string $name local / unqualified name of the element
     * @return string name of the element qualified by this context's prefix
     */
    public function mangle_name(string $name): string {
        if (utils::str_starts_with($name, $this->prefix)) {
            // Already mangled, perhaps by an array_render_context.
            return $name;
        }

        $firstbrace = strpos($name, "[");
        if ($firstbrace) {
            // We want to turn abc[def] into prefix[abc][def], not prefix[abc[def]].
            $beforebrace = substr($name, 0, $firstbrace);
            $afterbrace = substr($name, $firstbrace);
            return $this->prefix . "[$beforebrace]" . $afterbrace;
        }

        return $this->prefix . "[$name]";
    }

    /**
     * Get a unique and deterministic integer for use in generated element names and IDs.
     *
     * @return int a unique and deterministic integer for use in generated element names and IDs.
     */
    public abstract function next_unique_int(): int;

    /**
     * Turns a reference relative to this context's prefix into an absolute reference.
     *
     * The validity of the reference (i.e. whether it points to anything) is not checked.
     *
     * @param string $reference relative reference, which may contain `..` parts to refer to the parent
     * @return string absolute reference
     */
    public function reference_to_absolute(string $reference): string {
        $referee = $this->prefix;
        // Explode a $reference like qpy_form[abc][def] into an array ["qpy_form", "abc", "def"].
        $referenceparts = explode("[", str_replace("]", "", $reference));
        $refereeparts = explode("[", str_replace("]", "", $referee));

        foreach ($referenceparts as $referencepart) {
            if ($referencepart === "..") {
                $removed = array_pop($refereeparts);
                if (is_numeric($removed)) {
                    // The reference probably points from a repetition outward.
                    // We removed the index ([0]), but we also want to remove the repetition name.
                    array_pop($refereeparts);
                }
            } else {
                $refereeparts[] = $referencepart;
            }
        }

        // Stitch $refereeparts back together.
        return $refereeparts[0] . "[" . implode("][", array_slice($refereeparts, 1)) . "]";
    }

    /**
     * Replaces occurrences of `{ qpy:... }` with the appropriate contextual variable, if any.
     *
     * Unrecognized variables aren't replaced at all.
     *
     * This is used by {@see repetition_render_context} to allow repeated elements to show the repetition number without
     * the elements needing to be aware of whether they are in a repetition.
     *
     * @param string|null $text string possibly containing `{ qpy:... }` format specifiers
     * @return string|null input string with format specifiers replaced
     */
    public abstract function contextualize(?string $text): ?string;
}
