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
        global $DB;
        $DB->delete_records('qtype_questionpy', array('questionid' => $questionid));

        parent::delete_question($questionid, $contextid);
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
     * Adds the `edit_question` JS module to the requires before rendering the question edit form.
     *
     * @param qtype_questionpy_edit_form $mform the {@see qtype_questionpy_edit_form form} to be rendered
     * @param object $question                  probably {@see qtype_questionpy_question}?
     * @param string $wizardnow
     * @return void
     */
    public function display_question_editing_page($mform, $question, $wizardnow) {
        global $PAGE;
        $PAGE->requires->js_call_amd("qtype_questionpy/edit_question", "init");
        parent::display_question_editing_page($mform, $question, $wizardnow);
    }
}
