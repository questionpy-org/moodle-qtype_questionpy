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

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Parses the question UI XML and extracts the metadata.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2023 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_ui_metadata_extractor {
    /** @var string XML namespace for XHTML */
    private const XHTML_NAMESPACE = "http://www.w3.org/1999/xhtml";
    /** @var string XML namespace for our custom things */
    private const QPY_NAMESPACE = "http://questionpy.org/ns/question";

    /** @var DOMDocument $xml */
    private DOMDocument $xml;

    /** @var DOMXPath $xpath */
    private DOMXPath $xpath;

    /** @var question_metadata|null $metadata */
    private ?question_metadata $metadata = null;

    /**
     * Parses the given XML and initializes a new {@see question_ui_metadata_extractor} instance.
     *
     * @param string $xml XML as returned by the QPy Server
     */
    public function __construct(string $xml) {
        $this->xml = new DOMDocument();
        $this->xml->loadXML($xml);

        $this->xpath = new DOMXPath($this->xml);
        $this->xpath->registerNamespace("xhtml", self::XHTML_NAMESPACE);
        $this->xpath->registerNamespace("qpy", self::QPY_NAMESPACE);
    }

    /**
     * Extracts metadata from the question UI.
     *
     * @return question_metadata
     */
    public function extract(): question_metadata {
        if (!is_null($this->metadata)) {
            return $this->metadata;
        }

        $this->metadata = new question_metadata();
        /** @var DOMAttr $attr */
        foreach ($this->xpath->query("//@qpy:correct-response") as $attr) {
            /** @var DOMElement $element */
            $element = $attr->ownerElement;
            $name = $element->getAttribute("name");
            if (!$name) {
                continue;
            }

            if (is_null($this->metadata->correctresponse)) {
                $this->metadata->correctresponse = [];
            }

            if ($element->tagName == "input" && $element->getAttribute("type") == "radio") {
                // On radio buttons, we expect the correct option to be marked with correct-response.
                $radiovalue = $element->getAttribute("value");
                $this->metadata->correctresponse[$name] = $radiovalue;
            } else {
                $this->metadata->correctresponse[$name] = $attr->value;
            }
        }

        /** @var DOMElement $element */
        foreach (
            $this->xpath->query(
                "//*[self::xhtml:input or self::xhtml:select or self::xhtml:textarea or self::xhtml:button]"
            ) as $element
        ) {
            $name = $element->getAttribute("name");
            if ($name) {
                $this->metadata->expecteddata[$name] = PARAM_RAW;

                if ($element->hasAttribute("required")) {
                    $this->metadata->requiredfields[] = $name;
                }
            }
        }

        return $this->metadata;
    }
}
