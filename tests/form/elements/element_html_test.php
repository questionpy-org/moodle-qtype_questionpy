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

require_once(__DIR__ . "/test_moodleform.php");
require_once(__DIR__ . "/../../data_provider.php");

use qtype_questionpy\form\qpy_renderable;
use function qtype_questionpy\element_provider;

/**
 * Tests of the HTML rendering of form elements.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element_html_test extends \advanced_testcase {
    /**
     * Implements a snapshot testing approach similar to that of {@link https://jestjs.io/docs/snapshot-testing Jest}.
     *
     * Output is not compared to a manually written expectation HTML, but to the last output which was accepted by a
     * developer. This is intended to catch unintended changes in the rendering. In order to update the snapshots after
     * making intended changes instead of failing tests, re-run phpunit with the environment variable
     * `UPDATE_SNAPSHOTS=1`.
     *
     * @param string $elementkind     element kind, which the html file name is based on
     * @param qpy_renderable $element element to render
     * @dataProvider data_provider
     * @covers       \qtype_questionpy\form\elements
     * @covers       \qtype_questionpy\form\context\render_context
     * @covers       \qtype_questionpy\form\context\root_render_context
     * @covers       \qtype_questionpy\form\context\array_render_context
     * @covers       \qtype_questionpy\form\qpy_renderable
     */
    public function test_rendered_html_should_match_snapshot(string $elementkind, qpy_renderable $element): void {
        $snapshotfilepath = __DIR__ . "/html/" . $elementkind . ".html";

        // The sesskey is part of the form and therefore needs to be deterministic.
        $_SESSION['USER']->sesskey = "sesskey";
        $form = new test_moodleform($element);

        $actualhtml = $form->render();

        $actualdom = new \DOMDocument();
        $actualdom->loadHTML($actualhtml);
        $actualdom->preserveWhiteSpace = false;

        if (getenv("UPDATE_SNAPSHOTS")) {
            $actualdom->saveHTMLFile($snapshotfilepath);
            echo "Updated snapshot $snapshotfilepath.";
        }

        $expecteddom = new \DOMDocument();
        $expecteddom->loadHTMLFile($snapshotfilepath);
        $expecteddom->preserveWhiteSpace = false;

        $this->assertEquals($expecteddom, $actualdom);
    }

    /**
     * Provides argument pairs for {@see test_rendered_html_should_match_snapshot}.
     */
    public static function data_provider(): array {
        return element_provider();
    }
}
