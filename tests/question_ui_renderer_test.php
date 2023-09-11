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
        $input = file_get_contents(__DIR__ . "/question_uis/inputs.xhtml");

        $ui = new question_ui_renderer($input, mt_rand());
        $metadata = $ui->get_metadata();

        $this->assertEquals(new question_metadata([
            "my_number" => "42",
            "my_select" => "1",
            "my_radio" => "2",
            "my_text" => "Lorem ipsum dolor sit amet."
        ], [
            "my_number" => PARAM_RAW,
            "my_select" => PARAM_RAW,
            "my_radio" => PARAM_RAW,
            "my_text" => PARAM_RAW,
            "my_button" => PARAM_RAW
        ]), $metadata);
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

        $ui = new question_ui_renderer($input, mt_rand());

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
     * @covers \qtype_questionpy\question_ui_renderer
     */
    public function test_should_show_inline_feedback() {
        $input = file_get_contents(__DIR__ . "/question_uis/feedbacks.xhtml");

        $ui = new question_ui_renderer($input, mt_rand());

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

        $ui = new question_ui_renderer($input, mt_rand());
        $qa = $this->createStub(\question_attempt::class);

        $result = $ui->render_general_feedback($qa);

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

        $ui = new question_ui_renderer($input, mt_rand());
        $qa = $this->createStub(\question_attempt::class);

        $result = $ui->render_specific_feedback($qa);

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

        $ui = new question_ui_renderer($input, mt_rand());
        $qa = $this->createStub(\question_attempt::class);

        $result = $ui->render_right_answer($qa);

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

        $ui = new question_ui_renderer($input, mt_rand());
        $qa = $this->createStub(\question_attempt::class);

        $this->assertNull($ui->render_general_feedback($qa));
        $this->assertNull($ui->render_specific_feedback($qa));
        $this->assertNull($ui->render_right_answer($qa));
    }

    /**
     * Tests that an exception is thrown when the question formulation is missing.
     * @covers \qtype_questionpy\question_ui_renderer::render_formulation
     */
    public function test_should_throw_when_formulation_is_missing() {
        $input = file_get_contents(__DIR__ . "/question_uis/no-parts.xhtml");

        $ui = new question_ui_renderer($input, mt_rand());
        $qa = $this->createStub(\question_attempt::class);

        $this->expectException(coding_exception::class);
        $ui->render_formulation($qa, new \question_display_options());
    }

    /**
     * Tests that `name` attributes in most elements are mangled correctly.
     * @throws coding_exception
     * @covers \qtype_questionpy\question_ui_renderer
     */
    public function test_should_mangle_names() {
        $input = file_get_contents(__DIR__ . "/question_uis/inputs.xhtml");

        $ui = new question_ui_renderer($input, mt_rand());
        $qa = $this->createStub(\question_attempt::class);
        $qa->method("get_qt_field_name")
            ->willReturnCallback(function ($name) {
                return "mangled:$name";
            });

        $result = $ui->render_formulation($qa, new \question_display_options());

        $this->assertXmlStringEqualsXmlString(<<<EXPECTED
        <div xmlns="http://www.w3.org/1999/xhtml">
        <input name="mangled:my_number" type="number"/>
        <select name="mangled:my_select">
            <option value="1">One</option>
            <option value="2">Two</option>
        </select>
        <input type="radio" name="mangled:my_radio" value="1">One</input>
        <input type="radio" name="mangled:my_radio" value="2">Two</input>
        <textarea name="mangled:my_text"/>
        <button name="mangled:my_button">Click me!</button>
        </div>
        EXPECTED, $result);
    }

    /**
     * Tests that `qpy:shuffle-elements` sticks to the same shuffled order as long as the seed is the same.
     *
     * @throws coding_exception
     * @covers \qtype_questionpy\question_ui_renderer
     */
    public function test_should_shuffle_the_same_way_with_same_seed() {
        $input = file_get_contents(__DIR__ . "/question_uis/shuffle.xhtml");
        $qa = $this->createStub(\question_attempt::class);

        $seed = mt_rand();
        $firstresult = (new question_ui_renderer($input, $seed))
            ->render_formulation($qa, new \question_display_options());
        for ($i = 0; $i < 10; $i++) {
            $result = (new question_ui_renderer($input, $seed))
                ->render_formulation($qa, new \question_display_options());

            $this->assertEquals($firstresult, $result);
        }
    }
}
