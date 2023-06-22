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

use HTML_QuickForm_element;
use qtype_questionpy\form\elements\group_element;
use qtype_questionpy\utils;

/**
 * A {@see render_context} for {@see group_element groups}.
 *
 * Instead of adding elements to the {@see \MoodleQuickForm}, they are added to an array which is later used to create
 * the group.
 *
 * @see        mform_render_context
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class array_render_context extends render_context {
    /** @var render_context context containing this group */
    private render_context $parent;
    /**
     * @var HTML_QuickForm_element[] elements so far added to this group
     */
    public array $elements = [];
    /**
     * @var array associative array of element names to types ({@see PARAM_INT}, etc.)
     */
    public array $types = [];
    /**
     * @var array associative array of element names to default values
     */
    public array $defaults = [];
    /**
     * @var array associative array of mangled element names to arrays of rules for that element, where each rule is
     *      the array of arguments passed to {@see \MoodleQuickForm::addRule()} not including the element name
     */
    public array $rules = [];
    /**
     * @var array associative array of dependant names to [dependency name, operator, value] arrays which should disable
     *      the named element
     */
    public array $disableifs = [];
    /**
     * @var array same as $disableifs, but for hiding elements
     */
    public array $hideifs = [];

    /**
     * Initializes a new array-based context.
     *
     * @param render_context $parent context containing this group
     * @param string $prefix         prefix for the names of elements in this context
     */
    public function __construct(render_context $parent, string $prefix) {
        $this->parent = $parent;
        parent::__construct(
            $parent->moodleform, $parent->mform, $prefix,
            utils::array_get_nested($parent->data, $prefix) ?? []
        );
    }

    /**
     * Create, add and return an element.
     *
     * @param string $type   the type name of the element, as per the Moodle docs.
     * @param string $name   the name of the generated form element.
     * @param mixed ...$args remaining arguments specific to the element type.
     * @return object the created element. Really an instance of {@see \HTML_QuickForm_element}, but the return type of
     *                       {@see \MoodleQuickForm::addElement()} is also an object.
     * @see \MoodleQuickForm::addElement()
     */
    public function add_element(string $type, string $name, ...$args): object {
        $element = $this->mform->createElement($type, $this->mangle_name($name), ...$args);
        $this->elements[] = $element;
        return $element;
    }

    /**
     * Sets the type of an element which has been (or will be) added independently.
     *
     * @param string $name the name of the target element.
     * @param string $type one of the {@see PARAM_INT}, {@see PARAM_TEXT}, etc. constants.
     * @see \MoodleQuickForm::setType()
     */
    public function set_type(string $name, string $type): void {
        $this->types[$this->mangle_name($name)] = $type;
    }

    /**
     * Sets the default of an element which has been (or will be) added independently.
     *
     * @param string $name   the name of the target element.
     * @param mixed $default default value for the element.
     * @see \MoodleQuickForm::setDefault()
     */
    public function set_default(string $name, $default): void {
        $this->defaults[$this->mangle_name($name)] = $default;
    }

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
     * @see \MoodleQuickForm::addRule()
     */
    public function add_rule(string  $name, ?string $message, string $type, ?string $format = null,
                             ?string $validation = "server", bool $reset = false, bool $force = false): void {
        utils::ensure_exists(
            $this->rules, $this->mangle_name($name)
        )[] = [$message, $type, $format, $validation, $reset, $force];
    }

    /**
     * Adds a condition which will disable the named element if met.
     *
     * @param string $dependant name of the element which has the dependency on another element
     * @param string $dependency  absolute name of the element which is depended on
     * @param string $operator  one of a fixed set of conditions, as in {@see MoodleQuickForm::disabledIf}
     * @param mixed $value      for conditions requiring it, the value to compare with. Ignored otherwise.
     * @see \MoodleQuickForm::disabledIf()
     */
    public function disable_if(string $dependant, string $dependency, string $operator, $value = null): void {
        utils::ensure_exists($this->disableifs, $this->mangle_name($dependant))[] = [$dependency, $operator, $value];
    }

    /**
     * Adds a condition which will hide the named element if met.
     *
     * @param string $dependant name of the element which has the dependency on another element
     * @param string $dependency  absolute name of the element which is depended on
     * @param string $operator  one of a fixed set of conditions, as in {@see MoodleQuickForm::hideIf}
     * @param mixed $value      for conditions requiring it, the value to compare with. Ignored otherwise.
     * @see \MoodleQuickForm::hideIf()
     */
    public function hide_if(string $dependant, string $dependency, string $operator, $value = null): void {
        utils::ensure_exists($this->hideifs, $this->mangle_name($dependant))[] = [$dependency, $operator, $value];
    }

    /**
     * Get a unique and deterministic integer for use in generated element names and IDs.
     *
     * @return int a unique and deterministic integer for use in generated element names and IDs.
     */
    public function next_unique_int(): int {
        return $this->parent->next_unique_int();
    }

    /**
     * Replaces occurrences of `{ qpy:... }` with the appropriate contextual variable, if any.
     *
     * This implementation just delegates to the parent context, so that repetition numbers are also replaced within
     * groups.
     *
     * @param string $text string possibly containing `{ qpy:... }` format specifiers
     * @return string input string with format specifiers replaced
     */
    public function contextualize(string $text): string {
        return $this->parent->contextualize($text);
    }
}
