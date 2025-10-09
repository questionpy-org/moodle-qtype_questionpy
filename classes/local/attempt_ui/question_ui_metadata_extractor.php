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

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMXPath;
use qtype_questionpy\constants;

/**
 * Parses the question UI XML and extracts the metadata.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2023 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_ui_metadata_extractor {
    /** @var DOMDocument $xml */
    private DOMDocument $xml;

    /** @var DOMXPath $xpath */
    private DOMXPath $xpath;

    /**
     * @var array|false $correctresponse `false` if not yet extracted, empty array if not set in the XML.
     * @see \qtype_questionpy_question::get_correct_response()
     */
    private array|null|false $correctresponse = false;

    /**
     * @var string[] $requiredfields `false` if not yet extracted.
     * @see \question_manually_gradable::is_complete_response()
     * @see \question_manually_gradable::is_gradable_response()
     */
    private array|false $requiredfields = false;

    /**
     * Parses the given XML and initializes a new {@see question_ui_metadata_extractor} instance.
     *
     * @param string $xml XML as returned by the QPy Server
     */
    public function __construct(string $xml) {
        $this->xml = new DOMDocument();
        $this->xml->loadXML($xml);

        $this->xpath = new DOMXPath($this->xml);
        $this->xpath->registerNamespace('xhtml', constants::NAMESPACE_XHTML);
        $this->xpath->registerNamespace('qpy', constants::NAMESPACE_QPY);
    }

    /**
     * Extracts the names of required fields from the question UI XML.
     *
     * @return string[]
     */
    public function get_required_fields(): array {
        if ($this->requiredfields !== false) {
            return $this->requiredfields;
        }

        $this->requiredfields = [];

        /** @var DOMElement $element */
        foreach (
            $this->xpath->query(
                '//*[self::xhtml:input or self::xhtml:select or self::xhtml:textarea or self::xhtml:button]'
            ) as $element
        ) {
            $name = $element->getAttribute('name');
            if ($name && $element->hasAttribute('required')) {
                $this->requiredfields[] = $name;
            }
        }

        return $this->requiredfields;
    }

    /**
     * Returns the correct response for any fields that use the `@qpy:correct-response` attribute, or null if none do.
     *
     * @return array<string, string>|null
     */
    public function get_correct_response(): ?array {
        if ($this->correctresponse !== false) {
            return $this->correctresponse;
        }

        $this->correctresponse = null;

        /** @var DOMAttr $attr */
        foreach ($this->xpath->query('//@qpy:correct-response') as $attr) {
            /** @var DOMElement $element */
            $element = $attr->ownerElement;
            $name = $element->getAttribute('name');
            if (!$name) {
                continue;
            }

            if (is_null($this->correctresponse)) {
                $this->correctresponse = [];
            }

            if ($element->tagName == 'input' && $element->getAttribute('type') == 'radio') {
                // On radio buttons, we expect the correct option to be marked with correct-response.
                $radiovalue = $element->getAttribute('value');
                $this->correctresponse[$name] = $radiovalue;
            } else {
                $this->correctresponse[$name] = $attr->value;
            }
        }

        return $this->correctresponse;
    }
}
