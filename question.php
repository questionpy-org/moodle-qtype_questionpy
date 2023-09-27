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

use qtype_questionpy\api\api;
use qtype_questionpy\question_ui_renderer;

/**
 * Represents a QuestionPy question.
 *
 * @copyright  2022 Martin Gauk, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_questionpy_question extends question_graded_automatically_with_countback {

    /** @var string */
    private const QT_VAR_ATTEMPT_STATE = "_attemptstate";
    /** @var string */
    private const QT_VAR_MT_SEED = "_mt_seed";

    // Properties which do not change between attempts.
    /** @var api */
    private api $api;
    /** @var string */
    private string $packagehash;
    /** @var string */
    private string $questionstate;

    // Properties which do change between attempts (i.e. are modified by start_attempt and apply_attempt_state).
    /** @var question_ui_renderer */
    public question_ui_renderer $ui;

    /**
     * Initialize a new question. Called from {@see qtype_questionpy::make_question_instance()}.
     *
     * @param string $packagehash
     * @param string $questionstate
     */
    public function __construct(string $packagehash, string $questionstate) {
        parent::__construct();
        $this->api = new api();
        $this->packagehash = $packagehash;
        $this->questionstate = $questionstate;
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
     * @throws moodle_exception
     */
    public function start_attempt(question_attempt_step $step, $variant): void {
        $attempt = $this->api->start_attempt($this->packagehash, $this->questionstate, $variant);

        $step->set_qt_var(self::QT_VAR_ATTEMPT_STATE, $attempt->attemptstate);

        // We generate a fixed seed to be used during every render of the attempt, to keep shuffles deterministic.
        $mtseed = mt_rand();
        $step->set_qt_var(self::QT_VAR_MT_SEED, $mtseed);

        $this->ui = new question_ui_renderer($attempt->ui->content, $attempt->ui->placeholders, $mtseed);
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
        $attemptstate = $step->get_qt_var(self::QT_VAR_ATTEMPT_STATE);
        $mtseed = $step->get_qt_var(self::QT_VAR_MT_SEED);
        if (is_null($attemptstate) || is_null($mtseed)) {
            // Start_attempt probably was never called, which it should have been.
            throw new coding_exception("apply_attempt_state was called, but the attempt is missing a qt var");
        }

        $attempt = $this->api->view_attempt($this->packagehash, $this->questionstate, $attemptstate);
        $this->ui = new question_ui_renderer($attempt->ui->content, $attempt->ui->placeholders, $mtseed);
    }

    /**
     * What data may be included in the form submission when a student submits
     * this question in its current state?
     *
     * This information is used in calls to optional_param. The parameter name
     * has {@see question_attempt::get_field_prefix()} automatically prepended.
     *
     * @return array variable name => PARAM_... constant, or, as a special case
     *      that should only be used in unavoidable, the constant question_attempt::USE_RAW_DATA
     *      meaning take all the raw submitted data belonging to this question.
     */
    public function get_expected_data(): array {
        return $this->ui->get_metadata()->expecteddata;
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
        return $this->ui->get_metadata()->correctresponse;
    }

    /**
     * Used by many of the behaviours, to work out whether the student's
     * response to the question is complete. That is, whether the question attempt
     * should move to the COMPLETE or INCOMPLETE state.
     *
     * @param array $response responses, as returned by
     *                        {@see question_attempt_step::get_qt_data()}.
     * @return bool whether this response is a complete answer to this question.
     */
    public function is_complete_response(array $response): bool {
        foreach ($this->ui->get_metadata()->requiredfields as $requiredfield) {
            if (!isset($response[$requiredfield]) || $response[$requiredfield] === "") {
                return false;
            }
        }
        return true;
    }

    /**
     * Use by many of the behaviours to determine whether the student's
     * response has changed. This is normally used to determine that a new set
     * of responses can safely be discarded.
     *
     * @param array $prevresponse the responses previously recorded for this question,
     *                            as returned by {@see question_attempt_step::get_qt_data()}
     * @param array $newresponse the new responses, in the same format.
     * @return bool whether the two sets of responses are the same - that is
     *                            whether the new set of responses can safely be discarded.
     */
    public function is_same_response(array $prevresponse, array $newresponse) {
        return false;
    }

    /**
     * Produce a plain text summary of a response.
     *
     * @param array $response a response, as might be passed to {@see grade_response()}.
     * @return string a plain text summary of that response, that could be used in reports.
     */
    public function summarise_response(array $response) {
        return '';
    }

    /**
     * In situations where is_gradable_response() returns false, this method
     * should generate a description of what the problem is.
     *
     * @param array $response responses
     * @return string the message.
     */
    public function get_validation_error(array $response) {
        return '';
    }

    /**
     * Grade a response to the question, returning a fraction between
     * get_min_fraction() and get_max_fraction(), and the corresponding {@see question_state}
     * right, partial or wrong.
     *
     * @param array $response responses, as returned by
     *                        {@see question_attempt_step::get_qt_data()}.
     * @return array (float, integer) the fraction, and the state.
     */
    public function grade_response(array $response) {
        return [0, question_state::$gradedwrong];
    }

    /**
     * Work out a final grade for this attempt, taking into account all the
     * tries the student made.
     *
     * @param array $responses the response for each try. Each element of this
     *                         array is a response array, as would be passed to {@see grade_response()}.
     *                         There may be between 1 and $totaltries responses.
     * @param int $totaltries  The maximum number of tries allowed.
     * @return numeric the fraction that should be awarded for this
     *                         sequence of response.
     */
    public function compute_final_grade($responses, $totaltries) {
        return 0;
    }
}
