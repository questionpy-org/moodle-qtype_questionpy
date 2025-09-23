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

namespace qtype_questionpy\local\form\context;

use Closure;
use core\uuid;
use moodle_exception;
use moodleform;
use MoodleQuickForm;
use qtype_questionpy\local\form\qpy_renderable;
use qtype_questionpy\question_service;
use question_edit_form;

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
    /**
     * Initializes a new render context.
     *
     * @param question_edit_form $moodleform target {@see question_edit_form} instance, such as {@see \qtype_questionpy_edit_form}
     * @param MoodleQuickForm $mform target {@see MoodleQuickForm} instance, as passed to
     *                                {@see \question_edit_form::definition_inner}
     * @param string $prefix prefix for the names of elements in this context
     * @param array $data the current form data as of last save, in {@see self::register_rich_conversion() QPy server format}
     */
    public function __construct(
        /** @var moodleform target {@see moodleform} instance, such as {@see \qtype_questionpy_edit_form} */
        public question_edit_form $moodleform,
        /**
         * @var MoodleQuickForm target {@see MoodleQuickForm} instance, as passed to
         *                      {@see \question_edit_form::definition_inner}
         */
        public MoodleQuickForm $mform,
        /** @var object the current question being edited */
        public readonly object $question,
        /** @var string prefix for rendered element names */
        public string $prefix,
        /** @var array the current form data as of last save, in {@see self::register_rich_conversion() QPy server format} */
        public array $data
    ) {
    }

    /**
     * Create, add and return an element.
     *
     * @param string $type the type name of the element, as per the Moodle docs.
     * @param string $name the name of the generated form element.
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
     * @param string $name the name of the target element.
     * @param mixed $default default value for the element.
     * @see MoodleQuickForm::setDefault
     */
    abstract public function set_default(string $name, $default): void;

    /**
     * Adds a validation rule an element which has been added independently.
     *
     * Must be called *after* the element was added using {@see add_element}.
     *
     * @param string $name the name of the target element.
     * @param string|null $message message to display for invalid data.
     * @param string $type rule type, use getRegisteredRules() to get types.
     * @param string|null $format required for extra rule data.
     * @param string|null $validation where to perform validation: "server", "client".
     * @param bool $reset client-side validation: reset the form element to its original value if there is
     *                                an error?
     * @param bool $force force the rule to be applied, even if the target form element does not exist.
     * @see MoodleQuickForm::addRule
     */
    abstract public function add_rule(string $name, ?string $message, string $type, ?string $format = null,
                                      ?string $validation = 'server', bool $reset = false, bool $force = false): void;

    /**
     * Adds a condition which will disable the named element if met.
     *
     * @param string $dependant name of the element which has the dependency on another element
     * @param string $dependency absolute name of the element which is depended on
     * @param string $operator one of a fixed set of conditions, as in {@see MoodleQuickForm::disabledIf}
     * @param mixed $value for conditions requiring it, the value to compare with. Ignored otherwise.
     * @see MoodleQuickForm::disabledIf
     */
    abstract public function disable_if(string $dependant, string $dependency, string $operator, $value = null): void;

    /**
     * Adds a condition which will hide the named element if met.
     *
     * @param string $dependant name of the element which has the dependency on another element
     * @param string $dependency absolute name of the element which is depended on
     * @param string $operator one of a fixed set of conditions, as in {@see MoodleQuickForm::hideIf}
     * @param mixed $value for conditions requiring it, the value to compare with. Ignored otherwise.
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
        if (str_starts_with($name, $this->prefix)) {
            // Already mangled, perhaps by an array_render_context.
            return $name;
        }

        $firstbrace = strpos($name, '[');
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
    abstract public function next_unique_int(): int;

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
        $referenceparts = explode('[', str_replace(']', '', $reference));
        $refereeparts = explode('[', str_replace(']', '', $referee));

        foreach ($referenceparts as $referencepart) {
            if ($referencepart === '..') {
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
        return $refereeparts[0] . '[' . implode('][', array_slice($refereeparts, 1)) . ']';
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
    abstract public function contextualize(?string $text): ?string;

    /**
     * Generate a new UUID. Probably uses {@see uuid}, but may be overridden for tests.
     *
     * @return string
     */
    abstract public function generate_uuid(): string;

    /**
     * Mutate data before it is exported from the form.
     *
     * This is called by {@see question_edit_form::get_data()} and {@see question_edit_form::get_submitted_data()}. The resulting
     * data might be saved by {@see question_service::upsert_question()} or validated as a draft.
     *
     * The callback is given the entire question data and should mutate the parts relevant to it.
     *
     * @param Closure(array&): void $onexport Callback that receives form data by reference for export conversion
     * @return void
     */
    abstract public function on_export(Closure $onexport): void;

    /**
     * Mutate data from the QPy server before it is added to the mform in {@see question_edit_form::set_data()}.
     *
     * The callback is given the entire question data and should mutate the parts relevant to it.
     *
     * @param Closure(array&): void $onimport Callback that receives form data by reference for import conversion
     * @return void
     */
    abstract public function on_import(Closure $onimport): void;


    /**
     * If the form data already contains a draft item id under the given name, return it. Otherwise, return an unused one.
     *
     * This is usually done by file_get_submitted_draft_itemid, but that doesn't support the editor itself being nested.
     *
     * @param string $itemidname
     * @return array<int, bool> [$draftitemid, $isnew]
     * @throws moodle_exception
     */
    public function get_draft_area_for_upload(string $itemidname): array {
        $draftitemid = $this->moodleform->optional_param($itemidname, null, PARAM_INT);
        if ($draftitemid === null) {
            $draftitemid = file_get_unused_draft_itemid();
            $isnew = true;
        } else {
            // There's already a draft area, which means it must already have been prepared.
            // If we were to try to prepare it again, we would either recreate deleted files or run into unique key violations.
            $isnew = false;
        }

        return [$draftitemid, $isnew];
    }
}
