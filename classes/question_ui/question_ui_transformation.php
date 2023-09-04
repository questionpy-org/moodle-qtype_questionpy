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

use DOMDocument;
use DOMElement;
use DOMNameSpaceNode;
use DOMNode;
use DOMNodeList;
use DOMProcessingInstruction;
use DOMXPath;
use question_attempt;
use question_display_options;

/**
 * Base class for Question UI transformations.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2023 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class question_ui_transformation {

    /** @var DOMDocument */
    protected DOMDocument $doc;
    /** @var DOMXPath */
    protected DOMXPath $xpath;
    /** @var question_attempt */
    protected question_attempt $qa;
    /** @var question_display_options|null */
    protected ?question_display_options $options;

    /**
     * Initializes a new instance.
     *
     * @param DOMXPath $xpath an XPath context for the question document
     * @param question_attempt $qa
     * @param question_display_options|null $options
     */
    final public function __construct(DOMXPath $xpath, question_attempt $qa, ?question_display_options $options) {
        $this->doc = $xpath->document;
        $this->xpath = $xpath;
        $this->qa = $qa;
        $this->options = $options;
    }

    /**
     * Returns the nodes which this transformation should apply to.
     *
     * @return DOMNodeList
     */
    abstract public function collect(): DOMNodeList;

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
        if ($node instanceof DOMElement) {
            $this->transform_element($node);
        } else if ($node instanceof DOMProcessingInstruction) {
            $this->transform_pi($node);
        }
    }

    /**
     * Transforms the given element in-place. Delegated to by {@see transform_node()}.
     *
     * @param DOMElement $element one of the elements returned by {@see collect()}
     * @return void
     */
    protected function transform_element(DOMElement $element): void {
        // Default no-op.
    }

    /**
     * Transforms the given processing instruction in-place. Delegated to by {@see transform_node()}.
     *
     * @param DOMProcessingInstruction $pi one of the PIs returned by {@see collect()}
     * @return void
     */
    protected function transform_pi(DOMProcessingInstruction $pi): void {
        // Default no-op.
    }
}
