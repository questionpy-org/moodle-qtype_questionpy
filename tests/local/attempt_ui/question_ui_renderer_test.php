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

namespace qtype_questionpy\local\attempt_ui;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/questionpy/question.php');

use coding_exception;
use PHPUnit\Framework\MockObject\Stub;
use qtype_questionpy\constants;
use qtype_questionpy\local\api\api;
use qtype_questionpy_question;
use question_attempt;
use question_attempt_step;
use testable_question_attempt;

/**
 * Unit tests for {@see question_ui_renderer}.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2023 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class question_ui_renderer_test extends \advanced_testcase {
    /**
     * Asserts that two html strings are equal.
     *
     * @param string $expectedhtml
     * @param string $actualhtml
     * @return void
     */
    private function assert_html_string_equals_html_string(string $expectedhtml, string $actualhtml): void {
        // Remove whitespace as `preserveWhiteSpace = false` does not seem to work as expected.
        $expectedhtml = preg_replace('/>\s+</', '><', $expectedhtml);
        $actualhtml = preg_replace('/>\s+</', '><', $actualhtml);

        // We need these flags to parse HTML5 and prevent the html-tag, body-tag and doctype to be added.
        $flags = LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR;

        $expected = new \DOMDocument();
        $expected->loadHTML($expectedhtml, $flags);

        $actual = new \DOMDocument();
        $actual->loadHTML($actualhtml, $flags);

        $this->assertEquals($expected, $actual);
    }

    /**
     * Tests that inline feedback is hidden when the {@see \question_display_options} say so.
     *
     * @throws coding_exception
     * @covers \qtype_questionpy\local\attempt_ui\question_ui_renderer
     */
    public function test_should_hide_inline_feedback(): void {
        $input = file_get_contents(__DIR__ . '/question_uis/feedbacks.xhtml');

        $qa = $this->create_question_attempt_stub();
        $opts = new \question_display_options();
        $opts->hide_all_feedback();

        $result = question_ui_renderer::render($input, [], $opts, $qa);

        $this->assert_html_string_equals_html_string(<<<EXPECTED
        <div xmlns="http://www.w3.org/1999/xhtml">
            <span>No feedback</span>
        </div>
        EXPECTED, $result->html);
    }

    /**
     * Tests that inline feedback is shown when the {@see \question_display_options} say so.
     *
     * @throws coding_exception
     * @covers \qtype_questionpy\local\attempt_ui\question_ui_renderer
     */
    public function test_should_show_inline_feedback(): void {
        $input = file_get_contents(__DIR__ . '/question_uis/feedbacks.xhtml');

        $qa = $this->create_question_attempt_stub();
        $opts = new \question_display_options();

        $result = question_ui_renderer::render($input, [], $opts, $qa);

        $this->assert_html_string_equals_html_string(<<<EXPECTED
        <div xmlns="http://www.w3.org/1999/xhtml">
            <span>No feedback</span>
            <span>General feedback</span>
            <span>Specific feedback</span>
        </div>
        EXPECTED, $result->html);
    }

    /**
     * Tests that `qpy:shuffle-elements` works and especially correctly handles (nested) `qpy:shuffled-index` elements.
     *
     * @throws coding_exception
     * @covers \qtype_questionpy\question_ui_renderer::shuffle_contents
     * @covers \qtype_questionpy\question_ui_renderer::replace_shuffled_indices
     */
    public function test_should_shuffle_correctly_and_replace_indices(): void {
        $input = file_get_contents(__DIR__ . '/question_uis/shuffle.xhtml');

        // Fixed ID, because it's used as the shuffle seed.
        $qa = $this->create_question_attempt_stub(id: 42);

        $result = question_ui_renderer::render($input, [], new \question_display_options(), $qa);

        $this->assert_html_string_equals_html_string(<<<EXPECTED
        <div xmlns="http://www.w3.org/1999/xhtml">
            <span>Element 1, shuffled to 1</span>
            <span>Element 3, shuffled to b</span>
            <div>
                <span>Nested element 1, shuffled to 1</span>
                <span>Nested element 2, shuffled to 2</span>
            </div>
            <span>Element 2, shuffled to 4</span>
            <span>Element 4, shuffled to V</span>
            <div>
                Element 5, shuffled to 6
                <div>
                    <span>Nested element 2, shuffled to 1</span>
                    <span>Nested element 1, shuffled to 2</span>
                </div>
            </div>
        </div>
        EXPECTED, $result->html);
    }

    /**
     * Tests that `qpy:shuffle-elements` sticks to the same shuffled order as long as the seed (attempt id) is the same.
     *
     * @throws coding_exception
     * @covers \qtype_questionpy\local\attempt_ui\question_ui_renderer::shuffle_contents
     */
    public function test_should_shuffle_the_same_way_in_same_attempt(): void {
        $input = file_get_contents(__DIR__ . '/question_uis/shuffle.xhtml');
        $qa = $this->create_question_attempt_stub();

        $firstresult = question_ui_renderer::render($input, [], new \question_display_options(), $qa);
        for ($i = 0; $i < 10; $i++) {
            $result = question_ui_renderer::render($input, [], new \question_display_options(), $qa);
            $this->assertEquals($firstresult->html, $result->html);
        }
    }

    /**
     * Tests that placeholders are replaced.
     *
     * @return void
     * @throws coding_exception
     * @covers \qtype_questionpy\local\attempt_ui\question_ui_renderer
     */
    public function test_should_resolve_placeholders(): void {
        $input = file_get_contents(__DIR__ . '/question_uis/placeholder.xhtml');
        $qa = $this->create_question_attempt_stub();

        $result = question_ui_renderer::render($input, [
            'param' => "Value of param <b>one</b>.<script>'Oh no, danger!'</script>",
            'description' => 'My simple description.',
        ], new \question_display_options(), $qa);

        $this->assert_html_string_equals_html_string(<<<EXPECTED
        <div xmlns="http://www.w3.org/1999/xhtml">
            <div>My simple description.</div>
            <span>By default cleaned parameter: Value of param <b>one</b>.</span>
            <span>Explicitly cleaned parameter: Value of param <b>one</b>.</span>
            <span>Noclean parameter: Value of param <b>one</b>.<script>'Oh no, danger!'</script></span>
            <span>Plain parameter: Value of param &lt;b>one&lt;/b>.&lt;script>'Oh no, danger!'&lt;/script>
            </span>
        </div>
        EXPECTED, $result->html);
    }

    /**
     * Tests that broken HTML in placeholder values is handled correctly depending on clean mode.
     *
     * @return void
     * @throws coding_exception
     * @covers \qtype_questionpy\local\attempt_ui\question_ui_renderer::resolve_placeholders
     */
    public function test_should_correctly_handle_broken_html_in_placeholder_expansion(): void {
        $input = file_get_contents(__DIR__ . '/question_uis/placeholder.xhtml');
        $qa = $this->create_question_attempt_stub();

        $result = question_ui_renderer::render($input, [
            'param' => '<qpy:format-float>123</qpy:format-float><unknown-tag></unknown-tag><div>unclosed',
            'description' => 'My simple description.',
        ], new \question_display_options(), $qa);

        // For noclean, the qpy namespace prefix is unknown when appending the XML. Since we want to support as much
        // broken HTML/XML in user input as possible, the DOM just removes the prefix.

        // phpcs:disable moodle.Files.LineLength.TooLong
        $this->assert_html_string_equals_html_string(<<<EXPECTED
        <div xmlns="http://www.w3.org/1999/xhtml">
            <div>My simple description.</div>
            <span>By default cleaned parameter: 123<div>unclosed</div></span>
            <span>Explicitly cleaned parameter: 123<div>unclosed</div></span>
            <span>Noclean parameter: <format-float>123</format-float><unknown-tag></unknown-tag><div>unclosed</div></span>
            <span>Plain parameter: &lt;qpy:format-float>123&lt;/qpy:format-float>&lt;unknown-tag>&lt;/unknown-tag>&lt;div>unclosed</span>
        </div>
        EXPECTED, $result->html);
        // phpcs:enable moodle.Files.LineLength.TooLong
    }

    /**
     * Tests that placeholders are just removed when the corresponding value is missing.
     *
     * @return void
     * @throws coding_exception
     * @covers \qtype_questionpy\local\attempt_ui\question_ui_renderer
     */
    public function test_should_remove_placeholders_when_no_corresponding_value(): void {
        $input = file_get_contents(__DIR__ . '/question_uis/placeholder.xhtml');
        $qa = $this->create_question_attempt_stub();

        $result = question_ui_renderer::render($input, [], new \question_display_options(), $qa);

        $this->assert_html_string_equals_html_string(<<<EXPECTED
        <div xmlns="http://www.w3.org/1999/xhtml">
            <div></div>
            <span>By default cleaned parameter: </span>
            <span>Explicitly cleaned parameter: </span>
            <span>Noclean parameter: </span>
            <span>Plain parameter: </span>
        </div>
        EXPECTED, $result->html);
    }

    /**
     * Tests that submit and reset buttons (which would also affect other questions) are turned into simple ones.
     *
     * @throws coding_exception
     * @covers \qtype_questionpy\local\attempt_ui\question_ui_renderer
     */
    public function test_should_defuse_buttons(): void {
        $input = file_get_contents(__DIR__ . '/question_uis/buttons.xhtml');
        $qa = $this->create_question_attempt_stub();

        $result = question_ui_renderer::render($input, [], new \question_display_options(), $qa);

        $this->assert_html_string_equals_html_string(<<<EXPECTED
        <div xmlns="http://www.w3.org/1999/xhtml">
            <button class="btn btn-primary qpy-input" type="button">Submit</button>
            <button class="btn btn-primary qpy-input" type="button">Reset</button>
            <button class="btn btn-primary qpy-input" type="button">Button</button>

            <input class="btn btn-primary qpy-input" type="button" value="Submit"/>
            <input class="btn btn-primary qpy-input" type="button" value="Reset"/>
            <input class="btn btn-primary qpy-input" type="button" value="Button"/>
        </div>
        EXPECTED, $result->html);
    }

    /**
     * Tests that elements with `qpy:if-role` attributes are removed when the user has none of the given roles.
     *
     * @throws coding_exception
     * @covers \qtype_questionpy\local\attempt_ui\question_ui_renderer
     */
    public function test_should_remove_element_with_if_role_attribute(): void {
        $input = file_get_contents(__DIR__ . '/question_uis/if-role.xhtml');
        $qa = $this->create_question_attempt_stub();

        $this->resetAfterTest();
        $this->setGuestUser();

        $course = $this->getDataGenerator()->create_course();
        $options = new \question_display_options();
        $options->context = \context_course::instance($course->id);

        $result = question_ui_renderer::render($input, [], $options, $qa);

        $this->assert_html_string_equals_html_string(<<<EXPECTED
        <div xmlns="http://www.w3.org/1999/xhtml"></div>
        EXPECTED, $result->html);
    }

    /**
     * Tests that elements with `qpy:if-role` attributes are left be when the user has at least one of the given roles.
     *
     * @throws coding_exception
     * @covers \qtype_questionpy\local\attempt_ui\question_ui_renderer
     */
    public function test_should_not_remove_element_with_if_role_attribute(): void {
        $input = file_get_contents(__DIR__ . '/question_uis/if-role.xhtml');
        $qa = $this->create_question_attempt_stub();

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $options = new \question_display_options();
        $options->context = \context_course::instance($course->id);

        $result = question_ui_renderer::render($input, [], $options, $qa);

        $this->assert_html_string_equals_html_string(<<<EXPECTED
        <div xmlns="http://www.w3.org/1999/xhtml">
            <div>You're a teacher!</div>
            <div>You're a developer!</div>
            <div>You're a scorer!</div>
            <div>You're a proctor!</div>
            <div>You're any of the above!</div>
        </div>
        EXPECTED, $result->html);
    }

    /**
     * Tests `qpy:format-float` elements when the current language is en.
     *
     * @throws coding_exception
     * @covers \qtype_questionpy\local\attempt_ui\question_ui_renderer
     */
    public function test_should_format_floats_in_en(): void {
        $input = file_get_contents(__DIR__ . '/question_uis/format-floats.xhtml');
        $qa = $this->create_question_attempt_stub();

        $result = question_ui_renderer::render($input, [], new \question_display_options(), $qa);

        $this->assert_html_string_equals_html_string(<<<EXPECTED
        <div xmlns="http://www.w3.org/1999/xhtml">
            Just the decsep: 1.23456
            Thousands sep without decimals: 1,000,000,000
            Thousands sep with decimals: 10,000,000,000.123
            Round down: 1.11
            Round up: 1.12
            Pad with zeros: 1.10000
            Strip zeros: 1.1
        </div>
        EXPECTED, $result->html);
    }

    /**
     * Tests the replacement of QPy-URIs.
     *
     * @return void
     * @throws coding_exception
     * @covers \qtype_questionpy\local\attempt_ui\question_ui_renderer::replace_qpy_urls
     */
    public function test_should_replace_qpy_urls(): void {
        $input = file_get_contents(__DIR__ . '/question_uis/qpy-urls.xhtml');
        $qa = $this->create_question_attempt_stub('deadbeef');

        $result = question_ui_renderer::render($input, [], new \question_display_options(), $qa);

        // phpcs:disable moodle.Files.LineLength.MaxExceeded
        $this->assert_html_string_equals_html_string(<<<EXPECTED
        <div xmlns="http://www.w3.org/1999/xhtml">
            static link: <a href="https://www.example.com/moodle/pluginfile.php//qtype_questionpy/static/deadbeef/local/minimal_example/path1/path2/filename.txt">https://www.example.com/moodle/pluginfile.php//qtype_questionpy/static/deadbeef/local/minimal_example/path1/path2/filename.txt</a>
            minimal path: <a href="https://www.example.com/moodle/pluginfile.php//qtype_questionpy/static/deadbeef/local/minimal_example/f">https://www.example.com/moodle/pluginfile.php//qtype_questionpy/static/deadbeef/local/minimal_example/f</a>
        </div>
        EXPECTED, $result->html);
        // phpcs:enable moodle.Files.LineLength.MaxExceeded
    }

    /**
     * Tests {@see question_ui_renderer::set_input_values_and_readonly}.
     *
     * @return void
     * @throws coding_exception
     * @covers \qtype_questionpy\local\attempt_ui\question_ui_renderer::set_input_values_and_readonly
     */
    public function test_should_correctly_fill_data(): void {
        $input = file_get_contents(__DIR__ . '/question_uis/input-values.xhtml');
        $qa = $this->create_question_attempt_stub('deadbeef', lastresponse: [
            'my_text' => 'new',
            'my_checkbox_value' => 'value',
            'my_checkbox_on' => 'on',
            'my_radio' => 'value1',
            'my_select' => 'value3',
            'my_hidden' => 'new',
            'my_button' => 'should be ignored',
            'my_textarea' => 'new',
        ]);

        $result = question_ui_renderer::render($input, [], new \question_display_options(), $qa);

        $this->assert_html_string_equals_html_string(<<<EXPECTED
        <div xmlns="http://www.w3.org/1999/xhtml" id="my_div">
            <input class="form-control qpy-input" type="text" name="my_text" value="new"/>

            <input class="qpy-input" type="checkbox" name="my_checkbox_value" value="value" checked="checked"/>
            <input class="qpy-input" type="checkbox" name="my_checkbox_on" checked="checked"/>

            <input class="qpy-input" type="radio" name="my_radio" value="value1" checked="checked"/>
            <input class="qpy-input" type="radio" name="my_radio" value="value2"/>

            <select class="form-control qpy-input" name="my_select">
                <option value="value1"/>
                <option value="value2"/>
                <option value="value3" selected="selected"/>
            </select>

            <input class="form-control qpy-input" type="hidden" name="my_hidden" value="new"/>

            <input class="btn btn-primary qpy-input" name="my_button" type="button" value="value1"/>
            <input class="btn btn-primary qpy-input" name="my_button" type="button" value="value2"/>

            <textarea class="form-control qpy-input" name="my_textarea">new</textarea>
        </div>
        EXPECTED, $result->html);
    }

    /**
     * Tests that a fallback option is added when the current value isn't that of any option.
     *
     * @return void
     * @throws coding_exception
     * @covers \qtype_questionpy\local\attempt_ui\question_ui_renderer::set_select_value
     */
    public function test_should_add_fallback_option_to_select_when_value_isnt_present(): void {
        $input = file_get_contents(__DIR__ . '/question_uis/select.xhtml');
        $qa = $this->create_question_attempt_stub('deadbeef', lastresponse: [
            'my_select' => 'something',
        ]);

        $result = question_ui_renderer::render($input, [], new \question_display_options(), $qa);

        $this->assert_html_string_equals_html_string(<<<EXPECTED
        <div xmlns="http://www.w3.org/1999/xhtml" >
            <select class="form-control qpy-input" name="my_select">
                <option value="value1"/>
                <option value="value2"/>
                <option value="value3"/>
                <option value="something" selected="selected">(the selected option is no longer available)</option>
            </select>
        </div>
        EXPECTED, $result->html);
    }

    /**
     * Tests that render warnings are generated when the last response contains invalid values.
     *
     * @return void
     * @throws coding_exception
     * @covers \qtype_questionpy\local\attempt_ui\question_ui_renderer::extract_available_options
     * @covers \qtype_questionpy\local\attempt_ui\question_ui_renderer::check_for_unknown_options
     */
    public function test_should_warn_about_invalid_values(): void {
        $input = file_get_contents(__DIR__ . '/question_uis/input-values.xhtml');
        $qa = $this->create_question_attempt_stub('deadbeef', lastresponse: [
            'my_checkbox_value' => 'other_value',
            'my_checkbox_on' => 'schmon',
            'my_radio' => 'value13',
            'my_select' => 'value42',
        ]);

        $result = question_ui_renderer::render($input, [], new \question_display_options(), $qa);
        $this->assertEqualsCanonicalizing([
            new invalid_option_warning('my_checkbox_value', 'other_value', ['value'], preserved: false),
            new invalid_option_warning('my_checkbox_on', 'schmon', ['on'], preserved: false),
            new invalid_option_warning('my_radio', 'value13', ['value1', 'value2'], preserved: false),
            new invalid_option_warning('my_select', 'value42', ['value1', 'value2', 'value3'], preserved: true),
        ], $result->warnings);
    }

    /**
     * Tests that render warnings are NOT generated when the input element has `qpy:warn-on-unknown-option="no"`.
     *
     * @return void
     * @throws coding_exception
     * @covers \qtype_questionpy\local\attempt_ui\question_ui_renderer::extract_available_options
     * @covers \qtype_questionpy\local\attempt_ui\question_ui_renderer::check_for_unknown_options
     */
    public function test_should_not_warn_about_invalid_values_when_input_opts_out(): void {
        $input = file_get_contents(__DIR__ . '/question_uis/input-values-nowarn.xhtml');
        $qa = $this->create_question_attempt_stub('deadbeef', lastresponse: [
            'my_checkbox_value' => 'other_value',
            'my_checkbox_on' => 'schmon',
            'my_radio' => 'value13',
            'my_select' => 'value42',
        ]);

        $result = question_ui_renderer::render($input, [], new \question_display_options(), $qa);
        $this->assertEmpty($result->warnings);
    }

    /**
     * Creates a stub question attempt which should fulfill the needs of most tests.
     *
     * @param string|null $packagehash explicit package hash. Random if unset.
     * @param int|null $id explicit attempt database id. Random if unset.
     * @param array $lastresponse last response submitted in the attempt
     * @return question_attempt&Stub
     * @throws coding_exception
     */
    private function create_question_attempt_stub(?string $packagehash = null, ?int $id = null,
                                                  array $lastresponse = []): question_attempt {
        $packagehash ??= hash('sha256', random_string(64));
        $id ??= mt_rand();
        $question = new qtype_questionpy_question($packagehash, '{}', null, $this->createStub(api::class));

        $step = new question_attempt_step([constants::QT_VAR_RESPONSE => json_encode((object) $lastresponse)]);

        $qa = new testable_question_attempt($question, 1);
        $qa->set_database_id($id);
        $qa->set_slot(1);
        $qa->add_step($step);

        return $qa;
    }
}
