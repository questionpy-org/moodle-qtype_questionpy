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

namespace qtype_questionpy\question_ui;

use DOMElement;
use DOMNodeList;
use qtype_questionpy\question_ui;

/**
 * Removes elements marked with `qpy:feedback` when the type of feedback is disabled in {@see \question_display_options}.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2023 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback_transformation extends question_ui_transformation {

    /**
     * Returns the nodes which this transformation should apply to.
     *
     * @return DOMNodeList
     */
    public function collect(): DOMNodeList {
        return $this->xpath->query("//*[@qpy:feedback]");
    }

    /**
     * Transforms the given element in-place. Delegated to by {@see transform_node()}.
     *
     * @param DOMElement $element
     * @return void
     */
    protected function transform_element(DOMElement $element): void {
        $feedback = $element->getAttributeNS(question_ui::QPY_NAMESPACE, "feedback");

        if (($feedback == "general" && !$this->options->generalfeedback)
            || ($feedback == "specific" && !$this->options->feedback)) {
            $element->parentNode->removeChild($element);
        }
    }
}
