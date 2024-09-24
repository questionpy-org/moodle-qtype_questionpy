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

use core\di;
use core_question\local\bank\question_edit_contexts;
use qtype_questionpy\api\api;
use qtype_questionpy\form\context\root_render_context;
use qtype_questionpy\localizer;
use qtype_questionpy\package\package;
use qtype_questionpy\package\package_base;
use qtype_questionpy\package\package_version;
use qtype_questionpy\package_file_service;

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
                                bool $formeditable = true) {
        $this->api = di::get(api::class);
        $this->packagefileservice = di::get(package_file_service::class);

        parent::__construct($submiturl, $question, $category, $contexts, $formeditable);
    }

    /**
     * Adds package upload element to form.
     *
     * @param MoodleQuickForm $mform the form being built.
     * @throws moodle_exception
     */
    private function definition_package_upload(MoodleQuickForm $mform) {
        global $PAGE;

        $maxkb = get_config('qtype_questionpy', 'max_package_size_kb');
        $mform->addElement(
            'filepicker',
            'qpy_package_file',
            null,
            null,
            ['maxbytes' => $maxkb * 1024, 'accepted_types' => ['.qpy']]
        );
        $mform->hideIf('qpy_package_file', 'qpy_package_source', 'neq', 'upload');

        $PAGE->requires->js_call_amd('qtype_questionpy/edit_question', 'initUploadForm');
    }

    /**
     * Adds package selection container to form.
     *
     * @param MoodleQuickForm $mform the form being built.
     * @throws moodle_exception
     */
    private function definition_package_search_container(MoodleQuickForm $mform): void {
        global $OUTPUT;

        // Create a group which contains the package container - the group is used to simplify the styling.
        // TODO: get limit from settings.
        $group[] = $mform->createElement('html', $OUTPUT->render_from_template(
            'qtype_questionpy/package_search/area',
            ['contextid' => $this->context->get_course_context()->id, 'limit' => 10]
        ));
        $mform->addGroup($group, 'qpy_package_container');
        $mform->hideIf('qpy_package_container', 'qpy_package_source', 'neq', 'search');
    }

    /**
     * Renders question edit form of a specific package version.
     *
     * @param MoodleQuickForm $mform the form being built.
     * @param package_base $package
     * @param string $packagehash
     * @param string $packageversion
     * @param bool|null $isfavourite null if the package can not be marked as favourite
     * @param stored_file|null $file
     * @throws moodle_exception
     */
    private function definition_package_settings(MoodleQuickForm $mform, package_base $package, string $packagehash,
                                                 string $packageversion, ?bool $isfavourite = null,
                                                 ?stored_file $file = null): void {
        global $OUTPUT;

        // Get localized package array.
        $languages = localizer::get_preferred_languages();
        $packagearray = $package->as_localized_array($languages);
        $packagearray['contextid'] = $this->context->get_course_context()->id;
        $packagearray['isselected'] = true;
        $packagearray['versions'] = ['hash' => $packagehash, 'version' => $packageversion];
        $packagearray['islocal'] = !is_null($file);
        $packagearray['isfavourite'] = $isfavourite;
        $packagearray['ismarkableasfavourite'] = !is_null($isfavourite);
        $group = [];
        $group[] = $mform->createElement(
            'html',
            $OUTPUT->render_from_template('qtype_questionpy/package/package_selection', $packagearray)
        );
        $mform->addGroup($group, '', get_string('selection_title_selected', 'qtype_questionpy'));

        // Stores the currently selected package hash.
        if ($file) {
            $mform->addElement('hidden', 'qpy_package_file_hash', $packagehash);
            $mform->setType('qpy_package_file_hash', PARAM_RAW);
        } else {
            $mform->addElement('hidden', 'qpy_package_hash', $packagehash);
            $mform->setType('qpy_package_hash', PARAM_RAW);
        }

        // Render question edit form.
        $response = $this->api->package($packagehash, $file)->get_question_edit_form($this->question->qpy_state ?? null);
        $context = new root_render_context($this, $mform, 'qpy_form', $response->formdata);
        $response->definition->render_to($context);

        // Used by set_data.
        $this->currentdata = $response->formdata;
    }

    /**
     * Renders question edit form of a specific package version that was or is uploaded by the user.
     *
     * @param MoodleQuickForm $mform the form being built.
     * @param bool $fromdraft
     * @throws moodle_exception
     */
    private function definition_package_settings_upload(MoodleQuickForm $mform, bool $fromdraft) {
        $mform->addElement('hidden', 'qpy_package_source', 'upload');
        $mform->setType('qpy_package_source', PARAM_ALPHA);

        if ($fromdraft) {
            $draftid = $this->optional_param('qpy_package_file', null, PARAM_INT);
            $mform->addElement('hidden', 'qpy_package_file', $draftid);
            $mform->setType('qpy_package_file', PARAM_INT);
            $file = $this->packagefileservice->get_draft_file($draftid);
        } else {
            $qpyid = $this->question->qpy_id;
            $file = $this->packagefileservice->get_file_for_local_question($qpyid, $this->context->get_course_context()->id);
            $mform->addElement('hidden', 'qpy_package_path_name_hash', $file->get_pathnamehash());
            $mform->setType('qpy_package_path_name_hash', PARAM_ALPHANUM);
        }
        $package = api::extract_package_info($file);

        $this->definition_package_settings($mform, $package, $package->hash, $package->version, file: $file);
    }

    /**
     * Renders question edit form of a specific package version that was or is selected by the user.
     *
     * @param MoodleQuickForm $mform the form being built.
     * @throws moodle_exception
     */
    private function definition_package_settings_search(MoodleQuickForm $mform) {
        global $USER;

        $mform->addElement('hidden', 'qpy_package_source', 'search');
        $mform->setType('qpy_package_source', PARAM_ALPHA);

        // Get package version.
        $packagehash = $this->optional_param('qpy_package_hash', $this->question->qpy_package_hash ?? null, PARAM_ALPHANUM);
        $pkgversion = package_version::get_by_hash($packagehash);

        if ($pkgversion) {
            $package = package::get_by_version($pkgversion->id);
            // Get favourite status.
            $usercontext = context_user::instance($USER->id);
            $ufservice = \core_favourites\service_factory::get_service_for_user_context($usercontext);
            $isfavourite = $ufservice->favourite_exists('qtype_questionpy', 'package', $package->id, $usercontext);
            $version = $pkgversion->version;
        } else {
            $api = new api();
            $package = $api->get_package_info($packagehash);
            $isfavourite = null;
            $version = $package->version;
        }

        $this->definition_package_settings($mform, $package, $packagehash, $version, $isfavourite);
    }

    /**
     * Add any question-type specific form fields.
     *
     * @param MoodleQuickForm $mform the form being built.
     * @throws moodle_exception
     */
    protected function definition_inner($mform): void {
        $source = $this->optional_param('qpy_package_source', null, PARAM_ALPHA);
        $hash = $this->optional_param('qpy_package_hash', null, PARAM_ALPHANUM) ??
            $this->optional_param('qpy_package_file_hash', null, PARAM_ALPHANUM);
        $selected = $this->optional_param('qpy_package_selected', !empty($hash), PARAM_BOOL);

        // We are editing an existing question, if no source is set but the qpy_id is.
        $editing = is_null($source) && isset($this->question->qpy_id);

        if (($uploading = ($selected && $source === 'upload')) || ($editing && $this->question->qpy_is_local)) {
            // We are either uploading a package or editing a question with an uploaded package.
            $pathnamehash = $this->optional_param('qpy_package_path_name_hash', null, PARAM_ALPHANUM);
            self::definition_package_settings_upload($mform, $uploading && is_null($pathnamehash));
        } else if (($selected && $source === 'search') || ($editing && !$this->question->qpy_is_local)) {
            // We are either selecting a package or editing a question with a selected package.
            self::definition_package_settings_search($mform);
        } else {
            // View package search container and file picker.
            $searchorupload = [
                $mform->createElement(
                    'radio',
                    'qpy_package_source',
                    null,
                    get_string('question_package_search', 'qtype_questionpy'),
                    'search'
                ),
                $mform->createElement(
                    'radio',
                    'qpy_package_source',
                    null,
                    get_string('question_package_upload', 'qtype_questionpy'),
                    'upload'
                ),
            ];
            $mform->addGroup(
                $searchorupload,
                'qpy_package_source_group',
                get_string('selection_title', 'qtype_questionpy'),
                null,
                false
            );
            $mform->setDefault('qpy_package_source', 'search');
            $mform->addRule('qpy_package_source_group', null, 'required');

            self::definition_package_search_container($mform);
            self::definition_package_upload($mform);

            // Stores the currently selected package hash.
            $mform->addElement('hidden', 'qpy_package_hash', '');
            $mform->setType('qpy_package_hash', PARAM_RAW);
        }

        // While not a button, we need a way of telling moodle not to save the submitted data to the question when the
        // package has simply been changed. The hidden element is enabled from JS when a package is selected or changed.
        $mform->registerNoSubmitButton('qpy_package_selected');
        $mform->addElement('hidden', 'qpy_package_selected', !empty($hash), ['disabled' => 'disabled']);
        $mform->setType('qpy_package_selected', PARAM_BOOL);
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
     * Validates selected or uploaded package.
     *
     * @param array $data
     * @param array $files
     * @return array $errors
     * @throws moodle_exception
     */
    public function validation($data, $files) {
        global $USER;
        $errors = parent::validation($data, $files);

        $source = $data['qpy_package_source'] ?? null;
        if ($source == 'search') {
            if (empty($data['qpy_package_hash'])) {
                $errors['qpy_package_container'] = get_string('required');
            }
        } else if ($source == 'upload') {
            $filestorage = get_file_storage();
            if (isset($data['qpy_package_path_name_hash'])) {
                if (!$filestorage->file_exists_by_hash($data['qpy_package_path_name_hash'])) {
                    $errors['qpy_package_file'] = get_string('required');
                }
            } else {
                $usercontext = context_user::instance($USER->id);
                if (!$filestorage->get_area_files($usercontext->id, 'user', 'draft', $data['qpy_package_file'])) {
                    $errors['qpy_package_file'] = get_string('required');
                }
            }
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
