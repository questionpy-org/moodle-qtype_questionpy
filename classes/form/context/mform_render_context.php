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

use qtype_questionpy\utils;

/**
 * Regular {@see render_context} which delegates to {@see \moodleform} and {@see \MoodleQuickForm}.
 *
 * @see        array_render_context
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2023 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class mform_render_context extends render_context {
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
        return $this->mform->addElement($type, $this->mangle_name($name), ...$args);
    }

    /**
     * Sets the type of an element which has been (or will be) added independently.
     *
     * @param string $name the name of the target element.
     * @param string $type one of the {@see PARAM_INT}, {@see PARAM_TEXT}, etc. constants.
     * @see \MoodleQuickForm::setType()
     */
    public function set_type(string $name, string $type): void {
        $this->mform->setType($this->mangle_name($name), $type);
    }

    /**
     * Sets the default of an element which has been (or will be) added independently.
     *
     * @param string $name   the name of the target element.
     * @param mixed $default default value for the element.
     * @see \MoodleQuickForm::setDefault()
     */
    public function set_default(string $name, $default): void {
        /* We cannot use setDefault because that method uses $name as a top-level key instead of using nested arrays,
           which causes elements to prefer defaults over saved question data. (#44) */
        $this->mform->setDefaults(utils::array_create_nested($this->mangle_name($name), $default));
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
    public function add_rule(string $name, ?string $message, string $type, ?string $format = null,
                             ?string $validation = "server", bool $reset = false, bool $force = false): void {
        $this->mform->addRule($this->mangle_name($name), $message, $type, $format, $validation, $reset, $force);
    }

    /**
     * Adds a condition which will disable the named element if met.
     *
     * @param string $dependant  name of the element which has the dependency on another element
     * @param string $dependency absolute name of the element which is depended on
     * @param string $operator   one of a fixed set of conditions, as in {@see MoodleQuickForm::disabledIf}
     * @param mixed $value       for conditions requiring it, the value to compare with. Ignored otherwise.
     * @see \MoodleQuickForm::disabledIf()
     */
    public function disable_if(string $dependant, string $dependency, string $operator, $value = null): void {
        $this->mform->disabledIf($this->mangle_name($dependant), $dependency, $operator, $value);
    }

    /**
     * Adds a condition which will hide the named element if met.
     *
     * @param string $dependant  name of the element which has the dependency on another element
     * @param string $dependency absolute name of the element which is depended on
     * @param string $operator   one of a fixed set of conditions, as in {@see MoodleQuickForm::hideIf}
     * @param mixed $value       for conditions requiring it, the value to compare with. Ignored otherwise.
     * @see \MoodleQuickForm::hideIf()
     */
    public function hide_if(string $dependant, string $dependency, string $operator, $value = null): void {
        $this->mform->hideIf($this->mangle_name($dependant), $dependency, $operator, $value);
    }
}
