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

use coding_exception;
use DOMElement;
use DOMNode;
use DOMNodeList;
use qtype_questionpy\question_ui_renderer;

/**
 * Shuffles children of elements marked with `qpy:shuffle-contents`.
 *
 * Also replaces `qpy:shuffled-index` elements which are descendants of each child with the new index of the child.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2023 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class shuffle_transformation extends question_ui_transformation {

    /**
     * Returns the nodes which this transformation should apply to.
     *
     * @return DOMNodeList
     */
    public function collect(): DOMNodeList {
        return $this->xpath->query("//*[@qpy:shuffle-contents]");
    }

    /**
     * Transforms the given element in-place. Delegated to by {@see transform_node()}.
     *
     * @param DOMElement $element one of the elements returned by {@see collect()}
     * @return void
     * @throws coding_exception
     */
    protected function transform_element(DOMElement $element): void {
        $element->removeAttributeNS(question_ui_renderer::QPY_NAMESPACE, "shuffle-contents");
        $newelement = $element->cloneNode();

        // We want to shuffle elements while leaving other nodes (such as text, spacing) where they are.

        // Collect the elements and shuffle them.
        $childelements = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $childelements[] = $child;
            }
        }
        shuffle($childelements);

        // Iterate over children, replacing elements with random ones while copying everything else.
        $i = 1;
        while ($element->hasChildNodes()) {
            $child = $element->firstChild;
            if ($child instanceof DOMElement) {
                $child = array_pop($childelements);
                $newelement->appendChild($child);
                $this->replace_indices($child, $i++);
            } else {
                $newelement->appendChild($child);
            }
        }

        $element->parentNode->replaceChild($newelement, $element);
    }

    /**
     * Among the descendants of `$element`, finds `qpy:shuffled-index` elements and replaces them with `$index`.
     *
     * @param DOMNode $element
     * @param int $index
     * @throws coding_exception
     */
    private function replace_indices(DOMNode $element, int $index): void {
        $indexelements = $this->xpath->query(".//qpy:shuffled-index", $element);
        /** @var DOMElement $indexelement */
        foreach ($indexelements as $indexelement) {
            $format = $indexelement->getAttribute("format") ?: "123";

            switch ($format) {
                default:
                    // TODO: Warning?
                case "123":
                    $indexstr = strval($index);
                    break;
                case "abc":
                    $indexstr = strtolower(\question_utils::int_to_letter($index));
                    break;
                case "ABC":
                    $indexstr = \question_utils::int_to_letter($index);
                    break;
                case "iii":
                    $indexstr = \question_utils::int_to_roman($index);
                    break;
                case "III":
                    $indexstr = strtoupper(\question_utils::int_to_roman($index));
                    break;
            }

            $indexelement->parentNode->replaceChild(new \DOMText($indexstr), $indexelement);
        }
    }
}
