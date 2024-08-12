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

namespace qtype_questionpy;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . "/question/type/questionpy/question.php");

use coding_exception;
use PHPUnit\Framework\MockObject\Stub;
use qtype_questionpy\api\api;
use qtype_questionpy_question;
use question_attempt;

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
    private function assert_html_string_equals_html_string(string $expectedhtml, string $actualhtml) {
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
     * @covers \qtype_questionpy\question_ui_renderer
     */
    public function test_should_hide_inline_feedback(): void {
        $input = file_get_contents(__DIR__ . "/question_uis/feedbacks.xhtml");

        $qa = $this->create_question_attempt_stub();
        $opts = new \question_display_options();
        $opts->hide_all_feedback();

        $ui = new question_ui_renderer($input, [], $opts, $qa);
        $result = $ui->render();

        $this->assert_html_string_equals_html_string(<<<EXPECTED
        <div xmlns="http://www.w3.org/1999/xhtml">
            <span>No feedback</span>
        </div>
        EXPECTED, $result);
    }

    /**
     * Tests that inline feedback is shown when the {@see \question_display_options} say so.
     *
     * @throws coding_exception
     * @covers \qtype_questionpy\question_ui_renderer
     */
    public function test_should_show_inline_feedback(): void {
        $input = file_get_contents(__DIR__ . "/question_uis/feedbacks.xhtml");

        $qa = $this->create_question_attempt_stub();
        $opts = new \question_display_options();

        $ui = new question_ui_renderer($input, [], $opts, $qa);
        $result = $ui->render();

        $this->assert_html_string_equals_html_string(<<<EXPECTED
        <div xmlns="http://www.w3.org/1999/xhtml">
            <span>No feedback</span>
            <span>General feedback</span>
            <span>Specific feedback</span>
        </div>
        EXPECTED, $result);
    }

    /**
     * Tests that `name` attributes in most elements are mangled correctly.
     *
     * @throws coding_exception
     * @covers \qtype_questionpy\question_ui_renderer
     */
    public function test_should_mangle_names(): void {
        $input = file_get_contents(__DIR__ . "/question_uis/ids_and_names.xhtml");

        $qa = $this->create_question_attempt_stub();

        $ui = new question_ui_renderer($input, [], new \question_display_options(), $qa);
        $result = $ui->render();

        $this->assert_html_string_equals_html_string(<<<EXPECTED
        <div xmlns="http://www.w3.org/1999/xhtml" id="mangled:my_div">
            <datalist id="mangled:my_list">
                <option>42</option>
            </datalist>
            <label>Wrapping label <input class="form-control qpy-input" name="mangled:my_number"
                                         type="number" list="mangled:my_list"/></label>
            <label for="mangled:my_select">Separate label</label>
            <select class="form-control qpy-input" id="mangled:my_select" name="mangled:my_select">
                <option value="1">One</option>
                <option value="2">Two</option>
            </select>
            <input class="qpy-input" type="radio" name="mangled:my_radio" value="1"/>
            <input class="qpy-input" type="radio" name="mangled:my_radio" value="2"/>
            <textarea class="form-control qpy-input" name="mangled:my_text"/>
            <button class="btn btn-primary qpy-input" name="mangled:my_button">Click me!</button>
            <map name="mangled:my_map">
                <area shape="circle" coords="1, 2, 3"/>
            </map>
            <img src="https://picsum.photos/200/300" usemap="#mangled:my_map"/>
        </div>
        EXPECTED, $result);
    }

    /**
     * Tests that `qpy:shuffle-elements` sticks to the same shuffled order as long as the seed (attempt id) is the same.
     *
     * @throws coding_exception
     * @covers \qtype_questionpy\question_ui_renderer
     */
    public function test_should_shuffle_the_same_way_in_same_attempt(): void {
        $input = file_get_contents(__DIR__ . "/question_uis/shuffle.xhtml");
        $qa = $this->create_question_attempt_stub();

        $firstresult = (new question_ui_renderer($input, [], new \question_display_options(), $qa))->render();
        for ($i = 0; $i < 10; $i++) {
            $result = (new question_ui_renderer($input, [], new \question_display_options(), $qa))->render();
            $this->assertEquals($firstresult, $result);
        }
    }

    /**
     * Tests that placeholders are replaced.
     *
     * @return void
     * @throws coding_exception
     * @covers \qtype_questionpy\question_ui_renderer
     */
    public function test_should_resolve_placeholders(): void {
        $input = file_get_contents(__DIR__ . "/question_uis/placeholder.xhtml");
        $qa = $this->create_question_attempt_stub();

        $ui = new question_ui_renderer($input, [
            "param" => "Value of param <b>one</b>.<script>'Oh no, danger!'</script>",
            "description" => "My simple description.",
        ], new \question_display_options(), $qa);
        $result = $ui->render();

        $this->assert_html_string_equals_html_string(<<<EXPECTED
        <div xmlns="http://www.w3.org/1999/xhtml">
            <div>My simple description.</div>
            <span>By default cleaned parameter: Value of param <b>one</b>.</span>
            <span>Explicitly cleaned parameter: Value of param <b>one</b>.</span>
            <span>Noclean parameter: Value of param <b>one</b>.<script>'Oh no, danger!'</script></span>
            <span>Plain parameter: Value of param &lt;b>one&lt;/b>.&lt;script>'Oh no, danger!'&lt;/script>
            </span>
        </div>
        EXPECTED, $result);
    }

    /**
     * Tests that placeholders are just removed when the corresponding value is missing.
     *
     * @return void
     * @throws coding_exception
     * @covers \qtype_questionpy\question_ui_renderer
     */
    public function test_should_remove_placeholders_when_no_corresponding_value(): void {
        $input = file_get_contents(__DIR__ . "/question_uis/placeholder.xhtml");
        $qa = $this->create_question_attempt_stub();

        $ui = new question_ui_renderer($input, [], new \question_display_options(), $qa);
        $result = $ui->render();

        $this->assert_html_string_equals_html_string(<<<EXPECTED
        <div xmlns="http://www.w3.org/1999/xhtml">
            <div></div>
            <span>By default cleaned parameter: </span>
            <span>Explicitly cleaned parameter: </span>
            <span>Noclean parameter: </span>
            <span>Plain parameter: </span>
        </div>
        EXPECTED, $result);
    }

    /**
     * Tests that validation attributes from input(-like) elements are replaced so as not to prevent submission.
     *
     * @return void
     * @throws coding_exception
     * @covers \qtype_questionpy\question_ui_renderer
     */
    public function test_should_soften_validations(): void {
        $input = file_get_contents(__DIR__ . "/question_uis/validations.xhtml");
        $qa = $this->create_question_attempt_stub();

        $ui = new question_ui_renderer($input, [], new \question_display_options(), $qa);
        $result = $ui->render();

        $this->assert_html_string_equals_html_string(<<<EXPECTED
        <div xmlns="http://www.w3.org/1999/xhtml">
            <input aria-required="true" data-qpy_required="data-qpy_required"/>
            <input data-qpy_pattern="^[a-z]+$"/>
            <input data-qpy_minlength="5"/>
            <input data-qpy_minlength="10"/>
            <input aria-valuemin="17" data-qpy_min="17"/>
            <input aria-valuemax="42" data-qpy_max="42"/>
            <input aria-required="true" data-qpy_required="data-qpy_required" data-qpy_pattern="^[a-z]+$"
                   data-qpy_minlength="5" data-qpy_maxlength="10"
                   aria-valuemin="17" data-qpy_min="17" aria-valuemax="42" data-qpy_max="42"/>
        </div>
        EXPECTED, $result);
    }

    /**
     * Tests that submit and reset buttons (which would also affect other questions) are turned into simple ones.
     *
     * @throws coding_exception
     * @covers \qtype_questionpy\question_ui_renderer
     */
    public function test_should_defuse_buttons(): void {
        $input = file_get_contents(__DIR__ . "/question_uis/buttons.xhtml");
        $qa = $this->create_question_attempt_stub();

        $ui = new question_ui_renderer($input, [], new \question_display_options(), $qa);
        $result = $ui->render();

        $this->assert_html_string_equals_html_string(<<<EXPECTED
        <div xmlns="http://www.w3.org/1999/xhtml">
            <button class="btn btn-primary qpy-input" type="button">Submit</button>
            <button class="btn btn-primary qpy-input" type="button">Reset</button>
            <button class="btn btn-primary qpy-input" type="button">Button</button>

            <input class="btn btn-primary qpy-input" type="button" value="Submit"/>
            <input class="btn btn-primary qpy-input" type="button" value="Reset"/>
            <input class="btn btn-primary qpy-input" type="button" value="Button"/>
        </div>
        EXPECTED, $result);
    }

    /**
     * Tests that elements with `qpy:if-role` attributes are removed when the user has none of the given roles.
     *
     * @throws coding_exception
     * @covers \qtype_questionpy\question_ui_renderer
     */
    public function test_should_remove_element_with_if_role_attribute(): void {
        $input = file_get_contents(__DIR__ . "/question_uis/if-role.xhtml");
        $qa = $this->create_question_attempt_stub();

        $this->resetAfterTest();
        $this->setGuestUser();

        $course = $this->getDataGenerator()->create_course();
        $options = new \question_display_options();
        $options->context = \context_course::instance($course->id);

        $ui = new question_ui_renderer($input, [], $options, $qa);
        $result = $ui->render();

        $this->assert_html_string_equals_html_string(<<<EXPECTED
        <div xmlns="http://www.w3.org/1999/xhtml"></div>
        EXPECTED, $result);
    }

    /**
     * Tests that elements with `qpy:if-role` attributes are left be when the user has at least one of the given roles.
     *
     * @throws coding_exception
     * @covers \qtype_questionpy\question_ui_renderer
     */
    public function test_should_not_remove_element_with_if_role_attribute(): void {
        $input = file_get_contents(__DIR__ . "/question_uis/if-role.xhtml");
        $qa = $this->create_question_attempt_stub();

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $options = new \question_display_options();
        $options->context = \context_course::instance($course->id);

        $ui = new question_ui_renderer($input, [], $options, $qa);

        $result = $ui->render();

        $this->assert_html_string_equals_html_string(<<<EXPECTED
        <div xmlns="http://www.w3.org/1999/xhtml">
            <div>You're a teacher!</div>
            <div>You're a developer!</div>
            <div>You're a scorer!</div>
            <div>You're a proctor!</div>
            <div>You're any of the above!</div>
        </div>
        EXPECTED, $result);
    }

    /**
     * Tests `qpy:format-float` elements when the current language is en.
     *
     * @throws coding_exception
     * @covers \qtype_questionpy\question_ui_renderer
     */
    public function test_should_format_floats_in_en(): void {
        $input = file_get_contents(__DIR__ . "/question_uis/format-floats.xhtml");
        $qa = $this->create_question_attempt_stub();

        $ui = new question_ui_renderer($input, [], new \question_display_options(), $qa);
        $result = $ui->render();

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
        EXPECTED, $result);
    }

    /**
     * Tests the replacement of QPy-URIs.
     *
     * @return void
     * @throws coding_exception
     * @covers \qtype_questionpy\question_ui_renderer::replace_qpy_urls
     */
    public function test_should_replace_qpy_urls(): void {
        $input = file_get_contents(__DIR__ . "/question_uis/qpy-urls.xhtml");
        $qa = $this->create_question_attempt_stub("deadbeef");

        $ui = new question_ui_renderer($input, [], new \question_display_options(), $qa);
        $result = $ui->render();

        // phpcs:disable moodle.Files.LineLength.MaxExceeded
        $this->assert_html_string_equals_html_string(<<<EXPECTED
        <div xmlns="http://www.w3.org/1999/xhtml">
            static link: <a href="https://www.example.com/moodle/pluginfile.php//qtype_questionpy/static/deadbeef/local/minimal_example/path1/path2/filename.txt">https://www.example.com/moodle/pluginfile.php//qtype_questionpy/static/deadbeef/local/minimal_example/path1/path2/filename.txt</a>
            minimal path: <a href="https://www.example.com/moodle/pluginfile.php//qtype_questionpy/static/deadbeef/local/minimal_example/f">https://www.example.com/moodle/pluginfile.php//qtype_questionpy/static/deadbeef/local/minimal_example/f</a>
        </div>
        EXPECTED, $result);
        // phpcs:enable moodle.Files.LineLength.MaxExceeded
    }

    /**
     * Creates a stub question attempt which should fulfill the needs of most tests.
     *
     * @param string|null $packagehash explicit package hash. Random if unset.
     * @return question_attempt&Stub
     */
    private function create_question_attempt_stub(?string $packagehash = null): question_attempt {
        $packagehash ??= hash("sha256", random_string(64));
        $question = new qtype_questionpy_question($packagehash, "{}", null, $this->createStub(api::class));

        $qa = $this->createStub(question_attempt::class);
        $qa->method("get_database_id")
            ->willReturn(mt_rand());
        $qa->method("get_question")
            ->willReturn($question);
        $qa->method("get_qt_field_name")
            ->willReturnCallback(function ($name) {
                return "mangled:$name";
            });
        return $qa;
    }
}
