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
 * Question type class for the QuestionPy question type.
 *
 * @package    qtype_questionpy
 * @copyright  2022 Martin Gauk, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use qtype_questionpy\api\api;
use qtype_questionpy\package_service;
use qtype_questionpy\question_service;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/type/questionpy/question.php');

/**
 * The QuestionPy question type class.
 *
 * @copyright  2022 Martin Gauk, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_questionpy extends question_type {
    /** @var api */
    private api $api;

    /** @var package_service */
    private package_service $packageservice;

    /** @var question_service */
    private question_service $questionservice;

    /**
     * Initializes the instance. Called by Moodle.
     */
    public function __construct() {
        parent::__construct();
        $this->api = new api();
        $this->packageservice = new package_service($this->api);
        $this->questionservice = new question_service($this->api, $this->packageservice);
    }

    /**
     * Description
     *
     * @return bool true if this question type sometimes requires manual grading.
     */
    public function is_manual_graded() {
        return true;
    }

    /**
     * Whether a quesiton instance has to be graded manually.
     *
     * @param object $question            a question of this type.
     * @param string $otherquestionsinuse comma-separate list of other question ids in this attempt.
     * @return bool true if a particular instance of this question requires manual grading.
     */
    public function is_question_manual_graded($question, $otherquestionsinuse) {
        // TODO: could also return false, if $question can be automatically graded.
        return $this->is_manual_graded();
    }

    /**
     * Deletes the question-type specific data when a question is deleted.
     *
     * @param int $questionid the question being deleted.
     * @param int $contextid  the context this question belongs to.
     */
    public function delete_question($questionid, $contextid) {
        question_service::delete_question($questionid);
        parent::delete_question($questionid, $contextid);
    }

    /**
     * Move all the files belonging to this question from one context to another.
     *
     * @param $questionid
     * @param $oldcontextid
     * @param $newcontextid
     * @return void
     */
    public function move_files($questionid, $oldcontextid, $newcontextid) {
        parent::move_files($questionid, $oldcontextid, $newcontextid);
        $this->move_files_in_answers($questionid, $oldcontextid, $newcontextid);
        $this->move_files_in_hints($questionid, $oldcontextid, $newcontextid);

        $fs = get_file_storage();
        $fs->move_area_files_to_new_context($oldcontextid, $newcontextid, 'qtype_questionpy', 'package', $questionid);
    }

    /**
     * Delete all the files belonging to this question.
     *
     * @param $questionid
     * @param $contextid
     * @return void
     */
    protected function delete_files($questionid, $contextid) {
        parent::delete_files($questionid, $contextid);
        $this->delete_files_in_answers($questionid, $contextid);
        $this->delete_files_in_hints($questionid, $contextid);

        $fs = get_file_storage();
        $fs->delete_area_files($contextid, 'qtype_questionpy', 'package', $questionid);
    }

    /**
     * Calculate the score a monkey would get on a question by clicking randomly.
     *
     * @param stdClass $questiondata data defining a question, as returned by
     *                               question_bank::load_question_data().
     * @return number|null either a fraction estimating what the student would
     *                               score by guessing, or null, if it is not possible to estimate.
     */
    public function get_random_guess_score($questiondata) {
        // TODO: computing this has to be delegated to the question developer. This has to be requested at the application server.
        return 0;
    }

    /**
     * Saves QuestionPy-specific options.
     *
     * @param object $question this holds the information from the editing form, it is not a standard question object
     * @return void
     * @throws moodle_exception
     */
    public function save_question_options($question) {
        $this->questionservice->upsert_question($question);
    }

    /**
     * Loads QuestionPy-specific options for the question.
     *
     * @param object $question question object to which QuestionPy-specific options should be added
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function get_question_options($question): bool {
        if (!parent::get_question_options($question)) {
            return false;
        }

        foreach ($this->questionservice->get_question($question->id) as $key => $value) {
            $question->{$key} = $value;
        }

        return true;
    }

    /**
     * Create an appropriate question_definition for the question of this type
     * using data loaded from the database.
     *
     * @param object $questiondata the question data loaded from the database.
     * @return question_definition an instance of the appropriate question_definition subclass.
     *      Still needs to be initialised.
     * @throws moodle_exception
     */
    protected function make_question_instance($questiondata) {
        $packagefile = null;
        if ($questiondata->qpy_is_local) {
            $packagefile = $this->packageservice->get_file($questiondata->qpy_id, $questiondata->contextid);
        }
        return new qtype_questionpy_question($questiondata->qpy_package_hash, $questiondata->qpy_state, $packagefile);
    }
}
