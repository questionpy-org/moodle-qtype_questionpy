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

use coding_exception;
use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNameSpaceNode;
use DOMNode;
use DOMProcessingInstruction;
use DOMText;
use DOMXPath;
use question_attempt;
use question_display_options;

/**
 * Parses the question UI XML, transforms it, and renders it to HTML.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2023 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_ui_renderer {
    /** @var string XML namespace for XHTML */
    private const XHTML_NAMESPACE = "http://www.w3.org/1999/xhtml";
    /** @var string XML namespace for our custom things */
    private const QPY_NAMESPACE = "http://questionpy.org/ns/question";

    /** @var DOMDocument $xml */
    private DOMDocument $xml;

    /** @var DOMXPath $xpath */
    private DOMXPath $xpath;

    /** @var string|null $html */
    private ?string $html = null;

    /** @var array $placeholders */
    private array $placeholders;

    /** @var question_display_options $options */
    private question_display_options $options;

    /** @var question_attempt $attempt */
    private question_attempt $attempt;

    /**
     * Parses the given XML and initializes a new {@see question_ui_renderer} instance.
     *
     * @param string $xml         XML as returned by the QPy Server
     * @param array $placeholders string to string mapping of placeholder names to the values
     * @param question_display_options $options
     * @param question_attempt $attempt
     */
    public function __construct(string $xml, array $placeholders, question_display_options $options, question_attempt $attempt) {
        $this->xml = new DOMDocument();
        $this->xml->preserveWhiteSpace = false;
        $this->xml->loadXML($xml);
        $this->xml->normalizeDocument();

        $this->xpath = new DOMXPath($this->xml);
        $this->xpath->registerNamespace("xhtml", self::XHTML_NAMESPACE);
        $this->xpath->registerNamespace("qpy", self::QPY_NAMESPACE);

        $this->placeholders = $placeholders;
        $this->options = $options;
        $this->attempt = $attempt;
    }

    /**
     * Renders the given XML to HTML.
     *
     * @return string rendered html
     * @throws coding_exception
     */
    public function render(): string {
        if (!is_null($this->html)) {
            return $this->html;
        }

        $nextseed = mt_rand();
        $id = $this->attempt->get_database_id();
        if ($id === null) {
            throw new coding_exception("question_attempt does not have an id");
        }

        mt_srand($id);
        try {
            $this->resolve_placeholders();
            $this->hide_unwanted_feedback();
            $this->hide_if_role();
            $this->set_input_values_and_readonly();
            $this->soften_validation();
            $this->defuse_buttons();
            $this->shuffle_contents();
            $this->add_styles();
            $this->format_floats();
            $this->mangle_ids_and_names();
            $this->clean_up();
        } finally {
            // I'm not sure whether it is strictly necessary to reset the PRNG seed here, but it feels safer.
            // Resetting it to its original state would be ideal, but that doesn't seem to be possible.
            mt_srand($nextseed);
        }

        $this->html = $this->xml->saveXML();
        $this->html = substr($this->html, 21); /* 21 chars: '<?xml version="1.0"?>'. */
        return $this->html;
    }

    /**
     * Hides elements marked with `qpy:feedback` if the type of feedback is disabled in {@see question_display_options}.
     *
     * @return void
     */
    private function hide_unwanted_feedback(): void {
        /** @var DOMElement $element */
        foreach (iterator_to_array($this->xpath->query("//*[@qpy:feedback]")) as $element) {
            $feedback = $element->getAttributeNS(self::QPY_NAMESPACE, "feedback");

            if (
                ($feedback == "general" && !$this->options->generalfeedback)
                || ($feedback == "specific" && !$this->options->feedback)
            ) {
                $element->parentNode->removeChild($element);
            }
        }
    }

    /**
     * Shuffles children of elements marked with `qpy:shuffle-contents`.
     *
     * Also replaces `qpy:shuffled-index` elements which are descendants of each child with the new index of the child.
     *
     * @throws coding_exception
     */
    private function shuffle_contents(): void {
        /** @var DOMElement $element */
        foreach (iterator_to_array($this->xpath->query("//*[@qpy:shuffle-contents]")) as $element) {
            $element->removeAttributeNS(self::QPY_NAMESPACE, "shuffle-contents");
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
                    $this->replace_shuffled_indices($child, $i++);
                } else {
                    $newelement->appendChild($child);
                }
            }

            $element->parentNode->replaceChild($newelement, $element);
        }
    }

    /**
     * Among the descendants of `$element`, finds `qpy:shuffled-index` elements and replaces them with `$index`.
     *
     * @param DOMNode $element
     * @param int $index
     * @throws coding_exception
     */
    private function replace_shuffled_indices(DOMNode $element, int $index): void {
        /** @var DOMElement $indexelement */
        foreach (iterator_to_array($this->xpath->query(".//qpy:shuffled-index", $element)) as $indexelement) {
            // phpcs:ignore Squiz.ControlStructures.ForLoopDeclaration.SpacingAfterSecond
            for (
                $ancestor = $indexelement->parentNode; $ancestor !== null && $ancestor !== $indexelement;
                $ancestor = $ancestor->parentNode
            ) {
                assert($ancestor instanceof DOMElement);
                if ($ancestor->hasAttributeNS(self::QPY_NAMESPACE, "shuffle-contents")) {
                    // The index element is in a nested shuffle-contents.
                    // We want it to be replaced with the index of the inner shuffle, so we ignore it for now.
                    continue 2;
                }
            }

            $format = $indexelement->getAttribute("format") ?: "123";

            switch ($format) {
                default:
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

            $indexelement->parentNode->replaceChild(new DOMText($indexstr), $indexelement);
        }
    }

    /**
     * Mangles element IDs and names so that they are unique when multiple questions are shown at once.
     *
     * @return void
     */
    private function mangle_ids_and_names(): void {
        /** @var DOMAttr $attr */
        foreach (
            $this->xpath->query("
                //xhtml:*/@id | //xhtml:label/@for | //xhtml:output/@for | //xhtml:input/@list |
                (//xhtml:button | //xhtml:form | //xhtml:fieldset | //xhtml:iframe | //xhtml:input | //xhtml:object |
                 //xhtml:output | //xhtml:select | //xhtml:textarea | //xhtml:map)/@name |
                //xhtml:img/@usemap
                ") as $attr
        ) {
            $original = $attr->value;
            if ($attr->name === "usemap" && utils::str_starts_with($original, "#")) {
                // See https://developer.mozilla.org/en-US/docs/Web/API/HTMLImageElement/useMap.
                $attr->value = "#" . $this->attempt->get_qt_field_name(substr($original, 1));
            } else {
                $attr->value = $this->attempt->get_qt_field_name($original);
            }
        }
    }

    /**
     * Transforms input(-like) elements.
     *
     * - If {@see question_display_options} is set, the input is disabled.
     * - If a value was saved for the input in a previous step, the latest value is added to the HTML.
     *
     * Requires the unmangled name of the element, so must be called _before_ {@see mangle_ids_and_names}.
     *
     * @return void
     */
    private function set_input_values_and_readonly(): void {
        /** @var DOMElement $element */
        foreach ($this->xpath->query("//xhtml:button | //xhtml:input | //xhtml:select | //xhtml:textarea") as $element) {
            if ($this->options->readonly) {
                $element->setAttribute("disabled", "disabled");
            }

            // We want the unmangled name here, so this method must be called before mangle_ids_and_names.
            $name = $element->getAttribute("name");
            if (!$name) {
                continue;
            }

            if ($element->tagName == "input") {
                $type = $element->getAttribute("type") ?: "text";
            } else {
                $type = $element->tagName;
            }

            // Set the last saved value.
            $lastvalue = $this->attempt->get_last_qt_var($name);
            if (!is_null($lastvalue)) {
                if (($type === "checkbox" || $type === "radio") && $element->getAttribute("value") === $lastvalue) {
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
        }
    }

    /**
     * Removes remaining QuestionPy elements and attributes as well as comments and xmlns declarations.
     *
     * @return void
     */
    private function clean_up(): void {
        /** @var DOMNode|DOMNameSpaceNode $node */
        foreach (iterator_to_array($this->xpath->query("//qpy:* | //@qpy:* | //comment() | //namespace::*")) as $node) {
            if ($node instanceof DOMAttr || $node instanceof DOMNameSpaceNode) {
                $node->parentNode->removeAttributeNS($node->namespaceURI, $node->localName);
            } else {
                $node->parentNode->removeChild($node);
            }
        }
        /** @var DOMNode $root */
        $root = $this->xpath->document->documentElement;
        $root->removeAttributeNS(self::XHTML_NAMESPACE, "");
    }

    /**
     * Replace placeholder PIs such as `<?p my_key plain?>` with the appropriate value from `$this->placeholders`.
     *
     * Since QPy transformations should not be applied to the content of the placeholders, this method should be called
     * last.
     *
     * @return void
     */
    private function resolve_placeholders(): void {
        /** @var DOMProcessingInstruction $pi */
        foreach (iterator_to_array($this->xpath->query("//processing-instruction('p')")) as $pi) {
            $parts = preg_split("/\s+/", trim($pi->data));
            $key = $parts[0];
            $cleanoption = $parts[1] ?? "clean";

            if (!isset($this->placeholders[$key])) {
                $pi->parentNode->removeChild($pi);
            } else {
                $rawvalue = $this->placeholders[$key];
                if (strcasecmp($cleanoption, "clean") == 0) {
                    // Allow (X)HTML, but clean using Moodle's clean_text to prevent XSS.
                    $element = $this->xpath->document->createDocumentFragment();
                    $element->appendXML(clean_text($rawvalue));
                } else if (strcasecmp($cleanoption, "noclean") == 0) {
                    $element = $this->xpath->document->createDocumentFragment();
                    $element->appendXML($rawvalue);
                } else {
                    if (strcasecmp($cleanoption, "plain") != 0) {
                        debugging("Unrecognized placeholder cleaning option: '$cleanoption', using 'plain'");
                    }
                    // Treat the value as plain text and don't allow any kind of markup.
                    // Since we're adding a text node, the DOM handles escaping for us.
                    $element = new DOMText($rawvalue);
                }
                $pi->parentNode->replaceChild($element, $pi);
            }
        }
    }

    /**
     * Replaces the HTML attributes `pattern`, `required`, `minlength`, `maxlength`, `min, `max` so that submission is
     * not prevented.
     *
     * The standard attributes are replaced with `data-qpy_X`, which are then evaluated in JS.
     *
     * @return void
     */
    private function soften_validation(): void {
        /** @var DOMElement $element */
        foreach ($this->xpath->query("//xhtml:input[@pattern]") as $element) {
            $pattern = $element->getAttribute("pattern");
            $element->removeAttribute("pattern");
            $element->setAttribute("data-qpy_pattern", $pattern);
        }

        foreach ($this->xpath->query("(//xhtml:input | //xhtml:select | //xhtml:textarea)[@required]") as $element) {
            $element->removeAttribute("required");
            $element->setAttribute("data-qpy_required", "data-qpy_required");
            $element->setAttribute("aria-required", "true");
        }

        foreach ($this->xpath->query("(//xhtml:input | //xhtml:textarea)[@minlength]") as $element) {
            $minlength = $element->getAttribute("minlength");
            $element->removeAttribute("minlength");
            $element->setAttribute("data-qpy_minlength", $minlength);
        }

        foreach ($this->xpath->query("(//xhtml:input | //xhtml:textarea)[@maxlength]") as $element) {
            $maxlength = $element->getAttribute("maxlength");
            $element->removeAttribute("maxlength");
            $element->setAttribute("data-qpy_maxlength", $maxlength);
        }

        foreach ($this->xpath->query("//xhtml:input[@min]") as $element) {
            $min = $element->getAttribute("min");
            $element->removeAttribute("min");
            $element->setAttribute("data-qpy_min", $min);
            $element->setAttribute("aria-valuemin", $min);
        }

        foreach ($this->xpath->query("//xhtml:input[@max]") as $element) {
            $max = $element->getAttribute("max");
            $element->removeAttribute("max");
            $element->setAttribute("data-qpy_max", $max);
            $element->setAttribute("aria-valuemax", $max);
        }
    }

    /**
     * Adds CSS classes to various elements to style them similarly to Moodle's own question types.
     *
     * @return void
     */
    private function add_styles(): void {
        /** @var DOMElement $element */
        foreach (
            $this->xpath->query("
                //xhtml:input[@type != 'checkbox' and @type != 'radio' and
                              @type != 'button' and @type != 'submit' and @type != 'reset']
                | //xhtml:select | //xhtml:textarea
                ") as $element
        ) {
            $this->add_class_names($element, "form-control", "qpy-input");
        }

        foreach (
            $this->xpath->query("//xhtml:input[@type = 'button' or @type = 'submit' or @type = 'reset']
                                | //xhtml:button") as $element
        ) {
            $this->add_class_names($element, "btn", "btn-primary", "qpy-input");
        }

        foreach ($this->xpath->query("//xhtml:input[@type = 'checkbox' or @type = 'radio']") as $element) {
            $this->add_class_names($element, "qpy-input");
        }
    }

    /**
     * Turns submit and reset buttons into simple buttons without a default action.
     *
     * When multiple questions are shown on the same page, they share a form, so one question must not reset or submit
     * the entire form.
     *
     * @return void
     */
    private function defuse_buttons(): void {
        /** @var DOMElement $element */
        foreach ($this->xpath->query("(//xhtml:input | //xhtml:button)[@type = 'submit' or @type = 'reset']") as $element) {
            $element->setAttribute("type", "button");
        }
    }

    /**
     * Removes elements with `qpy:if-role` attributes if the user matches none of the given roles in this context.
     *
     * The attribute values `teacher`, `proctor`, `scorer` and `developer` are mapped onto Moodle's system as follows:
     * - The user is a teacher if they have the `mod/quiz:viewreports` capability, which includes the archetypes
     *   `manager`, `teacher` and `editingteacher`.
     * - Since Moodle has no concept of proctoring, `proctor` is considered synonymous with `teacher`.
     * - The user is a scorer if they have the `mod/quiz:grade` capability.
     * - The user is a developer if they are a teacher AND debugging is turned on. (As per {@see debugging}.)
     *
     * @throws coding_exception
     */
    private function hide_if_role(): void {
        /** @var DOMAttr $attr */
        foreach (iterator_to_array($this->xpath->query("//@qpy:if-role")) as $attr) {
            $allowedroles = preg_split("/[\s|]+/", $attr->value, -1, PREG_SPLIT_NO_EMPTY);

            $isteacher = has_capability("mod/quiz:viewreports", $this->options->context);
            $isscorer = has_capability("mod/quiz:grade", $this->options->context);
            $isdeveloper = $isteacher && debugging();

            if (
                !(in_array("teacher", $allowedroles) && $isteacher
                || in_array("proctor", $allowedroles) && $isteacher
                || in_array("scorer", $allowedroles) && $isscorer
                || in_array("developer", $allowedroles) && $isdeveloper)
            ) {
                $attr->ownerElement->parentNode->removeChild($attr->ownerElement);
            }
        }
    }

    /**
     * Handles `qpy:format-float`. Uses {@see format_float} and optionally adds thousands separators.
     *
     * @return void
     * @throws coding_exception
     */
    private function format_floats(): void {
        /** @var DOMElement $element */
        foreach (iterator_to_array($this->xpath->query("//qpy:format-float")) as $element) {
            $float = floatval($element->textContent);

            $precision = intval($element->hasAttribute("precision") ? $element->getAttribute("precision") : -1);
            $stripzeroes = $element->hasAttribute("strip-zeros");

            $str = format_float($float, $precision, true, $stripzeroes);

            $thousandssep = $element->getAttribute("thousands-separator");
            if ($thousandssep === "yes") {
                $thousandssep = get_string("thousandssep", "langconfig");
            } else if ($thousandssep === "no") {
                $thousandssep = "";
            }

            if ($thousandssep !== "") {
                $decsep = get_string("decsep", "langconfig");
                $decimalpos = strpos($str, $decsep);
                if ($decimalpos === false) {
                    // No decimal, start at the end of the number.
                    $decimalpos = strlen($str);
                }

                for ($i = $decimalpos - 3; $i >= 1; $i -= 3) {
                    // Insert a thousands separator.
                    $str = substr_replace($str, $thousandssep, $i, 0);
                }
            }

            $element->parentNode->replaceChild(new DOMText($str), $element);
        }
    }

    /**
     * Adds the given class names to the elements `class` attribute if not already present.
     *
     * @param DOMElement $element
     * @param string ...$newclasses
     * @return void
     */
    private function add_class_names(DOMElement $element, string ...$newclasses): void {
        $classarray = [];
        for ($class = strtok($element->getAttribute("class"), " \t\n"); $class; $class = strtok(" \t\n")) {
            $classarray[] = $class;
        }

        foreach ($newclasses as $newclass) {
            if (!in_array($newclass, $classarray)) {
                $classarray[] = $newclass;
            }
        }

        $element->setAttribute("class", implode(" ", $classarray));
    }
}
