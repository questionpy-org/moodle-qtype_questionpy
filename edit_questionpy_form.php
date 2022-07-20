<?php
// This file is part of Moodle - http://moodle.org/
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

use qtype_questionpy\api;
use qtype_questionpy\package;


/**
 * QuestionPy question editing form definition.
 *
 * @copyright  2022 Martin Gauk, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_questionpy_edit_form extends question_edit_form {

    /**
     * Add any question-type specific form fields.
     *
     * @param MoodleQuickForm $mform the form being built.
     */
    protected function definition_inner($mform) {
        global $OUTPUT;

        // Retrieve packages from the application server.
        $packages = api::get_packages();

        // No packages received.
        if (!$packages) {
            $mform->addElement('static', 'questionpy_no_package',
                get_string('selection_no_package_title', 'qtype_questionpy'),
                get_string('selection_no_package_text', 'qtype_questionpy'));

            return;
        }

        // Searchbar for QuestionPy packages.
        $mform->addElement('text', 'questionpy_package_search',
            get_string('selection_title', 'qtype_questionpy'),
            ['placeholder' => get_string('selection_searchbar', 'qtype_questionpy')]);

        $mform->setType('questionpy_package_search', PARAM_TEXT);

        // Create group which contains selectable QuestionPy packages.
        $group = array();

        foreach ($packages as $package) {
            // Get localized package texts.
            $localizedpackage = package::localize($package);

            $group[] = $mform->createElement('radio', 'questionpy_package_hash',
                $OUTPUT->render_from_template('qtype_questionpy/package', $localizedpackage),
                '', $package['package_hash']);
        }

        $mform->addGroup($group, 'questionpy_package_container', '', '</br>');
    }

    /**
     * Perform any preprocessing needed on the data passed to {@see set_data()}
     * before it is used to initialise the form.
     *
     * @param object $question the data being passed to the form.
     * @return object $question the modified data.
     */
    public function data_preprocessing($question) {
        $question = parent::data_preprocessing($question);

        // TODO.

        return $question;
    }

    /**
     * Load in existing data as form defaults. Usually new entry defaults are stored directly in
     * form definition (new entry form); this function is used to load in data where values
     * already exist and data is being edited (edit entry form).
     *
     * @param stdClass $question
     */
    public function set_data($question) {
        // The question text will be provided by the qpy package. This field is hidden by CSS, but we need
        // to define a default value to satisfy the base methods in question_edit_form.
        $question->questiontext = '.';

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
