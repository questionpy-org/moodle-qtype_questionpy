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
use DOMException;
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
    public const XHTML_NAMESPACE = "http://www.w3.org/1999/xhtml";
    /** @var string XML namespace for our custom things */
    public const QPY_NAMESPACE = "http://questionpy.org/ns/question";

    /** @var DOMDocument $question */
    private DOMDocument $question;

    /** @var array $placeholders */
    private array $placeholders;

    /** @var question_metadata|null $metadata */
    private ?question_metadata $metadata = null;

    /** @var int seed for {@see mt_srand}, to make shuffles deterministic */
    private int $mtseed;

    /**
     * Parses the given XML and initializes a new {@see question_ui_renderer} instance.
     *
     * @param string $xml XML as returned by the QPy Server
     * @param array $placeholders string to string mapping of placeholder names to the values
     * @param int $mtseed the seed to use ({@see mt_srand()}) to make `qpy:shuffle-contents` deterministic
     */
    public function __construct(string $xml, array $placeholders, int $mtseed) {
        $this->question = new DOMDocument();
        $this->question->loadXML($xml);
        $this->question->normalizeDocument();

        $this->placeholders = $placeholders;
        $this->mtseed = $mtseed;
    }

    /**
     * Renders the contents of the `qpy:formulation` element. Throws an exception if there is none.
     *
     * @param question_attempt $qa
     * @param question_display_options $options
     * @return string
     * @throws DOMException
     * @throws coding_exception
     */
    public function render_formulation(question_attempt $qa, question_display_options $options): string {
        $elements = $this->question->getElementsByTagNameNS(self::QPY_NAMESPACE, "formulation");
        if ($elements->length < 1) {
            // TODO: Helpful exception.
            throw new coding_exception("Question UI XML contains no 'qpy:formulation' element");
        }

        return $this->render_part($elements->item(0), $qa, $options);
    }

    /**
     * Renders the contents of the `qpy:general-feedback` element or returns null if there is none.
     *
     * @param question_attempt $qa
     * @param question_display_options $options
     * @return string|null
     * @throws DOMException
     * @throws coding_exception
     */
    public function render_general_feedback(question_attempt $qa, question_display_options $options): ?string {
        $elements = $this->question->getElementsByTagNameNS(self::QPY_NAMESPACE, "general-feedback");
        if ($elements->length < 1) {
            return null;
        }

        return $this->render_part($elements->item(0), $qa, $options);
    }

    /**
     * Renders the contents of the `qpy:specific-feedback` element or returns null if there is none.
     *
     * @param question_attempt $qa
     * @param question_display_options $options
     * @return string|null
     * @throws DOMException
     * @throws coding_exception
     */
    public function render_specific_feedback(question_attempt $qa, question_display_options $options): ?string {
        $elements = $this->question->getElementsByTagNameNS(self::QPY_NAMESPACE, "specific-feedback");
        if ($elements->length < 1) {
            return null;
        }

        return $this->render_part($elements->item(0), $qa, $options);
    }

    /**
     * Renders the contents of the `qpy:right-answer` element or returns null if there is none.
     *
     * @param question_attempt $qa
     * @param question_display_options $options
     * @return string|null
     * @throws DOMException
     * @throws coding_exception
     */
    public function render_right_answer(question_attempt $qa, question_display_options $options): ?string {
        $elements = $this->question->getElementsByTagNameNS(self::QPY_NAMESPACE, "right-answer");
        if ($elements->length < 1) {
            return null;
        }

        return $this->render_part($elements->item(0), $qa, $options);
    }

    /**
     * Extracts metadata from the question UI.
     *
     * @return question_metadata
     */
    public function get_metadata(): question_metadata {
        if (!$this->metadata) {
            $xpath = new DOMXPath($this->question);
            $xpath->registerNamespace("xhtml", self::XHTML_NAMESPACE);
            $xpath->registerNamespace("qpy", self::QPY_NAMESPACE);

            $this->metadata = new question_metadata();
            /** @var DOMAttr $attr */
            foreach ($xpath->query("/qpy:question/qpy:formulation//@qpy:correct-response") as $attr) {
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
            foreach ($xpath->query(
                "/qpy:question/qpy:formulation
                //*[self::xhtml:input or self::xhtml:select or self::xhtml:textarea or self::xhtml:button]"
            ) as $element) {
                $name = $element->getAttribute("name");
                if ($name) {
                    $this->metadata->expecteddata[$name] = PARAM_RAW;

                    if ($element->hasAttribute("required")) {
                        $this->metadata->requiredfields[] = $name;
                    }
                }
            }
        }

        return $this->metadata;
    }

    /**
     * Applies transformations to the descendants of a given node and returns the resulting HTML.
     *
     * @param DOMNode $part
     * @param question_attempt $qa
     * @param question_display_options $options
     * @return string
     * @throws DOMException
     * @throws coding_exception
     */
    private function render_part(DOMNode $part, question_attempt $qa, question_display_options $options): string {
        $newdoc = new DOMDocument();
        $div = $newdoc->appendChild($newdoc->createElementNS(self::XHTML_NAMESPACE, "div"));
        foreach ($part->childNodes as $child) {
            $div->appendChild($newdoc->importNode($child, true));
        }

        $xpath = new DOMXPath($newdoc);
        $xpath->registerNamespace("xhtml", self::XHTML_NAMESPACE);
        $xpath->registerNamespace("qpy", self::QPY_NAMESPACE);

        $nextseed = mt_rand();
        mt_srand($this->mtseed);
        try {
            $this->resolve_placeholders($xpath);
            $this->hide_unwanted_feedback($xpath, $options);
            $this->hide_if_role($xpath, $options);
            $this->set_input_values_and_readonly($xpath, $qa, $options);
            $this->soften_validation($xpath);
            $this->defuse_buttons($xpath);
            $this->shuffle_contents($xpath);
            $this->add_styles($xpath);
            $this->format_floats($xpath);
            $this->mangle_ids_and_names($xpath, $qa);
            $this->clean_up($xpath);
        } finally {
            // I'm not sure whether it is strictly necessary to reset the PRNG seed here, but it feels safer.
            // Resetting it to its original state would be ideal, but that doesn't seem to be possible.
            mt_srand($nextseed);
        }

        return $newdoc->saveHTML();
    }

    /**
     * Hides elements marked with `qpy:feedback` if the type of feedback is disabled in {@see question_display_options}.
     *
     * @param DOMXPath $xpath
     * @param question_display_options $options
     * @return void
     */
    private function hide_unwanted_feedback(\DOMXPath $xpath, question_display_options $options): void {
        /** @var DOMElement $element */
        foreach (iterator_to_array($xpath->query("//*[@qpy:feedback]")) as $element) {
            $feedback = $element->getAttributeNS(self::QPY_NAMESPACE, "feedback");

            if (($feedback == "general" && !$options->generalfeedback)
                || ($feedback == "specific" && !$options->feedback)) {
                $element->parentNode->removeChild($element);
            }
        }
    }

    /**
     * Shuffles children of elements marked with `qpy:shuffle-contents`.
     *
     * Also replaces `qpy:shuffled-index` elements which are descendants of each child with the new index of the child.
     *
     * @param DOMXPath $xpath
     * @throws coding_exception
     */
    private function shuffle_contents(\DOMXPath $xpath): void {
        /** @var DOMElement $element */
        foreach (iterator_to_array($xpath->query("//*[@qpy:shuffle-contents]")) as $element) {
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
                    $this->replace_shuffled_indices($xpath, $child, $i++);
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
     * @param DOMXPath $xpath
     * @param DOMNode $element
     * @param int $index
     * @throws coding_exception
     */
    private function replace_shuffled_indices(DOMXPath $xpath, DOMNode $element, int $index): void {
        /** @var DOMElement $indexelement */
        foreach (iterator_to_array($xpath->query(".//qpy:shuffled-index", $element)) as $indexelement) {
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
     * @param DOMXPath $xpath
     * @param question_attempt $qa
     * @return void
     */
    private function mangle_ids_and_names(\DOMXPath $xpath, question_attempt $qa): void {
        /** @var DOMAttr $attr */
        foreach ($xpath->query("
                //xhtml:*/@id | //xhtml:label/@for | //xhtml:output/@for | //xhtml:input/@list |
                (//xhtml:button | //xhtml:form | //xhtml:fieldset | //xhtml:iframe | //xhtml:input | //xhtml:object |
                 //xhtml:output | //xhtml:select | //xhtml:textarea | //xhtml:map)/@name |
                //xhtml:img/@usemap
                ") as $attr) {
            $original = $attr->value;
            if ($attr->name === "usemap" && utils::str_starts_with($original, "#")) {
                // See https://developer.mozilla.org/en-US/docs/Web/API/HTMLImageElement/useMap.
                $attr->value = "#" . $qa->get_qt_field_name(substr($original, 1));
            } else {
                $attr->value = $qa->get_qt_field_name($original);
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
     * @param DOMXPath $xpath
     * @param question_attempt $qa
     * @param question_display_options $options
     * @return void
     */
    private function set_input_values_and_readonly(DOMXPath                 $xpath, question_attempt $qa,
                                                   question_display_options $options): void {
        /** @var DOMElement $element */
        foreach ($xpath->query("//xhtml:button | //xhtml:input | //xhtml:select | //xhtml:textarea") as $element) {
            if ($options->readonly) {
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
            $lastvalue = $qa->get_last_qt_var($name);
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
     * @param DOMXPath $xpath
     * @return void
     */
    private function clean_up(DOMXPath $xpath): void {
        /** @var DOMNode|DOMNameSpaceNode $node */
        foreach (iterator_to_array($xpath->query("//qpy:* | //@qpy:* | //comment() | //namespace::*")) as $node) {
            if ($node instanceof DOMAttr || $node instanceof DOMNameSpaceNode) {
                $node->parentNode->removeAttributeNS($node->namespaceURI, $node->localName);
            } else {
                $node->parentNode->removeChild($node);
            }
        }
    }

    /**
     * Replace placeholder PIs such as `<?p my_key plain?>` with the appropriate value from `$this->placeholders`.
     *
     * Since QPy transformations should not be applied to the content of the placeholders, this method should be called
     * last.
     *
     * @param DOMXPath $xpath
     * @return void
     */
    private function resolve_placeholders(DOMXPath $xpath): void {
        /** @var DOMProcessingInstruction $pi */
        foreach (iterator_to_array($xpath->query("//processing-instruction('p')")) as $pi) {
            $parts = preg_split("/\s+/", trim($pi->data));
            $key = $parts[0];
            $cleanoption = $parts[1] ?? "clean";

            if (!isset($this->placeholders[$key])) {
                $pi->parentNode->removeChild($pi);
            } else {
                $rawvalue = $this->placeholders[$key];
                if (strcasecmp($cleanoption, "clean") == 0) {
                    // Allow (X)HTML, but clean using Moodle's clean_text to prevent XSS.
                    $element = $xpath->document->createDocumentFragment();
                    $element->appendXML(clean_text($rawvalue));
                } else if (strcasecmp($cleanoption, "noclean") == 0) {
                    $element = $xpath->document->createDocumentFragment();
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
     * @param DOMXPath $xpath
     * @return void
     */
    private function soften_validation(DOMXPath $xpath): void {
        /** @var DOMElement $element */
        foreach ($xpath->query("//xhtml:input[@pattern]") as $element) {
            $pattern = $element->getAttribute("pattern");
            $element->removeAttribute("pattern");
            $element->setAttribute("data-qpy_pattern", $pattern);
        }

        foreach ($xpath->query("(//xhtml:input | //xhtml:select | //xhtml:textarea)[@required]") as $element) {
            $element->removeAttribute("required");
            $element->setAttribute("data-qpy_required", "data-qpy_required");
            $element->setAttribute("aria-required", "true");
        }

        foreach ($xpath->query("(//xhtml:input | //xhtml:textarea)[@minlength]") as $element) {
            $minlength = $element->getAttribute("minlength");
            $element->removeAttribute("minlength");
            $element->setAttribute("data-qpy_minlength", $minlength);
        }

        foreach ($xpath->query("(//xhtml:input | //xhtml:textarea)[@maxlength]") as $element) {
            $maxlength = $element->getAttribute("maxlength");
            $element->removeAttribute("maxlength");
            $element->setAttribute("data-qpy_maxlength", $maxlength);
        }

        foreach ($xpath->query("//xhtml:input[@min]") as $element) {
            $min = $element->getAttribute("min");
            $element->removeAttribute("min");
            $element->setAttribute("data-qpy_min", $min);
            $element->setAttribute("aria-valuemin", $min);
        }

        foreach ($xpath->query("//xhtml:input[@max]") as $element) {
            $max = $element->getAttribute("max");
            $element->removeAttribute("max");
            $element->setAttribute("data-qpy_max", $max);
            $element->setAttribute("aria-valuemax", $max);
        }
    }

    /**
     * Adds CSS classes to various elements to style them similarly to Moodle's own question types.
     *
     * @param DOMXPath $xpath
     * @return void
     */
    private function add_styles(DOMXPath $xpath): void {
        /** @var DOMElement $element */
        foreach ($xpath->query("
                //xhtml:input[@type != 'checkbox' and @type != 'radio' and
                              @type != 'button' and @type != 'submit' and @type != 'reset']
                | //xhtml:select | //xhtml:textarea
                ") as $element) {
            $this->add_class_names($element, "form-control", "qpy-input");
        }

        foreach ($xpath->query("//xhtml:input[@type = 'button' or @type = 'submit' or @type = 'reset']
                                | //xhtml:button") as $element) {
            $this->add_class_names($element, "btn", "btn-primary", "qpy-input");
        }

        foreach ($xpath->query("//xhtml:input[@type = 'checkbox' or @type = 'radio']") as $element) {
            $this->add_class_names($element, "qpy-input");
        }
    }

    /**
     * Turns submit and reset buttons into simple buttons without a default action.
     *
     * When multiple questions are shown on the same page, they share a form, so one question must not reset or submit
     * the entire form.
     *
     * @param DOMXPath $xpath
     * @return void
     */
    private function defuse_buttons(DOMXPath $xpath): void {
        /** @var DOMElement $element */
        foreach ($xpath->query("(//xhtml:input | //xhtml:button)[@type = 'submit' or @type = 'reset']") as $element) {
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
     * @param DOMXPath $xpath
     * @param question_display_options $options
     * @throws coding_exception
     */
    public function hide_if_role(DOMXPath $xpath, question_display_options $options): void {
        /** @var DOMAttr $attr */
        foreach (iterator_to_array($xpath->query("//@qpy:if-role")) as $attr) {
            $allowedroles = preg_split("/[\s|]+/", $attr->value, -1, PREG_SPLIT_NO_EMPTY);

            $isteacher = has_capability("mod/quiz:viewreports", $options->context);
            $isscorer = has_capability("mod/quiz:grade", $options->context);
            $isdeveloper = $isteacher && debugging();

            if (!(in_array("teacher", $allowedroles) && $isteacher
                || in_array("proctor", $allowedroles) && $isteacher
                || in_array("scorer", $allowedroles) && $isscorer
                || in_array("developer", $allowedroles) && $isdeveloper)) {
                $attr->ownerElement->parentNode->removeChild($attr->ownerElement);
            }
        }
    }

    /**
     * Handles `qpy:format-float`. Uses {@see format_float} and optionally adds thousands separators.
     *
     * @param DOMXPath $xpath
     * @return void
     * @throws coding_exception
     */
    private function format_floats(DOMXPath $xpath): void {
        /** @var DOMElement $element */
        foreach (iterator_to_array($xpath->query("//qpy:format-float")) as $element) {
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
