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

use DOMAttr;
use DOMNameSpaceNode;
use DOMNode;
use DOMNodeList;

/**
 * Removes remaining QuestionPy elements and attributes as well as comments and xmlns declarations.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2023 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_transformation extends question_ui_transformation {

    /**
     * Returns the nodes which this transformation should apply to.
     *
     * @return DOMNodeList
     */
    public function collect(): DOMNodeList {
        return $this->xpath->query("//qpy:* | //@qpy:* | //comment() | //namespace::*");
    }

    /**
     * Transforms the given node in-place.
     *
     * The default implementation delegates to {@see transform_element()} or {@see transform_pi()}, depending on the
     * node type.
     *
     * @param DOMNode|DOMNameSpaceNode $node one of the nodes returned by {@see collect()}
     * @return void
     */
    public function transform_node($node): void {
        if ($node instanceof DOMAttr || $node instanceof DOMNameSpaceNode) {
            $node->parentNode->removeAttributeNS($node->namespaceURI, $node->localName);
        } else {
            $node->parentNode->removeChild($node);
        }
    }
}
