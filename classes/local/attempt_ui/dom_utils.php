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

use coding_exception;
use DOMDocument;
use DOMDocumentFragment;
use DOMElement;
use DOMException;
use DOMNode;
use qtype_questionpy\constants;

/**
 * Utility functions for working with the DOM.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2024 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dom_utils {
    /**
     * Adds the given class names to the elements `class` attribute if not already present.
     *
     * @param DOMElement $element
     * @param string ...$newclasses
     * @return void
     */
    public static function add_class_names(DOMElement $element, string ...$newclasses): void {
        $classarray = preg_split('/\s+/', $element->getAttribute('class'), flags: PREG_SPLIT_NO_EMPTY);
        $classarray = array_unique(array_merge($classarray, $newclasses));
        $element->setAttribute('class', implode(' ', $classarray));
    }

    /**
     * Parses some HTML source and returns a fragment.
     *
     * @param DOMDocument $doc target document which the fragment should belong to
     * @param string $html
     * @param int $options
     * @return DOMDocumentFragment|false fragment on success (or ignored errors), false on failure
     * @see DOMDocumentFragment::appendXML() the XML equivalent is provided by PHP, but not HTML :(
     */
    public static function html_to_fragment(DOMDocument $doc, string $html, int $options = 0): DOMDocumentFragment|false {
        $newdoc = new DOMDocument();
        // Libxml will add html and/or body elements and a DTD declaration without these options.
        $options |= LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD;
        // Despite LIBXML_HTML_NOIMPLIED, libxml will wrap a <p>-tag around the html if it doesn't have a root element.
        if (!$newdoc->loadHTML('<body>' . $html . '</body>', $options)) {
            return false;
        }

        $fragment = $doc->createDocumentFragment();
        /** @var DOMNode $childnode */
        foreach ($newdoc->documentElement->childNodes as $childnode) {
            $imported = $doc->importNode($childnode, deep: true);
            if ($imported === false) {
                debugging('Could not import HTML node from placeholder value');
                return false;
            }
            $fragment->appendChild($imported);
        }

        return $fragment;
    }

    /**
     * Modifies the given XHTML select element to set its value(s) to the given one(s).
     *
     * The `selected` attribute is added to the option(s) whose values are included in the given array and removed from all others.
     * If the array contains values for which no option exists, fallback options are added, which will show that the selected option
     * is missing while preserving the value.
     *
     * Multi-selects are supported by just passing an array with a single entry.
     *
     * @param DOMElement $select
     * @param string[] $values
     * @throws coding_exception
     */
    public static function set_select_values(DOMElement $select, array $values): void {
        $invalidvalues = array_flip($values);
        // Find the appropriate option and mark it as selected.
        foreach ($select->getElementsByTagName('option') as $option) {
            $optvalue = $option->hasAttribute('value') ? $option->getAttribute('value') : $option->textContent;
            if (in_array($optvalue, $values)) {
                $option->setAttribute('selected', 'selected');
                unset($invalidvalues[$optvalue]);
            } else {
                $option->removeAttribute('selected');
            }
        }

        foreach (array_keys($invalidvalues) as $invalidvalue) {
            // At least one of the set values belongs to none of the available options.
            try {
                $fallbackoption = $select->ownerDocument->createElementNS(
                    constants::NAMESPACE_XHTML,
                    'option',
                    get_string('missing_select_option', 'qtype_questionpy')
                );
            } catch (DOMException $e) {
                // Thrown by createElementNS "If invalid $namespace or $qualifiedName", which are both constants, so
                // the coding_exception fits.
                throw new coding_exception($e->getMessage());
            }

            if (!$fallbackoption) {
                debugging("Could not add fallback option element for value '$invalidvalue', which is no longer available.");
                return;
            }

            $fallbackoption->setAttribute('value', $invalidvalue);
            $fallbackoption->setAttribute('selected', 'selected');
            $select->appendChild($fallbackoption);
        }
    }
}
