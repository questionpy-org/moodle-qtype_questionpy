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

use core_question\local\bank\question_edit_contexts;
use qtype_questionpy\api\api;
use qtype_questionpy\form\root_render_context;
use qtype_questionpy\localizer;
use qtype_questionpy\package;
use qtype_questionpy\package_service;
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

    /** @var api */
    private api $api;

    /** @var package_service */
    private package_service $packageservice;

    /**
     * Initialize the form.
     *
     * The moodleform constructor also calls {@see definition()} and {@see _process_submission()}.
     *
     * @param string $submiturl
     * @param object $question
     * @param object $category
     * @param question_edit_contexts $contexts
     * @param bool $formeditable
     */
    public function __construct(string $submiturl, object $question, object $category, question_edit_contexts $contexts,
                                bool   $formeditable = true) {
        $this->api = new api();
        $this->packageservice = new package_service($this->api);

        parent::__construct($submiturl, $question, $category, $contexts, $formeditable);
    }

    /**
     * Add any question-type specific form fields.
     *
     * @param MoodleQuickForm $mform the form being built.
     * @throws moodle_exception
     */
    protected function definition_inner($mform) {
        // TODO: catch moodle_exception?
        // Retrieve packages from the application server.
        $packages = $this->api->get_packages();

        // TODO: Improve flow when editing a question whose package was uploaded.
        // The uploaded and selected package should be shown at the top of the list then.
        if ($packages) {
            // Packages available => allow to switch between search and upload functions.
            $searchorupload = [
                $mform->createElement(
                    "radio", "qpy_package_source", null,
                    get_string("question_package_search", "qtype_questionpy"), "search"
                ),
                $mform->createElement(
                    "radio", "qpy_package_source", null,
                    get_string("question_package_upload", "qtype_questionpy"), "upload"
                ),
            ];
            $mform->addGroup(
                $searchorupload, "qpy_package_source_group",
                get_string('selection_title', 'qtype_questionpy'),
                null, false
            );
            $mform->setDefault("qpy_package_source", "search");
            $mform->addRule("qpy_package_source_group", null, "required");

            $this->definition_package_search($mform, $packages);
            $this->definition_package_upload($mform, ["qpy_package_source", "neq", "upload"]);
        } else {
            // No packages received => show a message and only upload function.
            $mform->addElement("hidden", "qpy_package_source", "upload");
            $mform->setType("qpy_package_source", PARAM_ALPHA);

            $mform->addElement(
                'static', 'questionpy_no_package',
                get_string('selection_title', 'qtype_questionpy'),
                get_string('selection_no_package', 'qtype_questionpy')
            );

            $this->definition_package_upload($mform);
        }

        // While not a button, we need a way of telling moodle not to save the submitted data to the question when the
        // package has simply been changed. The hidden element is enabled from JS when changing packages.
        $mform->registerNoSubmitButton("package_changed");
        $mform->addElement("hidden", "package_changed", "true", ["disabled" => "disabled"]);
        $mform->setType("package_changed", PARAM_RAW);

        $this->definition_package_form($mform);
    }

    /**
     * Render the package list including search function.
     *
     * @param MoodleQuickForm $mform
     * @param package[] $packages available packages
     * @throws coding_exception
     */
    private function definition_package_search(MoodleQuickForm $mform, array $packages): void {
        global $OUTPUT;

        // Searchbar for QuestionPy packages.
        $mform->addElement(
            'text', 'questionpy_package_search', null,
            ['placeholder' => get_string('selection_searchbar', 'qtype_questionpy')]
        );
        $mform->setType('questionpy_package_search', PARAM_TEXT);
        $mform->hideIf("questionpy_package_search", "qpy_package_source", "neq", "search");

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
        $mform->hideIf("questionpy_package_container", "qpy_package_source", "neq", "search");
    }

    /**
     * Render the package upload function.
     *
     * @param MoodleQuickForm $mform
     * @param array|null $hideifargs args for {@see MoodleQuickForm::hideIf()} to be called on elements
     * @throws dml_exception
     */
    private function definition_package_upload(MoodleQuickForm $mform, ?array $hideifargs = null): void {
        $maxkb = get_config('qtype_questionpy', 'max_package_size_kb');
        $mform->addElement(
            "filepicker", "qpy_package", null, null,
            ['maxbytes' => $maxkb * 1024, 'accepted_types' => ['.qpy']]
        );
        $hideifargs && $mform->hideIf("qpy_package", ...$hideifargs);
    }

    /**
     * Render the package-specific form.
     *
     * @param MoodleQuickForm $mform
     * @throws coding_exception
     * @throws moodle_exception
     */
    private function definition_package_form(MoodleQuickForm $mform): void {
        $packagesource = $this->optional_param("qpy_package_source", "search", PARAM_ALPHA);

        $packageapi = null;
        if ($packagesource === "search") {
            // Search available packages is selected.
            $packagehash = $this->optional_param(
                "qpy_package_hash", $this->question->qpy_package_hash ?? null,
                PARAM_ALPHANUM
            );

            if ($packagehash) {
                // A package has been selected from the list.
                $packageapi = $this->api->package($packagehash);
            }
        } else if ($packagesource === "upload") {
            // Upload your own is selected.
            $draftid = $this->optional_param("qpy_package", null, PARAM_INT);

            if ($draftid) {
                // A package has been uploaded. Extract its info.
                $draftfile = $this->packageservice->get_draft_file($draftid);
                $package = $this->api->package_extract_info($draftfile);
                $packageapi = $this->api->package($package->hash, $draftfile);
            }
        }

        if ($packageapi) {
            $response = $packageapi->get_form($this->question->qpy_state ?? null);
            $response->definition->render_to(new root_render_context($this, $mform, "qpy_form"));

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
            $question->{$name} = $value;
        }

        parent::set_data($question);
    }

    /**
     * Dummy stub method - override if you needed to perform some extra validation.
     * If there are errors return array of errors ("fieldname"=>"error message"),
     * otherwise true if ok.
     *
     * Server side rules do not work for uploaded files, implement serverside rules here if needed.
     *
     * @param array $data  array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *                     or an empty array if everything is OK (true allowed for backwards compatibility too).
     * @throws coding_exception
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        $source = $data["qpy_package_source"] ?? null;
        if ($source == "search") {
            if (empty($data["qpy_package_hash"])) {
                $errors["questionpy_package_container"] = get_string("required");
            }
        } else if ($source == "upload") {
            if (empty($data["qpy_package"])) {
                $errors["qpy_package"] = get_string("required");
            }
        } else {
            $errors["qpy_package_source"] = get_string("required");
        }

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
