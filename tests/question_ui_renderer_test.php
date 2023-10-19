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

use coding_exception;
use core\context;
use core\context_test;
use DOMException;

/**
 * Unit tests for {@see question_ui_renderer}.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2023 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_ui_renderer_test extends \advanced_testcase {

    /**
     * Tests that metadata is correctly extracted from the UI's input elements.
     *
     * @covers \qtype_questionpy\question_ui_renderer::get_metadata
     * @covers \qtype_questionpy\question_metadata
     */
    public function test_should_extract_correct_metadata() {
        $input = file_get_contents(__DIR__ . "/question_uis/metadata.xhtml");

        $ui = new question_ui_renderer($input, [], mt_rand());
        $metadata = $ui->get_metadata();

        $this->assertEquals(new question_metadata([
            "my_number" => "42",
            "my_select" => "1",
            "my_radio" => "2",
            "my_text" => "Lorem ipsum dolor sit amet.",
        ], [
            "my_number" => PARAM_RAW,
            "my_select" => PARAM_RAW,
            "my_radio" => PARAM_RAW,
            "my_text" => PARAM_RAW,
            "my_button" => PARAM_RAW,
            "only_lowercase_letters" => PARAM_RAW,
            "between_5_and_10_chars" => PARAM_RAW,
        ], ["my_number"]), $metadata);
    }

    /**
     * Tests that inline feedback is hidden when the {@see \question_display_options} say so.
     *
     * @throws coding_exception
     * @throws DOMException
     * @covers \qtype_questionpy\question_ui_renderer
     */
    public function test_should_hide_inline_feedback() {
        $input = file_get_contents(__DIR__ . "/question_uis/feedbacks.xhtml");

        $ui = new question_ui_renderer($input, [], mt_rand());

        $qa = $this->createStub(\question_attempt::class);
        $opts = new \question_display_options();
        $opts->hide_all_feedback();

        $result = $ui->render_formulation($qa, $opts);

        $this->assertXmlStringEqualsXmlString(<<<EXPECTED
        <div xmlns="http://www.w3.org/1999/xhtml">
        <span>No feedback</span>
        </div>
        EXPECTED, $result);
    }

    /**
     * Tests that inline feedback is shown when the {@see \question_display_options} say so.
     *
     * @throws coding_exception
     * @throws DOMException
     * @covers \qtype_questionpy\question_ui_renderer
     */
    public function test_should_show_inline_feedback() {
        $input = file_get_contents(__DIR__ . "/question_uis/feedbacks.xhtml");

        $ui = new question_ui_renderer($input, [], mt_rand());

        $qa = $this->createStub(\question_attempt::class);
        $opts = new \question_display_options();

        $result = $ui->render_formulation($qa, $opts);

        $this->assertXmlStringEqualsXmlString(<<<EXPECTED
        <div xmlns="http://www.w3.org/1999/xhtml">
        <span>No feedback</span>
        <span>General feedback</span>
        <span>Specific feedback</span>
        </div>
        EXPECTED, $result);
    }

    /**
     * Tests that general feedback is extracted correctly.
     *
     * @throws coding_exception
     * @throws DOMException
     * @covers \qtype_questionpy\question_ui_renderer::render_general_feedback
     */
    public function test_should_render_general_feedback_part_when_present() {
        $input = file_get_contents(__DIR__ . "/question_uis/all-parts.xhtml");

        $ui = new question_ui_renderer($input, [], mt_rand());
        $qa = $this->createStub(\question_attempt::class);

        $result = $ui->render_general_feedback($qa, new \question_display_options());

        $this->assertXmlStringEqualsXmlString(
            '<div xmlns="http://www.w3.org/1999/xhtml">General feedback part</div>',
            $result
        );
    }

    /**
     * Tests that specific feedback is extracted correctly.
     *
     * @throws coding_exception
     * @throws DOMException
     * @covers \qtype_questionpy\question_ui_renderer::render_specific_feedback
     */
    public function test_should_render_specific_feedback_part_when_present() {
        $input = file_get_contents(__DIR__ . "/question_uis/all-parts.xhtml");

        $ui = new question_ui_renderer($input, [], mt_rand());
        $qa = $this->createStub(\question_attempt::class);

        $result = $ui->render_specific_feedback($qa, new \question_display_options());

        $this->assertXmlStringEqualsXmlString(
            '<div xmlns="http://www.w3.org/1999/xhtml">Specific feedback part</div>',
            $result
        );
    }

    /**
     * Tests that the right answer explanation is extracted correctly.
     *
     * @throws coding_exception
     * @throws DOMException
     * @covers \qtype_questionpy\question_ui_renderer::render_right_answer
     */
    public function test_should_render_right_answer_part_when_present() {
        $input = file_get_contents(__DIR__ . "/question_uis/all-parts.xhtml");

        $ui = new question_ui_renderer($input, [], mt_rand());
        $qa = $this->createStub(\question_attempt::class);

        $result = $ui->render_right_answer($qa, new \question_display_options());

        $this->assertXmlStringEqualsXmlString(
            '<div xmlns="http://www.w3.org/1999/xhtml">Right answer part</div>',
            $result
        );
    }

    /**
     * Tests that `null` is returned when any of the optional parts of the XML are missing.
     *
     * @throws coding_exception
     * @throws DOMException
     * @covers \qtype_questionpy\question_ui_renderer::render_general_feedback
     * @covers \qtype_questionpy\question_ui_renderer::render_specific_feedback
     * @covers \qtype_questionpy\question_ui_renderer::render_right_answer
     */
    public function test_should_return_null_when_optional_part_is_missing() {
        $input = file_get_contents(__DIR__ . "/question_uis/no-parts.xhtml");

        $ui = new question_ui_renderer($input, [], mt_rand());
        $qa = $this->createStub(\question_attempt::class);

        $this->assertNull($ui->render_general_feedback($qa, new \question_display_options()));
        $this->assertNull($ui->render_specific_feedback($qa, new \question_display_options()));
        $this->assertNull($ui->render_right_answer($qa, new \question_display_options()));
    }

    /**
     * Tests that an exception is thrown when the question formulation is missing.
     * @covers \qtype_questionpy\question_ui_renderer::render_formulation
     * @throws DOMException
     */
    public function test_should_throw_when_formulation_is_missing() {
        $input = file_get_contents(__DIR__ . "/question_uis/no-parts.xhtml");

        $ui = new question_ui_renderer($input, [], mt_rand());
        $qa = $this->createStub(\question_attempt::class);

        $this->expectException(coding_exception::class);
        $ui->render_formulation($qa, new \question_display_options());
    }

    /**
     * Tests that `name` attributes in most elements are mangled correctly.
     * @throws coding_exception
     * @throws DOMException
     * @covers \qtype_questionpy\question_ui_renderer
     */
    public function test_should_mangle_names() {
        $input = file_get_contents(__DIR__ . "/question_uis/ids_and_names.xhtml");

        $ui = new question_ui_renderer($input, [], mt_rand());
        $qa = $this->createStub(\question_attempt::class);
        $qa->method("get_qt_field_name")
            ->willReturnCallback(function ($name) {
                return "mangled:$name";
            });

        $result = $ui->render_formulation($qa, new \question_display_options());

        $this->assertXmlStringEqualsXmlString(<<<EXPECTED
        <div xmlns="http://www.w3.org/1999/xhtml">
            <div id="mangled:my_div">
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
                <input class="qpy-input" type="radio" name="mangled:my_radio" value="1">One</input>
                <input class="qpy-input" type="radio" name="mangled:my_radio" value="2">Two</input>
                <textarea class="form-control qpy-input" name="mangled:my_text"/>
                <button class="btn btn-primary qpy-input" name="mangled:my_button">Click me!</button>
                <map name="mangled:my_map">
                    <area shape="circle" coords="1, 2, 3"/>
                </map>
                <img src="https://picsum.photos/200/300" usemap="#mangled:my_map"/>
            </div>
        </div>
        EXPECTED, $result);
    }

    /**
     * Tests that `qpy:shuffle-elements` sticks to the same shuffled order as long as the seed is the same.
     *
     * @throws coding_exception
     * @throws DOMException
     * @covers \qtype_questionpy\question_ui_renderer
     */
    public function test_should_shuffle_the_same_way_with_same_seed() {
        $input = file_get_contents(__DIR__ . "/question_uis/shuffle.xhtml");
        $qa = $this->createStub(\question_attempt::class);

        $seed = mt_rand();
        $firstresult = (new question_ui_renderer($input, [], $seed))
            ->render_formulation($qa, new \question_display_options());
        for ($i = 0; $i < 10; $i++) {
            $result = (new question_ui_renderer($input, [], $seed))
                ->render_formulation($qa, new \question_display_options());

            $this->assertEquals($firstresult, $result);
        }
    }

    /**
     * Tests that placeholders are replaced.
     *
     * @return void
     * @throws DOMException
     * @throws coding_exception
     * @covers \qtype_questionpy\question_ui_renderer
     */
    public function test_should_resolve_placeholders() {
        $input = file_get_contents(__DIR__ . "/question_uis/placeholder.xhtml");
        $qa = $this->createStub(\question_attempt::class);

        $ui = new question_ui_renderer($input, [
            "param" => "Value of param <b>one</b>.<script>'Oh no, danger!'</script>",
        ], mt_rand());

        $result = $ui->render_formulation($qa, new \question_display_options());

        $this->assertXmlStringEqualsXmlString(<<<EXPECTED
        <div xmlns="http://www.w3.org/1999/xhtml">
            <span>By default cleaned parameter: Value of param <b>one</b>.</span>
            <span>Explicitly cleaned parameter: Value of param <b>one</b>.</span>
            <span>Noclean parameter: Value of param <b>one</b>.<script>'Oh no, danger!'</script></span>
            <span>Plain parameter: <![CDATA[Value of param <b>one</b>.<script>'Oh no, danger!'</script>]]></span>
        </div>
        EXPECTED, $result);
    }

    /**
     * Tests that placeholders are just removed when the corresponding value is missing.
     *
     * @return void
     * @throws DOMException
     * @throws coding_exception
     * @covers \qtype_questionpy\question_ui_renderer
     */
    public function test_should_remove_placeholders_when_no_corresponding_value() {
        $input = file_get_contents(__DIR__ . "/question_uis/placeholder.xhtml");
        $qa = $this->createStub(\question_attempt::class);

        $ui = new question_ui_renderer($input, [], mt_rand());

        $result = $ui->render_formulation($qa, new \question_display_options());

        $this->assertXmlStringEqualsXmlString(<<<EXPECTED
        <div xmlns="http://www.w3.org/1999/xhtml">
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
     * @throws DOMException
     * @throws coding_exception
     * @covers \qtype_questionpy\question_ui_renderer
     */
    public function test_should_soften_validations() {
        $input = file_get_contents(__DIR__ . "/question_uis/validations.xhtml");
        $qa = $this->createStub(\question_attempt::class);

        $ui = new question_ui_renderer($input, [], mt_rand());

        $result = $ui->render_formulation($qa, new \question_display_options());

        $this->assertXmlStringEqualsXmlString(<<<EXPECTED
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
     * @throws DOMException
     * @throws coding_exception
     * @covers \qtype_questionpy\question_ui_renderer
     */
    public function test_should_defuse_buttons() {
        $input = file_get_contents(__DIR__ . "/question_uis/buttons.xhtml");
        $qa = $this->createStub(\question_attempt::class);

        $ui = new question_ui_renderer($input, [], mt_rand());

        $result = $ui->render_formulation($qa, new \question_display_options());

        $this->assertXmlStringEqualsXmlString(<<<EXPECTED
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
     * @throws DOMException
     * @throws coding_exception
     * @covers \qtype_questionpy\question_ui_renderer
     */
    public function test_should_remove_element_with_if_role_attribute() {
        $input = file_get_contents(__DIR__ . "/question_uis/if-role.xhtml");
        $qa = $this->createStub(\question_attempt::class);

        $this->resetAfterTest();
        $this->setGuestUser();

        $course = $this->getDataGenerator()->create_course();
        $options = new \question_display_options();
        $options->context = \context_course::instance($course->id);

        $ui = new question_ui_renderer($input, [], mt_rand());

        $result = $ui->render_formulation($qa, $options);

        $this->assertXmlStringEqualsXmlString(<<<EXPECTED
        <div xmlns="http://www.w3.org/1999/xhtml"></div>
        EXPECTED, $result);
    }

    /**
     * Tests that elements with `qpy:if-role` attributes are left be when the user has at least one of the given roles.
     *
     * @throws DOMException
     * @throws coding_exception
     * @covers \qtype_questionpy\question_ui_renderer
     */
    public function test_should_not_remove_element_with_if_role_attribute() {
        $input = file_get_contents(__DIR__ . "/question_uis/if-role.xhtml");
        $qa = $this->createStub(\question_attempt::class);

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $options = new \question_display_options();
        $options->context = \context_course::instance($course->id);

        $ui = new question_ui_renderer($input, [], mt_rand());

        $result = $ui->render_formulation($qa, $options);

        $this->assertXmlStringEqualsXmlString(<<<EXPECTED
        <div xmlns="http://www.w3.org/1999/xhtml">
            <div>You're a teacher!</div>
            <div>You're a developer!</div>
            <div>You're a scorer!</div>
            <div>You're a proctor!</div>
            <div>You're any of the above!</div>
        </div>
        EXPECTED, $result);
    }
}
