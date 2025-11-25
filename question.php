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
 * QuestionPy question definition class.
 *
 * @package    qtype_questionpy
 * @copyright  2022 Martin Gauk, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use qtype_questionpy\constants;
use qtype_questionpy\exception\request_error;
use qtype_questionpy\local\api\api;
use qtype_questionpy\local\api\attempt;
use qtype_questionpy\local\api\attempt_ui;
use qtype_questionpy\local\api\package_dependency;
use qtype_questionpy\local\api\question_data;
use qtype_questionpy\local\api\scoring_code;
use qtype_questionpy\local\api\wysiwyg_editor_data;
use qtype_questionpy\local\attempt_ui\question_ui_metadata_extractor;
use qtype_questionpy\local\files\file_metadata;
use qtype_questionpy\local\files\response_file_service;
use qtype_questionpy\question_bridge_base;
use qtype_questionpy\utils;

/**
 * Represents a QuestionPy question.
 *
 * @copyright  2022 Martin Gauk, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_questionpy_question extends question_graded_automatically_with_countback {
    // Properties which do not change between attempts.
    /** @var api */
    private api $api;
    /** @var response_file_service */
    private response_file_service $rfs;
    /** @var string */
    public string $packagehash;
    /** @var string */
    public string $questionstate;
    /** @var question_data */
    public question_data $questiondata;
    /** @var stored_file|null */
    private ?stored_file $packagefile;

    // Properties which do change between attempts (i.e. are modified by start_attempt and apply_attempt_state).
    /** @var string */
    public string $attemptstate;
    /** @var string|null */
    public ?string $scoringstate;
    /** @var attempt_ui */
    public attempt_ui $ui;
    /** @var question_ui_metadata_extractor $metadata */
    public question_ui_metadata_extractor $metadata;
    /** @var package_dependency[] */
    public array $packagedependencies;
    /** @var bool set by {@see start_attempt} and {@see apply_attempt_state} when they can't load the attempt */
    public bool $errorduringload;

    /** @var qbehaviour_questionpy|null $behaviour */
    public ?qbehaviour_questionpy $behaviour = null;

    /** @var question_bridge_base|null $bridge bridge to get additional information about an attempt */
    public ?question_bridge_base $bridge = null;

    /**
     * Initialize a new question. Called from {@see qtype_questionpy::make_question_instance()}.
     *
     * @param string $packagehash
     * @param string $questionstate
     * @param question_data $questiondata
     * @param stored_file|null $packagefile
     * @param api $api
     * @param response_file_service $rfs
     */
    public function __construct(
        string $packagehash,
        string $questionstate,
        question_data $questiondata,
        ?stored_file $packagefile,
        api $api,
        response_file_service $rfs
    ) {
        parent::__construct();
        $this->api = $api;
        $this->rfs = $rfs;
        $this->packagehash = $packagehash;
        $this->questionstate = $questionstate;
        $this->questiondata = $questiondata;
        $this->packagefile = $packagefile;
    }

    /**
     * Updates the ui property, metadata extractor and package dependencies.
     *
     * @param attempt $attempt
     */
    private function update_attempt(attempt $attempt): void {
        $this->ui = $attempt->ui;
        $this->metadata = new question_ui_metadata_extractor($this->ui->formulation);
        $this->packagedependencies = $attempt->packagedependencies;
    }

    /**
     * Start a new attempt at this question, storing any information that will
     * be needed later in the step.
     *
     * This is where the question can do any initialisation required on a
     * per-attempt basis. For example, this is where the multiple choice
     * question type randomly shuffles the choices (if that option is set).
     *
     * Any information about how the question has been set up for this attempt
     * should be stored in the $step, by calling $step->set_qt_var(...).
     *
     * @param question_attempt_step $step The first step of the {@see question_attempt}
     *      being started. Can be used to store state.
     * @param int $variant which variant of this question to start. Will be between
     *      1 and {@see get_num_variants()} inclusive.
     * @throws Throwable
     */
    public function start_attempt(question_attempt_step $step, $variant): void {
        global $PAGE;

        try {
            $attributes = $this->get_requested_attributes();
            $attempt = $this->api->package($this->packagehash, $this->packagefile)
                ->start_attempt($this->questionstate, $variant, $attributes);

            $this->attemptstate = $attempt->attemptstate;
            $step->set_qt_var(constants::QT_VAR_ATTEMPT_STATE, $attempt->attemptstate);
            $this->scoringstate = null;
            $this->update_attempt($attempt);
        } catch (Throwable $t) {
            $this->errorduringload = true;
            // Trigger error event.
            $qa = $this->get_behaviour()->get_qa();
            $params = [
                'context' => $PAGE->context,
                'relateduserid' => $step->get_user_id(),
                'other' => [
                    'questionid' => $this->id,
                    'attemptusageid' => $qa->get_usage_id(),
                    'attemptslot' => $qa->get_slot(),
                    'errormessage' => $t->getMessage(),
                ],
            ];
            $event = \qtype_questionpy\event\starting_attempt_failed::create($params);
            $event->trigger();
            debugging($event->get_description(), backtrace: $t->getTrace());
            throw $t;
        }

        $this->errorduringload = false;
    }

    /**
     * When an in-progress {@see question_attempt} is re-loaded from the
     * database, this method is called so that the question can re-initialise
     * its internal state as needed by this attempt.
     *
     * For example, the multiple choice question type needs to set the order
     * of the choices to the order that was set up when start_attempt was called
     * originally. All the information required to do this should be in the
     * $step object, which is the first step of the question_attempt being loaded.
     *
     * @param question_attempt_step $step The first step of the {@see question_attempt}
     *      being loaded.
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function apply_attempt_state(question_attempt_step $step) {
        global $PAGE;

        $attemptstate = $step->get_qt_var(constants::QT_VAR_ATTEMPT_STATE);
        if (is_null($attemptstate)) {
            // There was a request error at start_attempt.
            return;
        }

        $qa = $this->get_behaviour()->get_qa();

        $this->attemptstate = $attemptstate;
        $this->scoringstate = $qa->get_last_qt_var(constants::QT_VAR_SCORING_STATE);

        /* TODO: This method is also called from question_attempt->regrade and
                 question_attempt->start_question_based_on, where we shouldn't need to get the UI. */
        try {
            $lastresponsestep = $qa->get_last_step_with_qt_var(constants::QT_VAR_RESPONSE);
            $lastresponse = utils::get_qpy_response($lastresponsestep->get_qt_data());

            $allfiles = $this->rfs->get_all_files_from_qt_data($lastresponsestep->get_qt_data());
            $editors = utils::get_qpy_editors_data($lastresponsestep->get_qt_data());
            array_walk(
                $editors,
                fn(&$editordata, $editorname) => $editordata = $this->build_wysiwyg_data($editorname, $editordata, $allfiles)
            );

            $attributes = $this->get_requested_attributes();

            $attempt = $this->api->package($this->packagehash, $this->packagefile)
                ->view_attempt(
                    $this->questionstate,
                    $attributes,
                    $this->attemptstate,
                    $this->scoringstate,
                    $lastresponse,
                    $editors,
                );
            $this->update_attempt($attempt);
            $this->errorduringload = false;
        } catch (Throwable $t) {
            $this->errorduringload = true;
            // Trigger error event.
            $params = [
                'context' => $PAGE->context,
                'relateduserid' => $step->get_user_id(),
                'other' => [
                    'questionid' => $this->id,
                    'questionattemptid' => $this->get_behaviour()->get_qa()->get_database_id(),
                    'errormessage' => $t->getMessage(),
                ],
            ];
            $event = \qtype_questionpy\event\viewing_attempt_failed::create($params);
            $event->trigger();
            debugging($event->get_description(), backtrace: $t->getTrace());
        }
    }

    /**
     * Generate a brief, plain-text, summary of this question. This is used by
     * various reports. This should show the particular variant of the question
     * as presented to students. For example, the calculated question type would
     * fill in the particular numbers that were presented to the student.
     * This method will return null if such a summary is not possible, or
     * inappropriate.
     *
     * @return string|null a plain text summary of this question.
     */
    public function get_question_summary() {
        // Parent method is not called, because we do not use questiontext.
        return null;
    }

    /**
     * Checks that our behaviour has been set, which happens in {@see qbehaviour_questionpy::__construct}.
     *
     * @throws coding_exception
     */
    private function get_behaviour(): qbehaviour_questionpy {
        if ($this->behaviour === null) {
            throw new coding_exception(
                'qtype_questionpy_question->behaviour is not set, does the question use the wrong behaviour?'
            );
        }
        return $this->behaviour;
    }

    /**
     * What data may be included in the form submission when a student submits
     * this question in its current state?
     *
     * This information is used in calls to optional_param. The parameter name
     * has {@see question_attempt::get_field_prefix()} automatically prepended.
     *
     * @return array|string variable name => PARAM_... constant, or, as a special case
     *      that should only be used in unavoidable, the constant question_attempt::USE_RAW_DATA
     *      meaning take all the raw submitted data belonging to this question.
     */
    public function get_expected_data(): array|string {
        return [
            constants::QT_VAR_RESPONSE => PARAM_RAW_TRIMMED,
            constants::QT_VAR_EDITORS => PARAM_RAW_TRIMMED,
            constants::QT_VAR_RESPONSE_FILES => question_attempt::PARAM_FILES,
        ];
    }

    /**
     * What data would need to be submitted to get this question correct.
     * If there is more than one correct answer, this method should just
     * return one possibility. If it is not possible to compute a correct
     * response, this method should return null.
     *
     * @return array|null parameter name => value.
     */
    public function get_correct_response(): ?array {
        if ($this->errorduringload) {
            // There was an error -> we cannot compute the correct response.
            return null;
        }

        $correctresponse = $this->metadata->get_correct_response();
        if ($correctresponse === null) {
            return null;
        }

        return [
            constants::QT_VAR_RESPONSE => json_encode((object)$correctresponse),
        ];
    }

    /**
     * Used by many of the behaviours, to work out whether the student's
     * response to the question is complete. That is, whether the question attempt
     * should move to the COMPLETE or INCOMPLETE state.
     *
     * @param array $response responses, as returned by
     *                        {@see question_attempt_step::get_qt_data()}.
     * @return bool whether this response is a complete answer to this question.
     * @throws \core\exception\coding_exception
     */
    public function is_complete_response(array $response): bool {
        $qpyresponse = utils::get_qpy_response($response);
        if ($qpyresponse === null) {
            return false;
        }

        if ($this->errorduringload) {
            // There was an error -> if no data was provided we want the question state to be set to INCOMPLETE.
            return !empty($qpyresponse);
        }

        foreach ($this->metadata->get_required_response_fields() as $requiredfield) {
            if (!isset($qpyresponse->{$requiredfield}) || $qpyresponse->{$requiredfield} === '') {
                return false;
            }
        }

        $editors = utils::get_qpy_editors_data($response);
        foreach ($this->metadata->get_required_editors() as $requirededitorname) {
            if (!isset($editors[$requirededitorname]) || $editors[$requirededitorname]->text === '') {
                return false;
            }
        }

        // TODO: Check if file uploads have their min-files satisfied (#220).

        return true;
    }

    /**
     * Used by many of the behaviours to determine whether the student's
     * response has changed. This is normally used to determine that a new set
     * of responses can safely be discarded.
     *
     * @param array $prevresponse the responses previously recorded for this question,
     *                            as returned by {@see question_attempt_step::get_qt_data()}
     * @param array $newresponse the new responses, in the same format.
     * @return bool whether the two sets of responses are the same - that is
     *                            whether the new set of responses can safely be discarded.
     * @throws \core\exception\coding_exception
     */
    public function is_same_response(array $prevresponse, array $newresponse): bool {
        if (utils::get_qpy_response($prevresponse) != utils::get_qpy_response($newresponse)) {
            return false;
        }

        if (utils::get_qpy_editors_data($prevresponse) != utils::get_qpy_editors_data($newresponse)) {
            return false;
        }

        // We compare the hashes question_file_saver generates over all files.
        $prevfilehash = strval($prevresponse[constants::QT_VAR_RESPONSE_FILES] ?? '');
        $newfilehash = strval($newresponse[constants::QT_VAR_RESPONSE_FILES] ?? '');
        if ($prevfilehash !== $newfilehash) {
            return false;
        }

        return true;
    }

    /**
     * Produce a plain text summary of a response.
     *
     * @param array $response a response, as might be passed to {@see grade_response()}.
     * @return string a plain text summary of that response, that could be used in reports.
     * @throws moodle_exception
     */
    public function summarise_response(array $response) {
        // TODO: Include WYSIWYG editor data.

        $summary = '';

        $qpyresponse = utils::get_qpy_response($response);

        if ($qpyresponse) {
            $qpyresponse = get_object_vars($qpyresponse);
            $dynamicdata = get_object_vars($qpyresponse['data'] ?? (object)[]);
            unset($qpyresponse['data']);

            if ($qpyresponse) {
                $summary .= get_string('response_summary_form_data', 'qtype_questionpy') . ':';

                ksort($qpyresponse);
                foreach ($qpyresponse as $key => $value) {
                    $summary .= $key . ': ' . $value . ';';
                }
            }

            if ($dynamicdata) {
                $summary .= get_string('response_summary_dynamic_data', 'qtype_questionpy') . ':';
                foreach ($dynamicdata as $key => $value) {
                    $summary .= $key . ': ' . json_encode($value) . ';';
                }
            }
        }

        $fileloader = $response[constants::QT_VAR_RESPONSE_FILES] ?? null;
        if ($fileloader) {
            $summary .= get_string('response_summary_files', 'qtype_questionpy') . ':';
            foreach ($fileloader->get_files() as $file) {
                $summary .= $file->get_filename() . ' (' . display_size($file->get_filesize()) . ');';
            }
        }

        return $summary;
    }

    /**
     * In situations where is_gradable_response() returns false, this method
     * should generate a description of what the problem is.
     *
     * @param array $response responses
     * @return string the message.
     */
    public function get_validation_error(array $response) {
        // This method is only called by the renderer of each question type.
        // We do not call it in our renderer, so we can just return an empty string.
        // The question package is responsible for displaying validation errors using JavaScript.
        return '';
    }

    /**
     * Joins the raw editor data with the files that belong to it and returns a {@see wysiwyg_editor_data} object.
     *
     * @param string $editorname
     * @param object $rawdata
     * @param stored_file[] $allfiles
     * @return wysiwyg_editor_data
     * @throws coding_exception
     */
    private function build_wysiwyg_data(string $editorname, object $rawdata, array $allfiles): wysiwyg_editor_data {
        $filemetas = [];
        foreach (response_file_service::filter_combined_files_for_field($allfiles, $editorname) as $filename => $file) {
            $filemetas[] = file_metadata::from_stored_file($file, overridename: $filename);
        }

        // TODO: Turn @@PLUGINFILE@@-links into QPy-URLs?

        return new wysiwyg_editor_data(
            text: $rawdata->text,
            textformat: $rawdata->format,
            files: $filemetas,
        );
    }

    /**
     * Grade a response to the question, returning a fraction between
     * get_min_fraction() and get_max_fraction(), and the corresponding {@see question_state}
     * right, partial or wrong.
     *
     * @param array $response responses, as returned by
     *                        {@see question_attempt_step::get_qt_data()}.
     * @return array (float, integer) the fraction, and the state.
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function grade_response(array $response): array {
        global $PAGE;

        try {
            $attributes = $this->get_requested_attributes();

            $allfiles = $this->rfs->get_all_files_from_qt_data($response);
            $editors = utils::get_qpy_editors_data($response);
            array_walk(
                $editors,
                fn(&$editordata, $editorname) => $editordata = $this->build_wysiwyg_data($editorname, $editordata, $allfiles)
            );

            $attemptscored = $this->api->package($this->packagehash, $this->packagefile)->score_attempt(
                $this->questionstate,
                $attributes,
                $this->attemptstate,
                $this->scoringstate,
                utils::get_qpy_response($response) ?? (object)[],
                $editors
            );
            $this->update_attempt($attemptscored);
        } catch (Throwable $t) {
            // Trigger error event.
            $params = [
                'context' => $PAGE->context,
                // TODO: It would be nice to set a 'relateduserid'.
                'other' => [
                    'questionid' => $this->id,
                    'errormessage' => $t->getMessage(),
                ],
            ];
            $event = \qtype_questionpy\event\grading_response_failed::create($params);
            $event->trigger();
            debugging($event->get_description(), backtrace: $t->getTrace());

            // Our question behaviour constructs an error message containing this error representation.
            $error = $t instanceof request_error ? $t->requesterrorcode->value : $t::class . "({$t->getCode()})";
            $this->get_behaviour()->get_pending_step()->set_qt_var(constants::QT_VAR_ERROR, $error);

            // As the server was not able to score the response, we mark this question with manual scoring.
            return [0, question_state::$needsgrading];
        }

        // Persist scoring state.
        $this->scoringstate = $attemptscored->scoringstate;
        $this->get_behaviour()->get_pending_step()
            ->set_qt_var(constants::QT_VAR_SCORING_STATE, $attemptscored->scoringstate);

        $newqstate = match ($attemptscored->scoringcode) {
            scoring_code::automatically_scored => question_state::graded_state_for_fraction($attemptscored->score),
            scoring_code::needs_manual_scoring => question_state::$needsgrading,
            scoring_code::response_not_scorable => question_state::$gaveup,
            scoring_code::invalid_response => question_state::$invalid,
        };
        return [$attemptscored->score, $newqstate];
    }

    /**
     * Work out a final grade for this attempt, taking into account all the
     * tries the student made.
     *
     * @param array $responses the response for each try. Each element of this
     *                         array is a response array, as would be passed to {@see grade_response()}.
     *                         There may be between 1 and $totaltries responses.
     * @param int $totaltries The maximum number of tries allowed.
     * @return numeric the fraction that should be awarded for this
     *                         sequence of response.
     * @throws coding_exception
     */
    public function compute_final_grade($responses, $totaltries) {
        // TODO: This is necessary to support interactive countback.
        throw new coding_exception('not implemented');
    }

    /**
     * Wraps the behaviour which would ordinarily have been used in a {@see qbehaviour_questionpy}.
     *
     * @param question_attempt $qa
     * @param string $preferredbehaviour the requested type of behaviour.
     * @return question_behaviour
     * @throws coding_exception
     */
    public function make_behaviour(question_attempt $qa, $preferredbehaviour): question_behaviour {
        question_engine::load_behaviour_class('questionpy');
        $delegate = parent::make_behaviour($qa, $preferredbehaviour);
        return new qbehaviour_questionpy($qa, $preferredbehaviour, $delegate);
    }

    /**
     * Checks whether the user is allowed to be served a particular file.
     *
     * @param question_attempt $qa the question attempt being displayed.
     * @param question_display_options $options the options that control display of the question.
     * @param string $component the name of the component we are serving files for.
     * @param string $filearea the name of the file area.
     * @param array $args the remaining bits of the file path.
     * @param bool $forcedownload whether the user must be forced to download the file.
     * @return bool true if the user can access this file.
     */
    public function check_file_access($qa, $options, $component, $filearea, $args, $forcedownload) {
        if ($component == 'question' && $filearea == 'response_files') {
            // Response files are always visible.
            // Assuming that the plugin that displays our question already checked if the user
            // is allowed to see the question attempt.
            return true;
        }

        // Parent method is not called, because we do not use questiontext and generalfeedback.
        return false;
    }

    /**
     * Get the QuestionPy bridge used to retrieve additional information about an attempt.
     *
     * @return question_bridge_base|null
     * @throws moodle_exception
     */
    public function get_bridge(): ?question_bridge_base {
        if ($this->bridge === null) {
            $attempt = $this->get_behaviour()->get_qa();
            if ($attempt->get_database_id() !== null && is_numeric($attempt->get_usage_id())) {
                $this->bridge = question_bridge_base::create($attempt);
            }
        }
        return $this->bridge;
    }

    /**
     * Explicitly set the bridge to use for this question.
     *
     * The plugin that uses this question may call this method so the bridge object does not need to fetch
     * some data again from the database.
     *
     * @param question_bridge_base $bridge
     * @return void
     */
    public function set_bridge(question_bridge_base $bridge): void {
        $this->bridge = $bridge;
    }

    /**
     * Retrieves the requested attributes if any.
     *
     * @return array|null
     * @throws moodle_exception
     */
    private function get_requested_attributes(): ?array {
        $attributes = $this->questiondata->permissions?->attributes;
        return $attributes ? $this->get_bridge()?->get_attributes($attributes) : null;
    }
}
