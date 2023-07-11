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
 * QuestionPy renderer class
 *
 * @package    qtype_questionpy
 * @copyright  2022 Martin Gauk, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Generates the output for QuestionPy questions.
 *
 * @copyright  2022 Martin Gauk, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_questionpy_renderer extends qtype_renderer {

    /**
     * Generate the display of the formulation part of the question. This is the
     * area that contains the quetsion text, and the controls for students to
     * input their answers. Some question types also embed bits of feedback, for
     * example ticks and crosses, in this area.
     *
     * @param question_attempt $qa              the question attempt to display.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return string HTML fragment.
     * @throws coding_exception
     */
    public function formulation_and_controls(question_attempt $qa, question_display_options $options): string {
        $question = $qa->get_question(false);
        assert($question instanceof qtype_questionpy_question);
        return $question->get_question_ui()->render_formulation($qa, $options);
    }

    /**
     * Generate the general feedback. This is feedback shown to all students.
     *
     * @param question_attempt $qa the question attempt to display.
     * @return string HTML fragment.
     */
    protected function general_feedback(question_attempt $qa): string {
        $question = $qa->get_question(false);
        assert($question instanceof qtype_questionpy_question);
        return $question->get_question_ui()->render_general_feedback($qa) ?? "";
    }

    /**
     * Generate the specific feedback. This is feedback that varies according to
     * the response the student gave.
     *
     * @param question_attempt $qa the question attempt to display.
     * @return string HTML fragment.
     */
    public function specific_feedback(question_attempt $qa): string {
        $question = $qa->get_question(false);
        assert($question instanceof qtype_questionpy_question);
        return $question->get_question_ui()->render_specific_feedback($qa) ?? "";
    }

    /**
     * Create an automatic description of the correct response to this question.
     * Not all question types can do this. If it is not possible, this method
     * should just return an empty string.
     *
     * @param question_attempt $qa the question attempt to display.
     * @return string HTML fragment.
     */
    public function correct_response(question_attempt $qa): string {
        $question = $qa->get_question(false);
        assert($question instanceof qtype_questionpy_question);
        return $question->get_question_ui()->render_right_answer($qa) ?? "";
    }

    /**
     * Create a link for the upload page with given arguments
     *
     * @param context $context
     * @return mixed html link with attached action
     */
    public function package_upload_link(context $context) {
        $params['courseid'] = $context->instanceid;
        $link = new moodle_url('/question/type/questionpy/upload_view.php', $params);
        $action = new \popup_action('click', $link);

        return $this->action_link($link, 'qpy_package_upload', $action, null);
    }
}
