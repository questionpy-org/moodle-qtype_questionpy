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

use qtype_questionpy\api\attempt_ui;
use qtype_questionpy\api\feedback_type;
use qtype_questionpy\api\js_module_call;
use qtype_questionpy\constants;
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
        return '';
    }

    /**
     * Renders a message informing the user about an error.
     *
     * @return string
     * @throws \core\exception\moodle_exception
     */
    private function render_error(): string {
        return $this->output->render_from_template('qtype_questionpy/render_error', [
            'message' => 'There was an error attempting to view the question.',
            'info' => 'Please contact an administrator.',
        ]);
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

        if ($question->errorduringload) {
            // This should already have been logged in qtype_questionpy_question.
            return $this->render_error();
        }

        try {
            $questiondivid = $qa->get_outer_question_div_unique_id();
            $autosavehintid = $questiondivid . '-autosave';
            $formulationcb = function (qtype_questionpy_renderer $renderer) use ($qa, $question, $options, $autosavehintid) {
                return $renderer->formulation_controls_feedback_in_iframe($qa, $question->ui, $options, $autosavehintid);
            };
            $iframesrc = $this->get_iframe_document($options->context, $question, $formulationcb);

            // A hidden input field is used to tell the quiz autosaver that the user changed their question answer
            // in the iframe. The value is increased by one every time. The autosaver detects this modification and will
            // save all answers.
            $iframeid = $questiondivid . '-iframe';
            $autosavehintname = 'qpy-autosave-' . $questiondivid;
            $this->page->requires->js_call_amd(
                'qtype_questionpy/view_question',
                'addIframeFormDataOnSubmit',
                [$iframeid, $qa->get_field_prefix() . constants::QT_VAR_RESPONSE]
            );

            return <<<EOA
    <input type="hidden" name="{$autosavehintname}" id="{$autosavehintid}" value="0">
    <iframe id="{$iframeid}" srcdoc="{$iframesrc}"></iframe>
EOA;
        } catch (Throwable $t) {
            global $USER;
            // Trigger error event.
            $params = [
                'context' => $this->page->context,
                'relateduserid' => $USER->id,
                'other' => [
                    'questionid' => $question->id,
                    'questionattemptid' => $qa->get_database_id(),
                    'errormessage' => $t->getMessage(),
                ],
            ];
            $event = \qtype_questionpy\event\viewing_attempt_failed::create($params);
            $event->trigger();
            debugging($event->get_description(), backtrace: $t->getTrace());

            return $this->render_error();
        }
    }

    /**
     * Generate the HTML document that is put into an iframe.
     *
     * A new moodle_page object is created (and temporarily swapped with $PAGE) to get a usual Moodle page with the
     * embedded layout. $contentcb is called to get the actual content. A callback is used here because it
     * needs to be called on a new renderer instance that is connected with the temporary moodle_page.
     *
     * @param context $context
     * @param qtype_questionpy_question $question
     * @param callable $contentcb Callback to get the main content that should be part of the iframe.
     * @return string HTML document already encoded with htmlspecialchars to put in iframe srcdoc
     * @throws \core\exception\coding_exception
     */
    protected function get_iframe_document(context $context, qtype_questionpy_question $question, callable $contentcb): string {
        // We know what we are doing here. We are touching these globals on purpose.
        // phpcs:disable moodle.PHP.ForbiddenGlobalUse.BadGlobal
        global $PAGE, $OUTPUT;
        $oldpage = $PAGE;
        $oldoutput = $OUTPUT;

        // Initialize output buffer.
        // We do this to ensure that any echo, var_dump, etc. statements are included in the iframe contents.
        ob_start();
        try {
            $classname = get_class($oldpage); // The class of $PAGE may be customized using $CFG->moodlepageclass.
            /** @var moodle_page $PAGE */
            $PAGE = new $classname();
            /** @var \core\output\core_renderer $OUTPUT */
            $OUTPUT = new bootstrap_renderer(); // Class bootstrap_renderer will initialize $OUTPUT on first use.

            $PAGE->set_context($context);
            $PAGE->set_pagelayout('embedded');
            $PAGE->set_pagetype($oldpage->pagetype);
            $PAGE->set_url($oldpage->url);
            $PAGE->add_body_class('questionpy-iframe-body');

            // Get a new instance of this renderer for the new $PAGE object to render the iframe contents.
            // This is necessary so that any JS/CSS requirements get added to the new page's page_requirements_manager.
            /** @var self $qpyrenderer */
            $qpyrenderer = $PAGE->get_renderer('qtype_questionpy');

            // Render iframe contents before the header is printed to allow CSS to be added to the page header.
            $iframecontents = $contentcb($qpyrenderer);

            // Write iframe source into the output buffer.
            echo $OUTPUT->header();
            echo $this->get_iframe_js_before();
            echo $this->get_iframe_js_importmap($question);
            echo $iframecontents;
            echo $OUTPUT->footer();
        } finally {
            $PAGE = $oldpage;
            $OUTPUT = $oldoutput;
        }
        // phpcs:enable

        $iframesrc = ob_get_clean();
        return htmlspecialchars($iframesrc);
    }

    /**
     * Gather the formulation/controls and feedbacks of a question attempt.
     *
     * Prepares everything in order to display the question in an iframe.
     *
     * @param question_attempt $qa the question attempt to display.
     * @param attempt_ui $ui
     * @param question_display_options $options controls what should and should not be displayed.
     * @param string $autosavehintinputid
     * @return string HTML fragment.
     * @throws moodle_exception
     */
    protected function formulation_controls_feedback_in_iframe(question_attempt $qa, attempt_ui $ui,
              question_display_options $options, string $autosavehintinputid): string {
        $qformulation = new question_ui_renderer($ui->formulation, $ui->placeholders, $options, $qa);
        $feedback = html_writer::nonempty_tag(
            'div',
            $this->feedback_in_iframe($qa, $options),
            ['class' => 'outcome clearfix']
        );

        $roles = $qformulation->get_user_roles();
        $this->page->requires->js_call_amd(
            'qtype_questionpy/view_question',
            'init',
            [$autosavehintinputid, $roles]
        );
        $this->add_package_js_calls($ui->javascriptcalls, $roles, $options);

        return $this->render_from_template('qtype_questionpy/iframe_question_content', [
            'question' => $qformulation->render(),
            'feedback' => $feedback,
        ]);
    }

    /**
     * Filter the JavaScript calls requested by the package and call the functions.
     *
     * @param js_module_call[] $jscalls
     * @param string[] $roles names of qpy user roles
     * @param question_display_options $options
     * @return void
     * @throws coding_exception
     */
    protected function add_package_js_calls(array $jscalls, array $roles, question_display_options $options): void {
        $calls = [];
        foreach ($jscalls as $call) {
            // If there are role/feedback conditions, both have to be matched.
            if ($call->ifrole && !in_array(strtolower($call->ifrole), $roles)) {
                continue;
            }
            if (
                $call->iffeedbacktype && !(
                    ($call->iffeedbacktype == feedback_type::general_feedback->value && $options->generalfeedback)
                    || ($call->iffeedbacktype == feedback_type::specific_feedback->value && $options->feedback)
                    || ($call->iffeedbacktype == feedback_type::right_answer->value && $options->rightanswer)
                )
            ) {
                continue;
            }

            if ($call->data !== null) {
                try {
                    // Decode and encode the data again to be sure that it is a properly escaped JSON (no XSS in script tag).
                    $decodedjson = json_decode(
                        $call->data,
                        associative: false,
                        depth: 64,
                        flags: JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE
                    );
                    $jsondata = json_encode($decodedjson);
                } catch (JsonException | ValueError $e) {
                    debugging(
                        'qtype_questionpy: Error decoding JSON data from package: ' . $e->getMessage(),
                        DEBUG_DEVELOPER,
                        backtrace: $e->getTrace()
                    );
                    $calls[] = "window.console.error('There was an error (on the server side) decoding the JSON data from " .
                        "the package ({$call->module} -> {$call->function} will not be called).');";
                    continue;
                }
            } else {
                $jsondata = 'undefined';
            }

            $calls[] = <<<EOF
    M.util.js_pending("qtype_questionpy/package/{$call->module}");
    import("{$call->module}").then(module => {
        const data = {$jsondata};
        module["{$call->function}"](attempt, data);
        M.util.js_complete("qtype_questionpy/package/{$call->module}");
    });

EOF;
        }

        if ($calls) {
            $imploded = implode("\n", $calls);
            $inlinejs = <<<EOD
M.util.js_pending('qtype_questionpy/view_question');
require(['qtype_questionpy/view_question'], (view) => {
    const attempt = view.getAttempt()
{$imploded}
    M.util.js_complete('qtype_questionpy/view_question');
});
EOD;

            // We are not using js_call_amd here, because (a) it is not possible to call the ES6 import function from
            // amd/src (it is transpiled to use RequireJS) and (b) js_call_amd has a quite low character limit for the params.
            $this->page->requires->js_amd_inline($inlinejs);
        }
    }

    /**
     * JavaScript within the iframe that is executed before the formulation part.
     *
     * The iframe should quickly resize its height to its scroll height to make it a seamless part of the page.
     *
     * @return string
     */
    protected function get_iframe_js_before(): string {
        return <<<'END'
<script>
    "use strict";
    (function () {
        // Add <base target='_blank'> tag to head so links are opened in a new tab.
        const base = document.createElement('base');
        base.target = '_blank';
        document.getElementsByTagName('head')[0].appendChild(base);

        // Resize iframe when content height changes.
        const resize = function() {
            if (window.frameElement) {
                window.frameElement.style.height = document.body.scrollHeight + 'px';
                window.frameElement.style.width = '100%';
            }
        };
        resize();
        const resizeObserver = new ResizeObserver(resize);
        resizeObserver.observe(document.body);
    })();
</script>
END;
    }

    /**
     * Generate an importmap script element
     *
     * @param qtype_questionpy_question $question
     * @return string
     */
    protected function get_iframe_js_importmap(qtype_questionpy_question $question): string {
        $importmap = [
            'imports' => [],
        ];

        foreach ($question->packagedependencies as $dependency) {
            $path = "@{$dependency->namespace}/{$dependency->shortname}/";
            $mapsto = \moodle_url::make_pluginfile_url(
                $question->contextid,
                'qtype_questionpy',
                'static',
                null,
                "/{$question->packagehash}/{$dependency->namespace}/{$dependency->shortname}/js/",
                ''
            );
            $importmap['imports'][$path] = $mapsto->out();
        }

        $importmapjson = json_encode($importmap, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return <<<"EOD"
<script type="importmap">
$importmapjson
</script>
EOD;
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

        $output = '';
        $hint = null;

        if ($options->feedback && !is_null($question->ui->specificfeedback)) {
            $renderer = new question_ui_renderer($question->ui->specificfeedback, $question->ui->placeholders, $options, $qa);
            $output .= html_writer::nonempty_tag(
                'div',
                $renderer->render(),
                ['class' => 'specificfeedback', 'id' => 'qpy-specific-feedback']
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
                ['class' => 'generalfeedback', 'id' => 'qpy-general-feedback']
            );
        }

        if ($options->rightanswer && !is_null($question->ui->rightanswer)) {
            $renderer = new question_ui_renderer($question->ui->rightanswer, $question->ui->placeholders, $options, $qa);
            $output .= html_writer::nonempty_tag(
                'div',
                $renderer->render(),
                ['class' => 'rightanswer', 'id' => 'qpy-right-answer']
            );
        }

        if ($output) {
            // Copied from \core_question_renderer::question.
            $output = html_writer::tag(
                'h4',
                get_string('feedback', 'question'),
                ['class' => 'accesshide']
            ) . $output;
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
        // We display all feedbacks in the iframe together with the formulation.
        return '';
    }
}
