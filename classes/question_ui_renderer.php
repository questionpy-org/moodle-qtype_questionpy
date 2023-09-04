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
use DOMNode;
use DOMXPath;
use qtype_questionpy\question_ui\cleanup_transformation;
use qtype_questionpy\question_ui\feedback_transformation;
use qtype_questionpy\question_ui\input_transformation;
use qtype_questionpy\question_ui\question_ui_transformation;
use qtype_questionpy\question_ui\shuffle_transformation;
use qtype_questionpy\question_ui\verbatim_transformation;
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

    /** @var string[] class names of {@see question_ui_transformation}s to apply, in order */
    private const TRANSFORMATIONS = [
        feedback_transformation::class,
        shuffle_transformation::class,
        input_transformation::class,
        verbatim_transformation::class,
        cleanup_transformation::class
    ];

    /** @var DOMDocument $question */
    public DOMDocument $question;

    /** @var question_metadata|null $metadata */
    private ?question_metadata $metadata = null;

    /** @var int seed for {@see mt_srand}, to make shuffles deterministic */
    private int $mtseed;

    /**
     * Parses the given XML and initializes a new {@see question_ui_renderer} instance.
     *
     * @param string $xml XML as returned by the QPy Server
     * @param int $mtseed the seed to use ({@see mt_srand()}) to make `qpy:shuffle-contents` deterministic
     */
    public function __construct(string $xml, int $mtseed) {
        $this->question = new DOMDocument();
        $this->question->loadXML($xml);
        $this->question->normalizeDocument();


        $this->mtseed = $mtseed;
    }

    /**
     * Renders the contents of the `qpy:formulation` element. Throws an exception if there is none.
     *
     * @param question_attempt $qa
     * @param question_display_options $options
     * @return string
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
            foreach (self::TRANSFORMATIONS as $class) {
                /** @var question_ui_transformation $transformation */
                $transformation = new $class($xpath, $qa, $options);
                $nodes = $transformation->collect();
                foreach ($nodes as $node) {
                    $transformation->transform_node($node);
                }
            }
        } finally {
            // I'm not sure whether it is strictly necessary to reset the PRNG seed here, but it feels safer.
            // Resetting it to its original state would be ideal, but that doesn't seem to be possible.
            mt_srand($nextseed);
        }

        return $newdoc->saveHTML();
    }
}
