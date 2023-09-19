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
use DOMDocumentFragment;
use DOMElement;
use DOMException;
use DOMNameSpaceNode;
use DOMNode;
use DOMProcessingInstruction;
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

    /** @var array $parameters */
    private array $parameters;

    /** @var question_metadata|null $metadata */
    private ?question_metadata $metadata = null;

    /** @var int seed for {@see mt_srand}, to make shuffles deterministic */
    private int $mtseed;

    /**
     * Parses the given XML and initializes a new {@see question_ui_renderer} instance.
     *
     * @param string $xml XML as returned by the QPy Server
     * @param array $parameters mapping of parameter names to the strings they should be replaced with in the content
     * @param int $mtseed the seed to use ({@see mt_srand()}) to make `qpy:shuffle-contents` deterministic
     */
    public function __construct(string $xml, array $parameters, int $mtseed) {
        $this->question = new DOMDocument();
        $this->question->loadXML($xml);
        $this->question->normalizeDocument();

        $this->parameters = $parameters;
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
     * @param question_attempt $qa
     * @return string|null
     * @throws DOMException
     * @throws coding_exception
     */
    public function render_general_feedback(question_attempt $qa): ?string {
        $elements = $this->question->getElementsByTagNameNS(self::QPY_NAMESPACE, "general-feedback");
        if ($elements->length < 1) {
            return null;
        }

        return $this->render_part($elements->item(0), $qa);
    }

    /**
     * Renders the contents of the `qpy:specific-feedback` element or returns null if there is none.
     * @param question_attempt $qa
     * @return string|null
     * @throws DOMException
     * @throws coding_exception
     */
    public function render_specific_feedback(question_attempt $qa): ?string {
        $elements = $this->question->getElementsByTagNameNS(self::QPY_NAMESPACE, "specific-feedback");
        if ($elements->length < 1) {
            return null;
        }

        return $this->render_part($elements->item(0), $qa);
    }


    /**
     * Renders the contents of the `qpy:right-answer` element or returns null if there is none.
     * @param question_attempt $qa
     * @return string|null
     * @throws DOMException
     * @throws coding_exception
     */
    public function render_right_answer(question_attempt $qa): ?string {
        $elements = $this->question->getElementsByTagNameNS(self::QPY_NAMESPACE, "right-answer");
        if ($elements->length < 1) {
            return null;
        }

        return $this->render_part($elements->item(0), $qa);
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
     * @param question_display_options|null $options
     * @return string
     * @throws DOMException
     * @throws coding_exception
     */
    private function render_part(DOMNode $part, question_attempt $qa, ?question_display_options $options = null): string {
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
            $this->hide_unwanted_feedback($xpath, $options);
            $this->set_input_values_and_readonly($xpath, $qa, $options);
            $this->soften_validation($xpath);
            $this->shuffle_contents($xpath);
            $this->add_styles($xpath);
            $this->mangle_ids_and_names($xpath, $qa);
            $this->clean_up($xpath);
            $this->resolve_placeholders($xpath);
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
     * @param question_display_options|null $options
     * @return void
     */
    private function hide_unwanted_feedback(\DOMXPath $xpath, ?question_display_options $options = null): void {
        /** @var DOMElement $element */
        foreach ($xpath->query("//*[@qpy:feedback]") as $element) {
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
        foreach ($xpath->query("//*[@qpy:shuffle-contents]") as $element) {
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
        $indexelements = $xpath->query(".//qpy:shuffled-index", $element);
        /** @var DOMElement $indexelement */
        foreach ($indexelements as $indexelement) {
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

            $indexelement->parentNode->replaceChild(new \DOMText($indexstr), $indexelement);
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
     * @param question_display_options|null $options
     * @return void
     */
    private function set_input_values_and_readonly(DOMXPath                  $xpath, question_attempt $qa,
                                                   ?question_display_options $options = null): void {
        /** @var DOMElement $element */
        foreach ($xpath->query("//xhtml:button | //xhtml:input | //xhtml:select | //xhtml:textarea") as $element) {
            if ($options && $options->readonly) {
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
        foreach ($xpath->query("//qpy:* | //@qpy:* | //comment() | //namespace::*") as $node) {
            if ($node instanceof DOMAttr || $node instanceof DOMNameSpaceNode) {
                $node->parentNode->removeAttributeNS($node->namespaceURI, $node->localName);
            } else {
                $node->parentNode->removeChild($node);
            }
        }
    }

    /**
     * Replace placeholder PIs such as `<?p my_key?>` with the appropriate value from `$parameters`.
     *
     * Since QPy transformations should not be applied to the content of parameters, this method should be called last.
     *
     * @param DOMXPath $xpath
     * @return void
     */
    private function resolve_placeholders(DOMXPath $xpath): void {
        /** @var DOMProcessingInstruction $pi */
        foreach ($xpath->query("//processing-instruction('p')") as $pi) {
            $key = trim($pi->data);
            $value = utils::array_get_nested($this->parameters, $key);

            if (is_null($value)) {
                $pi->parentNode->removeChild($pi);
            } else {
                /** @var DOMDocumentFragment $frag */
                $frag = $xpath->document->createDocumentFragment();
                /* TODO: This always interprets the parameter value as XHTML.
                         While supporting markup here is important, perhaps we should allow placeholders to opt out? */
                $frag->appendXML($value);
                $pi->parentNode->replaceChild($frag, $pi);
            }
        }
    }

    /**
     * Replaces the HTML attributes `pattern`, `required`, `minlength`, `maxlength` so that submission is not prevented.
     *
     * The standard attributes are replaced with `data-qpy_X`, which are then evaluated in JS.
     * Ideally we'd also want to handle min and max here, but their evaluation in JS would be quite complicated.
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
