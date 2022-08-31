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

namespace qtype_questionpy\form\elements;

defined('MOODLE_INTERNAL') || die();

use qtype_questionpy\form\renderable;
use qtype_questionpy\form\root_render_context;

/**
 * Stub {@see \moodleform} implementation for tests.
 */
class test_moodleform extends \moodleform {
    private renderable $element;

    public function __construct(renderable $element) {
        $this->element = $element;
        parent::__construct(null, null, "post", "", ["id" => "my_form"]);
    }

    protected function definition() {
        $context = new root_render_context($this, $this->_form);
        $this->element->render_to($context);
    }
}

class element_html_test extends \advanced_testcase {
    /**
     * Implements a snapshot testing approach similar to that of {@link https://jestjs.io/docs/snapshot-testing Jest}.
     *
     * Output is not compared to a manually written expectation HTML, but to the last output which was accepted by a
     * developer. This is intended to catch unintended changes in the rendering. In order to update the snapshots after
     * making intended changes instead of failing tests, re-run phpunit with the environment variable
     * `UPDATE_SNAPSHOTS=1`.
     *
     * @dataProvider data_provider
     * @covers       \qtype_questionpy\form\elements
     * @covers       \qtype_questionpy\form\render_context
     * @covers       \qtype_questionpy\form\root_render_context
     * @covers       \qtype_questionpy\form\group_render_context
     * @covers       \qtype_questionpy\form\renderable
     */
    public function test_rendered_html_should_match_snapshot(string $snapshotfilename, renderable $element): void {
        $snapshotfilepath = __DIR__ . "/html/" . $snapshotfilename;

        // The sesskey is part of the form and therefore needs to be deterministic.
        $_SESSION['USER']->sesskey = "sesskey";
        $form = new test_moodleform($element);

        $actualhtml = $form->render();

        $actualdom = new \DOMDocument();
        $actualdom->loadHTML($actualhtml);
        $actualdom->preserveWhiteSpace = false;

        if (getenv("UPDATE_SNAPSHOTS")) {
            $actualdom->saveHTMLFile($snapshotfilepath);
        }

        $expecteddom = new \DOMDocument();
        $expecteddom->loadHTMLFile($snapshotfilepath);
        $expecteddom->preserveWhiteSpace = false;

        $this->assertEquals($expecteddom, $actualdom);
    }

    public function data_provider(): array {
        return [
            ["checkbox.html", new checkbox_element("my_checkbox", "Left", "Right", true, true)],
            ["checkbox_group.html", new checkbox_group_element(
                new checkbox_element(
                    "my_checkbox", "Left", "Right",
                    true, true
                )
            )],
            ["group.html", new group_element(
                "my_group", "Name", new form_elements(
                    new text_input_element("first_name", "", true, null, "Vorname"),
                    new text_input_element("last_name", "", false, null, "Nachname (optional)"),
                )
            )],
            ["hidden.html", new hidden_element("my_hidden_value", "42")],
            ["radio_group.html", new radio_group_element(
                "my_radio", "Label", new options(
                new option("Option 1", "opt1", true),
                new option("Option 2", "opt2"),
            ), true
            )],
            ["select.html", new select_element(
                "my_select", "Label", new options(
                new option("Option 1", "opt1", true),
                new option("Option 2", "opt2"),
            ), true, true
            )],
            ["static_text.html", new static_text_element("Label", "Lorem ipsum dolor sit amet.")],
            ["text_input.html", new text_input_element("my_field", "Label", true, "default", "placeholder")],
        ];
    }
}
