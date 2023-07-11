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

/**
 * Transforms HTML `input` elements.
 *
 * - The name is mangled using {@see \question_attempt::get_qt_field_name()}.
 * - If a value was saved for the input in a previous step, the latest value is added to the HTML.
 * - If {@see \question_display_options::$readonly} is set, the input is disabled.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2023 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class input_transformation extends question_ui_transformation {

    /**
     * Returns the nodes which this transformation should apply to.
     *
     * @return DOMNodeList
     */
    public function collect(): DOMNodeList {
        return $this->doc->getElementsByTagName("input");
    }

    /**
     * Transforms the given element in-place. Delegated to by {@see transform_node()}.
     *
     * @param DOMElement $element
     * @return void
     */
    protected function transform_element(DOMElement $element): void {
        $name = $element->getAttribute("name");
        if (!$name) {
            return;
        }

        // Moodle expects question input names to be mangled to avoid collisions between the questions of a test.
        $element->setAttribute("name", $this->qa->get_qt_field_name($name));

        // Set the last saved value.
        $type = $element->getAttribute("type") ?: "text";
        $lastvalue = $this->qa->get_last_qt_var($name);

        if (!is_null($lastvalue)) {
            if ($element->getAttribute("value") === $lastvalue
                && ($type === "checkbox" || $type === "radio")) {
                $element->setAttribute("checked", "checked");
            } else {
                $element->setAttribute("value", $lastvalue);
            }
        }

        if ($this->options && $this->options->readonly) {
            $element->setAttribute("disabled", "disabled");
        }
    }
}
