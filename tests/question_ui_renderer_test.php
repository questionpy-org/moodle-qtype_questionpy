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
use DOMDocument;

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
     * Tests that inline feedback is hidden when the {@see \question_display_options} say so.
     *
     * @throws coding_exception
     * @covers \qtype_questionpy\question_ui_renderer
     */
    public function test_should_hide_inline_feedback() {
        $input = file_get_contents(__DIR__ . "/question_uis/feedbacks.xhtml");

        $qa = $this->createStub(\question_attempt::class);
        $qa->method("get_database_id")
            ->willReturn(mt_rand());
        $opts = new \question_display_options();
        $opts->hide_all_feedback();

        $ui = new question_ui_renderer($input, [], $opts, $qa);
        $result = $ui->render();

        $this->assertXmlStringEqualsXmlString(<<<EXPECTED
        <div>
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

        $qa = $this->createStub(\question_attempt::class);
        $qa->method("get_database_id")
            ->willReturn(mt_rand());
        $opts = new \question_display_options();

        $ui = new question_ui_renderer($input, [], $opts, $qa);
        $result = $ui->render();

        $this->assertXmlStringEqualsXmlString(<<<EXPECTED
        <div>
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
    public function test_should_mangle_names() {
        $input = file_get_contents(__DIR__ . "/question_uis/ids_and_names.xhtml");

        $qa = $this->createStub(\question_attempt::class);
        $qa->method("get_database_id")
            ->willReturn(mt_rand());
        $qa->method("get_qt_field_name")
            ->willReturnCallback(function ($name) {
                return "mangled:$name";
            });

        $ui = new question_ui_renderer($input, [], new \question_display_options(), $qa);
        $result = $ui->render();

        $this->assertXmlStringEqualsXmlString(<<<EXPECTED
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
        EXPECTED, $result);
    }

    /**
     * Tests that `qpy:shuffle-elements` sticks to the same shuffled order as long as the seed (attempt id) is the same.
     *
     * @throws coding_exception
     * @covers \qtype_questionpy\question_ui_renderer
     */
    public function test_should_shuffle_the_same_way_in_same_attempt() {
        $input = file_get_contents(__DIR__ . "/question_uis/shuffle.xhtml");
        $qa = $this->createStub(\question_attempt::class);
        $qa->method("get_database_id")
            ->willReturn(mt_rand());

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
    public function test_should_resolve_placeholders() {
        $input = file_get_contents(__DIR__ . "/question_uis/placeholder.xhtml");
        $qa = $this->createStub(\question_attempt::class);
        $qa->method("get_database_id")
            ->willReturn(mt_rand());

        $ui = new question_ui_renderer($input, [
            "param" => "Value of param <b>one</b>.<script>'Oh no, danger!'</script>",
            "description" => "My simple description.",
        ], new \question_display_options(), $qa);
        $result = $ui->render();

        $this->assertXmlStringEqualsXmlString(<<<EXPECTED
        <div>
            <div>My simple description.</div>
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
     * @throws coding_exception
     * @covers \qtype_questionpy\question_ui_renderer
     */
    public function test_should_remove_placeholders_when_no_corresponding_value() {
        $input = file_get_contents(__DIR__ . "/question_uis/placeholder.xhtml");
        $qa = $this->createStub(\question_attempt::class);
        $qa->method("get_database_id")
            ->willReturn(mt_rand());

        $ui = new question_ui_renderer($input, [], new \question_display_options(), $qa);
        $result = $ui->render();

        $this->assertXmlStringEqualsXmlString(<<<EXPECTED
        <div>
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
    public function test_should_soften_validations() {
        $input = file_get_contents(__DIR__ . "/question_uis/validations.xhtml");
        $qa = $this->createStub(\question_attempt::class);
        $qa->method("get_database_id")
            ->willReturn(mt_rand());

        $ui = new question_ui_renderer($input, [], new \question_display_options(), $qa);
        $result = $ui->render();

        $this->assertXmlStringEqualsXmlString(<<<EXPECTED
        <div>
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
    public function test_should_defuse_buttons() {
        $input = file_get_contents(__DIR__ . "/question_uis/buttons.xhtml");
        $qa = $this->createStub(\question_attempt::class);
        $qa->method("get_database_id")
            ->willReturn(mt_rand());

        $ui = new question_ui_renderer($input, [], new \question_display_options(), $qa);
        $result = $ui->render();


        $this->assertXmlStringEqualsXmlString(<<<EXPECTED
        <div>
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
    public function test_should_remove_element_with_if_role_attribute() {
        $input = file_get_contents(__DIR__ . "/question_uis/if-role.xhtml");
        $qa = $this->createStub(\question_attempt::class);
        $qa->method("get_database_id")
            ->willReturn(mt_rand());

        $this->resetAfterTest();
        $this->setGuestUser();

        $course = $this->getDataGenerator()->create_course();
        $options = new \question_display_options();
        $options->context = \context_course::instance($course->id);

        $ui = new question_ui_renderer($input, [], $options, $qa);
        $result = $ui->render();

        $this->assertXmlStringEqualsXmlString(<<<EXPECTED
        <div></div>
        EXPECTED, $result);
    }

    /**
     * Tests that elements with `qpy:if-role` attributes are left be when the user has at least one of the given roles.
     *
     * @throws coding_exception
     * @covers \qtype_questionpy\question_ui_renderer
     */
    public function test_should_not_remove_element_with_if_role_attribute() {
        $input = file_get_contents(__DIR__ . "/question_uis/if-role.xhtml");
        $qa = $this->createStub(\question_attempt::class);
        $qa->method("get_database_id")
            ->willReturn(mt_rand());

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $options = new \question_display_options();
        $options->context = \context_course::instance($course->id);

        $ui = new question_ui_renderer($input, [], $options, $qa);

        $result = $ui->render();

        $this->assertXmlStringEqualsXmlString(<<<EXPECTED
        <div>
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
    public function test_should_format_floats_in_en() {
        $input = file_get_contents(__DIR__ . "/question_uis/format-floats.xhtml");
        $qa = $this->createStub(\question_attempt::class);
        $qa->method("get_database_id")
            ->willReturn(mt_rand());

        $ui = new question_ui_renderer($input, [], new \question_display_options(), $qa);
        $result = $ui->render();

        $this->assertXmlStringEqualsXmlString(<<<EXPECTED
        <div>
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
}
