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

use qtype_questionpy\question_ui_renderer;

/**
 * Generates the output for QuestionPy questions.
 *
 * @copyright  2022 Martin Gauk, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_questionpy_renderer extends qtype_renderer {
    /**
     * Return any HTML that needs to be included in the page's <head> when this
     * question is used.
     * @param question_attempt $qa the question attempt that will be displayed on the page.
     * @return string HTML fragment.
     */
    public function head_code(question_attempt $qa) {
        $this->page->requires->js_call_amd("qtype_questionpy/view_question", "init");
        return parent::head_code($qa);
    }

    /**
     * Generate the display of the formulation part of the question. This is the
     * area that contains the question text, and the controls for students to
     * input their answers. Some question types also embed bits of feedback, for
     * example ticks and crosses, in this area.
     *
     * @param question_attempt $qa the question attempt to display.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return string HTML fragment.
     * @throws moodle_exception
     */
    public function formulation_and_controls(question_attempt $qa, question_display_options $options): string {
        $question = $qa->get_question();
        assert($question instanceof qtype_questionpy_question);

        if (!isset($question->ui)) {
            return $this->output->render_from_template('qtype_questionpy/render_error', [
                'message' => 'There was an error attempting to view the question.',
                'info' => 'Please contact an administrator.',
            ]);
        }

        global $PAGE, $OUTPUT;
        $oldpage = $PAGE;
        $oldoutput = $OUTPUT; // Class moodle_page also modifies global $OUTPUT variable.

        try {
            $PAGE = new moodle_page();
            $PAGE->set_context($options->context);
            $PAGE->set_pagelayout('embedded');
            $PAGE->set_pagetype($oldpage->pagetype);
            $PAGE->set_url($oldpage->url);

            $qformulation = new question_ui_renderer($question->ui->formulation, $question->ui->placeholders, $options,
                $qa);
            $feedback = html_writer::nonempty_tag('div', $this->feedback_in_iframe($qa, $options),
                ['class' => 'outcome clearfix']);

            $editor = editors_get_preferred_editor(FORMAT_HTML); // Only for testing purposes!
            $editor->use_editor('mytextareaid', [
                'context' => $options->context,
                'enable_filemanagement' => true,
                'maxfiles' => EDITOR_UNLIMITED_FILES,
            ], question_utils::get_filepicker_options($options->context, 0));

            $questionsrc = $this->output->render_from_template('qtype_questionpy/iframe_content', [
                'question' => $qformulation->render(),
                'feedback' => $feedback,
                'filepicker' => $this->get_file_picker_test($options->context),
            ]);

            $outputrenderer = $PAGE->get_renderer('core', null);
            $iframesrc = $outputrenderer->header();
            $iframesrc .= $questionsrc;
            $iframesrc .= $outputrenderer->footer();
            return '<iframe srcdoc="' . htmlspecialchars($iframesrc) . '"></iframe>';

        } finally {
            $PAGE = $oldpage;
            $OUTPUT = $oldoutput;
        }

        return '';
    }

    protected function get_file_picker_test($context) {
        global $CFG, $PAGE;
        require_once($CFG->dirroot . '/lib/form/filemanager.php');

        $pickeroptions = new stdClass();
        $pickeroptions->mainfile = null;
        $pickeroptions->maxfiles = 10;
        $pickeroptions->itemid = 1;
        $pickeroptions->context = $context;
        $pickeroptions->return_types = FILE_INTERNAL | FILE_CONTROLLED_LINK;
        $pickeroptions->accepted_types = '*';

        $fm = new form_filemanager($pickeroptions);
        $fm->options->maxbytes = 10*1024*1024;
        $filesrenderer = $PAGE->get_renderer('core', 'files');
        return $filesrenderer->render($fm);
    }

    /**
     * Generate the display of the outcome part of the question.
     *
     * We reimplement this method instead of overriding the more specific methods {@see specific_feedback()},
     * {@see general_feedback()} and {@see correct_response()} because those aren't passed the
     * {@see question_display_options}.
     *
     * @param question_attempt $qa the question attempt to display.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return string HTML fragment.
     * @throws coding_exception
     */
    protected function feedback_in_iframe(question_attempt $qa, question_display_options $options): string {
        $question = $qa->get_question();
        assert($question instanceof qtype_questionpy_question);

        if ($options->feedback && !isset($question->ui)) {
            // We do not want to show another error message in the feedback section as there is already one in the formulations.
            return '';
        }

        $output = '';
        $hint = null;

        if ($options->feedback && !is_null($question->ui->specificfeedback)) {
            $renderer = new question_ui_renderer($question->ui->specificfeedback, $question->ui->placeholders, $options, $qa);
            $output .= html_writer::nonempty_tag(
                'div',
                $renderer->render(),
                ['class' => 'specificfeedback']
            );
            $hint = $qa->get_applicable_hint();
        }

        if ($options->numpartscorrect) {
            $output .= html_writer::nonempty_tag('div', $this->num_parts_correct($qa), ['class' => 'numpartscorrect']);
        }

        if ($hint) {
            $output .= $this->hint($qa, $hint);
        }

        if ($options->generalfeedback && !is_null($question->ui->generalfeedback)) {
            $renderer = new question_ui_renderer($question->ui->generalfeedback, $question->ui->placeholders, $options, $qa);
            $output .= html_writer::nonempty_tag(
                'div',
                $renderer->render(),
                ['class' => 'generalfeedback']
            );
        }

        if ($options->rightanswer && !is_null($question->ui->rightanswer)) {
            $renderer = new question_ui_renderer($question->ui->rightanswer, $question->ui->placeholders, $options, $qa);
            $output .= html_writer::nonempty_tag(
                'div',
                $renderer->render(),
                ['class' => 'rightanswer']
            );
        }

        if ($output) {
            // Copied from \core_question_renderer::question.
            $output = html_writer::tag('h4', get_string('feedback', 'question'),
                    ['class' => 'accesshide']) . $output;
        }

        return $output;
    }

    /**
     * Generate the display of the outcome part of the question. This is the
     * area that contains the various forms of feedback. This function generates
     * the content of this area belonging to the question type.
     *
     * Subclasses will normally want to override the more specific methods
     * {specific_feedback()}, {general_feedback()} and {correct_response()}
     * that this method calls.
     *
     * @param question_attempt $qa the question attempt to display.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return string HTML fragment.
     */
    public function feedback(question_attempt $qa, question_display_options $options) {
        // We display all feedbacks in the iframe.
        return '';
    }
}
