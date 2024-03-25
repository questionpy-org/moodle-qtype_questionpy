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
use qtype_questionpy\form\context\root_render_context;
use qtype_questionpy\localizer;
use qtype_questionpy\package\package;
use qtype_questionpy\package\package_version;

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
     * Renders package selection form.
     *
     * @param MoodleQuickForm $mform the form being built.
     * @throws moodle_exception
     */
    protected function definition_package_selection(MoodleQuickForm $mform): void {
        global $OUTPUT, $PAGE;

        $uploadlink = $PAGE->get_renderer('qtype_questionpy')->package_upload_link($this->context);

        $mform->setType('questionpy_package_search', PARAM_TEXT);

        // Create a group which contains the package container - the group is used to simplify the styling.
        // TODO: get limit from settings.
        $group[] = $mform->createElement('html', $OUTPUT->render_from_template('qtype_questionpy/package_search/area',
            ['contextid' => $PAGE->context->get_course_context()->id, 'limit' => 10]));
        $mform->addGroup($group, 'questionpy_package_container', get_string('selection_title', 'qtype_questionpy'), null, false);
        $mform->addRule(
            'questionpy_package_container',
            get_string('selection_required', 'qtype_questionpy'), 'required'
        );

        $mform->addElement('button', 'uploadlink', 'QPy Package upload form', $uploadlink);
    }

    /**
     * Renders question edit form of a specific package version.
     *
     * @param MoodleQuickForm $mform the form being built.
     * @param string $packagehash the hash of the package.
     * @throws moodle_exception
     */
    protected function definition_package_settings(MoodleQuickForm $mform, string $packagehash): void {
        global $OUTPUT, $USER, $PAGE;

        $pkgversion = package_version::get_by_hash($packagehash);
        $package = package::get_by_version($pkgversion->id);

        $languages = localizer::get_preferred_languages();
        $packagearray = $package->as_localized_array($languages);
        $packagearray['selected'] = true;
        $packagearray['versions'] = ['hash' => $pkgversion->hash, 'version' => $pkgversion->version];
        $packagearray['contextid'] = $PAGE->context->id;

        $usercontext = context_user::instance($USER->id);
        $ufservice = \core_favourites\service_factory::get_service_for_user_context($usercontext);
        $packagearray['isfavourite'] = $ufservice->favourite_exists('qtype_questionpy', 'package', $package->id, $usercontext);

        $group = [];
        $group[] = $mform->createElement(
            'html', $OUTPUT->render_from_template('qtype_questionpy/package/package_selection', $packagearray)
        );
        $mform->addGroup($group, '', get_string('selection_title_selected', 'qtype_questionpy'));

        // Render question edit form.
        $api = new api();
        $response = $api->get_question_edit_form($packagehash, $this->question->qpy_state ?? null);

        $context = new root_render_context($this, $mform, 'qpy_form', $response->formdata);
        $response->definition->render_to($context);

        // Used by set_data.
        $this->currentdata = $response->formdata;
    }


    /**
     * Add any question-type specific form fields.
     *
     * @param MoodleQuickForm $mform the form being built.
     * @throws moodle_exception
     */
    protected function definition_inner($mform): void {
        // Check if package is already selected.
        $packagehash = $this->optional_param(
            'qpy_package_hash',
            $this->question->qpy_package_hash ?? null, PARAM_ALPHANUM
        );

        if ($packagehash) {
            self::definition_package_settings($mform, $packagehash);
        } else {
            self::definition_package_selection($mform);
        }

        // Stores the currently selected package hash.
        $mform->addElement('hidden', 'qpy_package_hash', '');
        $mform->setType('qpy_package_hash', PARAM_RAW);

        // While not a button, we need a way of telling moodle not to save the submitted data to the question when the
        // package has simply been changed. The hidden element is enabled from JS when a package is selected or changed.
        $mform->registerNoSubmitButton('qpy_package_changed');
        $mform->addElement('hidden', 'qpy_package_changed', '1', ['disabled' => 'disabled']);
        $mform->setType('qpy_package_changed', PARAM_BOOL);
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

        $question->qpy_form = $this->currentdata;

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
