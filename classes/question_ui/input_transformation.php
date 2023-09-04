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
        return $this->xpath->query("//xhtml:button | //xhtml:input | //xhtml:select | //xhtml:textarea");
    }

    /**
     * Transforms the given element in-place. Delegated to by {@see transform_node()}.
     *
     * @param DOMElement $element one of the elements returned by {@see collect()}
     * @return void
     */
    protected function transform_element(DOMElement $element): void {
        /* TODO: Probably split this into smaller transformations. Other things need mangling, and buttons don't need
                 value setting. */
        $name = $element->getAttribute("name");
        if (!$name) {
            return;
        }

        // Moodle expects question input names to be mangled to avoid collisions between the questions of a test.
        $element->setAttribute("name", $this->qa->get_qt_field_name($name));

        // Set the last saved value.
        if ($element->tagName == "input") {
            $type = $element->getAttribute("type") ?: "text";
        } else {
            $type = $element->tagName;
        }
        $lastvalue = $this->qa->get_last_qt_var($name);

        if (!is_null($lastvalue)) {
            if (($type === "checkbox" || $type === "radio")
                && $element->getAttribute("value") === $lastvalue) {
                $element->setAttribute("checked", "checked");
            } else if ($type == "select") {
                // Find the appropriate option and mark it as selected.
                /** @var DOMElement $option */
                foreach ($element->getElementsByTagName("option") as $option) {
                    $optvalue = $option->hasAttribute("value") ? $option->getAttribute("value") : $option->textContent;
                    if ($optvalue == $lastvalue) {
                        $option->setAttribute("selected", "selected");
                        break;
                    }
                }
            } else if ($type != "button" && $type != "submit" && $type != "hidden") {
                $element->setAttribute("value", $lastvalue);
            }
        }

        if ($this->options && $this->options->readonly) {
            $element->setAttribute("disabled", "disabled");
        }
    }
}
