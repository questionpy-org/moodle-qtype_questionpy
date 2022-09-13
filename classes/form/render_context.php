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

namespace qtype_questionpy\form;

/**
 * Abstracts away the differences in rendering elements in a group (where the element is created, and added as part of
 * the group element) and outside of a group (where the element is added directly) while still allowing for checkbox
 * controllers, which use an entirely different method.
 *
 * @see        root_render_context
 * @see        group_render_context
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class render_context {
    public \moodleform $moodleform;
    public \MoodleQuickForm $mform;

    public function __construct(\moodleform $moodleform, \MoodleQuickForm $mform) {
        $this->moodleform = $moodleform;
        $this->mform = $mform;
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
    abstract public function add_element(string $type, string $name, ...$args): object;

    /**
     * Sets the type of an element which has been (or will be) added independently.
     *
     * @param string $name the name of the target element.
     * @param string $type one of the {@see PARAM_INT}, {@see PARAM_TEXT}, etc. constants.
     * @see \MoodleQuickForm::setType()
     */
    abstract public function set_type(string $name, string $type): void;

    /**
     * Sets the default of an element which has been (or will be) added independently.
     *
     * @param string $name   the name of the target element.
     * @param mixed $default default value for the element.
     * @see \MoodleQuickForm::setDefault()
     */
    abstract public function set_default(string $name, $default): void;

    /**
     * Adds a validation rule an element which has been added independently.
     *
     * Must be called *after* the element was added using {@see add_element}.
     *
     * @param string $name        the name of the target element.
     * @param ?string $message    message to display for invalid data.
     * @param string $type        rule type, use getRegisteredRules() to get types.
     * @param ?string $format     required for extra rule data.
     * @param ?string $validation where to perform validation: "server", "client".
     * @param bool $reset         client-side validation: reset the form element to its original value if there is an
     *                            error?
     * @param bool $force         force the rule to be applied, even if the target form element does not exist.
     * @see \MoodleQuickForm::addRule()
     */
    abstract public function add_rule(string  $name, ?string $message, string $type, ?string $format = null,
                                      ?string $validation = "server", bool $reset = false, bool $force = false): void;

    /**
     * @return int a unique and deterministic integer for use in generated element names and IDs.
     */
    abstract public function next_unique_int(): int;

    /**
     * Adds a `Select all/none` checkbox controller controlling all `advcheckboxes` with the given group id.
     *
     * @param int $groupid the group id matching the `group` attribute of the `advcheckboxes` which should be toggled
     *                     by this controller.
     * @see \moodleform::add_checkbox_controller()
     */
    abstract public function add_checkbox_controller(int $groupid): void;
}
