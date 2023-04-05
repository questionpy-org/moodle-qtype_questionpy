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

/**
 * Defines the editing form for the QuestionPy question type.
 *
 * @package    qtype_questionpy
 * @copyright  2022 Martin Gauk, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use qtype_questionpy\api\api;
use qtype_questionpy\form\root_render_context;
use qtype_questionpy\localizer;
use qtype_questionpy\utils;

/**
 * QuestionPy question editing form definition.
 *
 * @copyright  2022 Martin Gauk, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_questionpy_edit_form extends question_edit_form {

    /** @var array current form data set in {@see definition_inner} and added to the question in {@see set_data}. */
    private array $currentdata = [];

    /**
     * Add any question-type specific form fields.
     *
     * @param MoodleQuickForm $mform the form being built.
     * @throws moodle_exception
     */
    protected function definition_inner($mform) {
        global $OUTPUT, $PAGE;

        $api = new api();

        // TODO: catch moodle_exception?
        // Retrieve packages from the application server.
        $packages = $api->get_packages();

        $uploadlink = $PAGE->get_renderer('qtype_questionpy')->package_upload_link($this->context);

        // No packages received.
        if (!$packages) {
            $mform->addElement(
                'static', 'questionpy_no_package',
                get_string('selection_no_package_title', 'qtype_questionpy'),
                get_string('selection_no_package_text', 'qtype_questionpy')
            );

            $mform->addElement('button', 'uploadlink', 'QPy Package upload form', $uploadlink);
            return;
        }

        // Searchbar for QuestionPy packages.
        $mform->addElement(
            'text', 'questionpy_package_search',
            get_string('selection_title', 'qtype_questionpy'),
            ['placeholder' => get_string('selection_searchbar', 'qtype_questionpy')]
        );

        $mform->setType('questionpy_package_search', PARAM_TEXT);

        // Create group which contains selectable QuestionPy packages.
        $group = array();

        $languages = localizer::get_preferred_languages();

        foreach ($packages as $package) {
            // Get localized package texts.
            $packagearray = $package->as_localized_array($languages);

            $group[] = $mform->createElement(
                'radio', 'qpy_package_hash',
                $OUTPUT->render_from_template('qtype_questionpy/package', $packagearray),
                '', $package->hash
            );
        }
        $mform->addGroup($group, 'questionpy_package_container', '', '</br>', false);
        $mform->addRule(
            'questionpy_package_container',
            get_string('selection_required', 'qtype_questionpy'), 'required'
        );

        $mform->addElement('button', 'uploadlink', 'QPy Package upload form', $uploadlink);

        // While not a button, we need a way of telling moodle not to save the submitted data to the question when the
        // package has simply been changed. The hidden element is enabled from JS when changing packages.
        $mform->registerNoSubmitButton("package_changed");
        $mform->addElement("hidden", "package_changed", "true", ["disabled" => "disabled"]);
        $mform->setType("package_changed", PARAM_RAW);

        $packagehash = $this->optional_param(
            "qpy_package_hash",
            $this->question->qpy_package_hash ?? null, PARAM_ALPHANUM
        );
        if ($packagehash) {
            $response = $api->get_question_edit_form($packagehash, $this->question->qpy_state ?? null);

            $context = new root_render_context($this, $mform, "qpy_form", $response->formdata);
            $response->definition->render_to($context);

            // Used by set_data.
            $this->currentdata = $response->formdata;
        }
    }

    /**
     * Load in existing data as form defaults. Usually new entry defaults are stored directly in
     * form definition (new entry form); this function is used to load in data where values
     * already exist and data is being edited (edit entry form).
     *
     * @param stdClass $question
     */
    public function set_data($question): void {
        // The question text will be provided by the qpy package. This field is hidden by CSS, but we need
        // to define a default value to satisfy the base methods in question_edit_form.
        $question->questiontext = '.';

        // Current form data was returned by the server along with the form definition in definition_inner.
        foreach (utils::flatten($this->currentdata, "qpy_form") as $name => $value) {
            $this->question->{$name} = $value;
        }

        parent::set_data($question);
    }

    /**
     * Load in existing data as form defaults. Usually new entry defaults are stored directly in
     * form definition (new entry form); this function is used to load in data where values
     * already exist and data is being edited (edit entry form).
     *
     * note: $slashed param removed
     *
     * @param array $data
     * @param array $files
     * @return array $errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        // TODO.

        return $errors;
    }

    /**
     * Override this in the subclass to question type name.
     *
     * @return string the question type name, should be the same as the name() method
     *      in the question type class.
     */
    public function qtype() {
        return 'questionpy';
    }
}
