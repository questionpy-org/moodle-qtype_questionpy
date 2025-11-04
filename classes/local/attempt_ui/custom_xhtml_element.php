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


use core\exception\coding_exception;
use DOMElement;
use DOMNode;
use file_exception;
use moodle_exception;
use question_attempt;
use stored_file_creation_exception;

/**
 * Represents a `<qpy:X/>` element in the question UI XML.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2025 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface custom_xhtml_element {
    /**
     * Parses the given DOMElement if possible.
     *
     * @param DOMElement $element
     * @return static|null
     */
    public static function from_element(DOMElement $element): ?static;

    /**
     * Renders this element to a DOMNode.
     *
     * @param question_attempt $qa
     * @param question_ui_renderer $renderer
     * @return DOMNode
     * @throws coding_exception
     * @throws file_exception
     * @throws moodle_exception
     * @throws stored_file_creation_exception
     */
    public function render(question_attempt $qa, question_ui_renderer $renderer): DOMNode;
}
