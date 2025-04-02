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
use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNameSpaceNode;
use DOMNode;
use DOMProcessingInstruction;
use DOMText;
use DOMXPath;
use qtype_questionpy\constants;
use qtype_questionpy\utils;
use qtype_questionpy_question;
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
    /** @var string[]|null $roles names of roles that the current user has (use {@see get_user_roles()} to get the roles) */
    private ?array $roles = null;

    /** @var string $html resulting rendered html */
    public string $html;

    /** @var invalid_option_warning[] $warnings warnings emitted during rendering */
    public array $warnings;

    /**
     * @var string[] $unmappableduplicatefieldnames contains duplicate input field names that cannot be mapped with certainty to
     * their corresponding fields
     */
    public array $unmappableduplicatefieldnames = [];

    /**
     * @var string[] $mappableduplicatefieldnames contains duplicate input field names that can be mapped with certainty to their
     * their corresponding fields
     */
    public array $mappableduplicatefieldnames = [];

    /**
     * Private constructor. Use {@see question_ui_renderer::render()}.
     *
     * @param DOMDocument $xml XML document to operate on
     * @param DOMXPath $xpath
     * @param question_display_options $options
     */
    private function __construct(
        /** @var DOMDocument $xml */
        private readonly DOMDocument $xml,
        /** @var DOMXPath $xpath */
        private readonly DOMXPath $xpath,
        /** @var question_display_options $options */
        private readonly question_display_options $options
    ) {
    }

    /**
     * Renders the given QuestionPy XHTML to HTML.
     *
     * @param string $xml XML as returned by the QPy Server
     * @param array $placeholders string to string mapping of placeholder names to the values
     * @param question_display_options $options
     * @param question_attempt $attempt
     * @return question_ui_renderer object containing {@see question_ui_renderer::$html rendered html} and
     *                              {@see question_ui_renderer::$warnings emitted warnings}.
     * @throws coding_exception
     */
    public static function render(string $xml, array $placeholders, question_display_options $options,
                                  question_attempt $attempt): static {
        $xml = static::replace_qpy_urls($xml, $attempt);

        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = false;
        $doc->loadXML($xml);
        $doc->normalizeDocument();

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('xhtml', constants::NAMESPACE_XHTML);
        $xpath->registerNamespace('qpy', constants::NAMESPACE_QPY);

        $renderer = new static($doc, $xpath, $options);
        $renderer->populate_duplicate_field_names();

        $nextseed = mt_rand();
        $id = $attempt->get_database_id();
        if ($id === null) {
            throw new coding_exception('question_attempt does not have an id');
        }

        mt_srand($id);
        try {
            // Handle our custom elements and attributes.
            $renderer->hide_unwanted_feedback();
            $renderer->hide_if_role();
            $renderer->shuffle_contents();
            $renderer->format_floats();

            $availableoptions = $renderer->extract_available_options();

            // Remove all unhandled custom elements, attributes, comments, and non-default xmlns declarations.
            $renderer->clean_up();

            // Modify standard HTML.
            $renderer->set_input_values_and_readonly($attempt);
            $renderer->soften_validation();
            $renderer->defuse_buttons();

            $renderer->add_styles();

            // We don't want to support QPy elements (and attributes, etc.) in placeholder expansions, so we resolve
            // them after replacing QPy elements.
            $renderer->resolve_placeholders($placeholders);
        } finally {
            // I'm not sure whether it is strictly necessary to reset the PRNG seed here, but it feels safer.
            // Resetting it to its original state would be ideal, but that doesn't seem to be possible.
            mt_srand($nextseed);
        }

        $warnings = $renderer->check_for_and_preserve_unknown_options($availableoptions, $attempt);
        $renderer->html = $renderer->xml->saveHTML();
        $renderer->warnings = $warnings;
        return $renderer;
    }

    /**
     * Hides elements marked with `qpy:feedback` if the type of feedback is disabled in {@see question_display_options}
     * or if it does not exist.
     *
     * @return void
     */
    private function hide_unwanted_feedback(): void {
        /** @var DOMElement $element */
        foreach (iterator_to_array($this->xpath->query('//*[@qpy:feedback]')) as $element) {
            $feedback = $element->getAttributeNS(constants::NAMESPACE_QPY, 'feedback');

            if (
                !(
                    ($feedback == 'general' && $this->options->generalfeedback)
                    || ($feedback == 'specific' && $this->options->feedback)
                )
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
        foreach (iterator_to_array($this->xpath->query('//*[@qpy:shuffle-contents]')) as $element) {
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
                    $this->replace_shuffled_indices($newelement, $child, $i++);
                } else {
                    $newelement->appendChild($child);
                }
            }

            $newelement->removeAttributeNS(constants::NAMESPACE_QPY, 'shuffle-contents');
            $element->parentNode->replaceChild($newelement, $element);
        }
    }

    /**
     * Among the descendants of `$element`, finds `qpy:shuffled-index` elements and replaces them with `$index`.
     *
     * @param DOMElement $container the element which has the currently handled `qpy:shuffle-contents` attribute
     * @param DOMElement $element the shuffled element whose `qpy:shuffled-index` descendants should be replaced
     * @param int $index
     * @throws coding_exception
     */
    private function replace_shuffled_indices(DOMElement $container, DOMElement $element, int $index): void {
        /** @var DOMElement $indexelement */
        foreach (iterator_to_array($this->xpath->query('.//qpy:shuffled-index', $element)) as $indexelement) {
            // phpcs:ignore Squiz.ControlStructures.ForLoopDeclaration.SpacingAfterSecond
            for (
                $ancestor = $indexelement->parentNode; $ancestor !== null && $ancestor !== $container;
                $ancestor = $ancestor->parentNode
            ) {
                assert($ancestor instanceof DOMElement);
                if ($ancestor->hasAttributeNS(constants::NAMESPACE_QPY, 'shuffle-contents')) {
                    // The index element is in a nested shuffle-contents.
                    // We want it to be replaced with the index of the inner shuffle, so we ignore it for now.
                    continue 2;
                }
            }

            $format = $indexelement->getAttribute('format') ?: '123';

            switch ($format) {
                default:
                case '123':
                    $indexstr = strval($index);
                    break;
                case 'abc':
                    $indexstr = strtolower(\question_utils::int_to_letter($index));
                    break;
                case 'ABC':
                    $indexstr = \question_utils::int_to_letter($index);
                    break;
                case 'iii':
                    $indexstr = \question_utils::int_to_roman($index);
                    break;
                case 'III':
                    $indexstr = strtoupper(\question_utils::int_to_roman($index));
                    break;
            }

            $indexelement->parentNode->replaceChild(new DOMText($indexstr), $indexelement);
        }
    }

    /**
     * Transforms input(-like) elements.
     *
     * - If {@see question_display_options::$readonly} is set, the input is disabled.
     * - If a value was saved for the input in a previous step, the latest value is added to the HTML.
     *
     * @param question_attempt $attempt
     * @return void
     * @throws coding_exception
     */
    private function set_input_values_and_readonly(question_attempt $attempt): void {
        $lastresponse = utils::get_qpy_response($attempt);

        /** @var DOMElement $element */
        foreach ($this->xpath->query('//xhtml:button | //xhtml:input | //xhtml:select | //xhtml:textarea') as $element) {
            if ($this->options->readonly) {
                $element->setAttribute('disabled', 'disabled');
            }

            $name = $element->getAttribute('name');
            if (!$name) {
                continue;
            }

            // Get the last saved value.
            $lastvalue = $lastresponse->{$name} ?? null;
            if (is_null($lastvalue)) {
                continue;
            }

            if (in_array($name, $this->unmappableduplicatefieldnames)) {
                // We can not map value(s) of elements with this name with certainty to the input fields.
                continue;
            }

            if ($element->tagName == 'input') {
                $type = $element->getAttribute('type') ?: 'text';
            } else {
                $type = $element->tagName;
            }

            $hasmultiplevalues = in_array($name, $this->mappableduplicatefieldnames);
            if ($hasmultiplevalues && is_array($lastvalue)) {
                $this->set_input_values_for_duplicate_name_fields($element, $type, $lastvalue);
            } else {
                $this->set_input_values_for_single_name_fields($element, $type, $lastvalue);
            }
        }
    }

    /**
     * Set input values for fields whose last value is a list of values.
     *
     * @param DOMElement $element
     * @param string $type
     * @param array $lastvalue
     * @return void
     * @throws coding_exception
     */
    private function set_input_values_for_duplicate_name_fields(DOMElement $element, string $type, array $lastvalue): void {
        if ($type === 'checkbox') {
            $value = $element->hasAttribute('value') ? $element->getAttribute('value') : 'on';
            if (in_array($value, $lastvalue)) {
                $element->setAttribute('checked', 'checked');
            } else {
                $element->removeAttribute('checked');
            }
        } else if ($type === 'select') {
            if (!$element->hasAttribute('multiple')) {
                // This should never happen.
                return;
            }

            dom_utils::set_select_values($element, $lastvalue);
        }
    }

    /**
     * Set input values for fields whose last value is a single string.
     *
     * @param DOMElement $element
     * @param string $type
     * @param string $lastvalue
     * @return void
     * @throws coding_exception
     */
    private function set_input_values_for_single_name_fields(DOMElement $element, string $type, string $lastvalue): void {
        if ($type === 'checkbox' || $type === 'radio') {
            // FIXME: Unchecked checkboxes send nothing, so we have no way of distinguishing an explicitly
            // unchecked checkbox from a checkbox which was not submitted (e.g. because it wasn't shown).
            // As it stands, a default-checked but explicitly unchecked checkbox will be checked again on next
            // view.
            $shouldbechecked = $element->hasAttribute('value')
                ? $element->getAttribute('value') === $lastvalue
                : $lastvalue === 'on';
            if ($shouldbechecked) {
                $element->setAttribute('checked', 'checked');
            } else {
                $element->removeAttribute('checked');
            }
        } else if ($type === 'select') {
            // Find the appropriate option and mark it as selected.
            dom_utils::set_select_values($element, [$lastvalue]);
        } else if ($type === 'textarea') {
            $element->textContent = $lastvalue;
        } else if ($type !== 'button' && $type !== 'submit') {
            $element->setAttribute('value', $lastvalue);
        }
    }

    /**
     * Removes remaining QuestionPy elements and attributes as well as comments and xmlns declarations.
     *
     * @return void
     */
    private function clean_up(): void {
        /** @var DOMNode|DOMNameSpaceNode $node */
        foreach (iterator_to_array($this->xpath->query('//qpy:* | //@qpy:* | //comment() | //namespace::*')) as $node) {
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
     * near the end (after {@see clean_up()}).
     *
     * @param array $placeholders
     * @return void
     */
    private function resolve_placeholders(array $placeholders): void {
        /** @var DOMProcessingInstruction $pi */
        foreach (iterator_to_array($this->xpath->query("//processing-instruction('p')")) as $pi) {
            $parts = preg_split('/\s+/', trim($pi->data));
            $key = $parts[0];
            $cleanoption = $parts[1] ?? 'clean';

            if (!isset($placeholders[$key])) {
                // No value for this placeholder, so we just remove the PI.
                $pi->parentNode->removeChild($pi);
                continue;
            }

            $rawvalue = $placeholders[$key];
            if (strtolower($cleanoption) === 'clean') {
                // Allow HTML, but clean using Moodle's clean_text to prevent XSS.
                $element = dom_utils::html_to_fragment($this->xml, clean_text($rawvalue));
                if (!$element) {
                    debugging('clean_text produced invalid HTML');
                    // Replace with empty fragment so we just remove the PI.
                    $element = $this->xml->createDocumentFragment();
                }
            } else if (strtolower($cleanoption) === 'noclean') {
                $element = dom_utils::html_to_fragment($this->xml, $rawvalue, LIBXML_NOERROR);
            } else {
                if (strtolower($cleanoption) !== 'plain') {
                    debugging("Unrecognized placeholder cleaning option: '$cleanoption', using 'plain'");
                }
                // Treat the value as plain text and don't allow any kind of markup.
                // Since we're adding a text node, the DOM handles escaping for us.
                $element = new DOMText($rawvalue);
            }
            $pi->parentNode->replaceChild($element, $pi);
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
        foreach ($this->xpath->query('//xhtml:input[@pattern]') as $element) {
            $pattern = $element->getAttribute('pattern');
            $element->removeAttribute('pattern');
            $element->setAttribute('data-qpy_pattern', $pattern);
        }

        foreach ($this->xpath->query('(//xhtml:input | //xhtml:select | //xhtml:textarea)[@required]') as $element) {
            $element->removeAttribute('required');
            $element->setAttribute('data-qpy_required', 'data-qpy_required');
            $element->setAttribute('aria-required', 'true');
        }

        foreach ($this->xpath->query('(//xhtml:input | //xhtml:textarea)[@minlength]') as $element) {
            $minlength = $element->getAttribute('minlength');
            $element->removeAttribute('minlength');
            $element->setAttribute('data-qpy_minlength', $minlength);
        }

        foreach ($this->xpath->query('(//xhtml:input | //xhtml:textarea)[@maxlength]') as $element) {
            $maxlength = $element->getAttribute('maxlength');
            $element->removeAttribute('maxlength');
            $element->setAttribute('data-qpy_maxlength', $maxlength);
        }

        foreach ($this->xpath->query('//xhtml:input[@min]') as $element) {
            $min = $element->getAttribute('min');
            $element->removeAttribute('min');
            $element->setAttribute('data-qpy_min', $min);
            $element->setAttribute('aria-valuemin', $min);
        }

        foreach ($this->xpath->query('//xhtml:input[@max]') as $element) {
            $max = $element->getAttribute('max');
            $element->removeAttribute('max');
            $element->setAttribute('data-qpy_max', $max);
            $element->setAttribute('aria-valuemax', $max);
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
            dom_utils::add_class_names($element, 'form-control', 'qpy-input');
        }

        foreach (
            $this->xpath->query("//xhtml:input[@type = 'button' or @type = 'submit' or @type = 'reset']
                                | //xhtml:button") as $element
        ) {
            dom_utils::add_class_names($element, 'btn', 'btn-primary', 'qpy-input');
        }

        foreach ($this->xpath->query("//xhtml:input[@type = 'checkbox' or @type = 'radio']") as $element) {
            dom_utils::add_class_names($element, 'qpy-input');
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
            $element->setAttribute('type', 'button');
        }
    }

    /**
     * Removes elements with `qpy:if-role` attributes if the user matches none of the given roles in this context.
     *
     * @throws coding_exception
     */
    private function hide_if_role(): void {
        /** @var DOMAttr $attr */
        foreach (iterator_to_array($this->xpath->query('//@qpy:if-role')) as $attr) {
            $allowedroles = preg_split('/[\s|]+/', $attr->value, -1, PREG_SPLIT_NO_EMPTY);
            $hasroles = $this->get_user_roles();

            if (!array_intersect($allowedroles, $hasroles)) {
                $attr->ownerElement->parentNode->removeChild($attr->ownerElement);
            }
        }
    }

    /**
     * Get the questionpy role names that the user has.
     *
     *  - The user is a teacher if they have the `mod/quiz:viewreports` capability, which includes the archetypes
     *    `manager`, `teacher` and `editingteacher`.
     *  - Since Moodle has no concept of proctoring, `proctor` is considered synonymous with `teacher`.
     *  - The user is a scorer if they have the `mod/quiz:grade` capability.
     *  - The user is a developer if they are a teacher AND debugging is turned on. (As per {@see debugging}.)
     *
     * @return string[] roles (`teacher`, `proctor`, `scorer` and `developer`)
     * @throws coding_exception
     */
    public function get_user_roles(): array {
        if ($this->roles !== null) {
            return $this->roles;
        }

        $roles = [];
        if (has_capability('mod/quiz:viewreports', $this->options->context)) {
            $roles[] = 'teacher';
            $roles[] = 'proctor';

            if (debugging()) {
                $roles[] = 'developer';
            }
        }

        if (has_capability('mod/quiz:grade', $this->options->context)) {
            $roles[] = 'scorer';
        }

        $this->roles = $roles;
        return $roles;
    }

    /**
     * Handles `qpy:format-float`. Uses {@see format_float} and optionally adds thousands separators.
     *
     * @return void
     * @throws coding_exception
     */
    private function format_floats(): void {
        /** @var DOMElement $element */
        foreach (iterator_to_array($this->xpath->query('//qpy:format-float')) as $element) {
            $float = floatval($element->textContent);

            $precision = intval($element->hasAttribute('precision') ? $element->getAttribute('precision') : -1);
            $stripzeroes = $element->hasAttribute('strip-zeros');

            $str = format_float($float, $precision, true, $stripzeroes);

            $thousandssep = $element->getAttribute('thousands-separator');
            if ($thousandssep === 'yes') {
                $thousandssep = get_string('thousandssep', 'langconfig');
            } else if ($thousandssep === 'no') {
                $thousandssep = '';
            }

            if ($thousandssep !== '') {
                $decsep = get_string('decsep', 'langconfig');
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
     * Replaces QPy-URIs such as `qpy:acme/great_package/static/css/styles.css` with functioning pluginfile URLs.
     *
     * @param string $input
     * @param question_attempt $attempt
     * @return string
     */
    private static function replace_qpy_urls(string $input, question_attempt $attempt): string {
        $question = $attempt->get_question();
        assert($question instanceof qtype_questionpy_question);

        return preg_replace_callback(
        // The first two path segments are namespace and short name, and so more restrictive.
            ';qpy://static((?:/[a-z_][a-z0-9_]{0,126}){2}(?:/[\w\-@:%+.~=]+)+);',
            function (array $match) use ($question) {
                $path = $match[1];
                $url = \moodle_url::make_pluginfile_url(
                    $question->contextid,
                    'qtype_questionpy',
                    'static',
                    null,
                    '/' . $question->packagehash . dirname($path) . '/',
                    basename($path)
                );

                return $url->out();
            },
            $input
        );
    }

    /**
     * Collects form input names that can appear multiple times in the `FormData` of the current question form.
     *
     * The names are stored in {@see question_ui_renderer::$unmappableduplicatefieldnames} and
     * {@see question_ui_renderer::$mappableduplicatefieldnames}.
     * @return void
     */
    private function populate_duplicate_field_names(): void {
        // Populate the list of names that have duplicates which we cannot assign with certainty to an input field.
        $namemap = [];
        foreach ($this->xpath->query('(//xhtml:button | //xhtml:input | //xhtml:select | //xhtml:textarea)[@name]') as $element) {
            $name = $element->getAttribute('name');
            $type = $element->getAttribute('type') ?: 'text';
            $value = $element->hasAttribute('value') ? $element->getAttribute('value') : 'on';

            if (!isset($namemap[$name])) {
                // This name has not been used yet by other elements.
                $namemap[$name] = [$element, [$value]];
                continue;
            }

            if (in_array($name, $this->unmappableduplicatefieldnames)) {
                // We already know that this name is wrongfully used multiple times.
                continue;
            }

            // Get the type and values of the other element(s) with the same name.
            [$other, &$values] = $namemap[$name];
            $othertype = $other->getAttribute('type') ?: 'text';

            if (!in_array($othertype, ['checkbox', 'radio'])) {
                // Duplicate names are not allowed for other elements.
                $this->unmappableduplicatefieldnames[] = $name;
                continue;
            }

            // Check that the types match and the values is unique.
            if ($othertype !== $type || in_array($value, $values)) {
                $this->unmappableduplicatefieldnames[] = $name;
            } else {
                $values[] = $value;
            }
        }

        // Populate the list of names where we are correctly expecting multiple values under the same name, i.e. multiple `checkbox`
        // elements with the same name and `select` elements with the `multiple` attribute.
        // In contrast to `checkbox` elements, `radio` elements with the same name only return a single value.
        foreach ($namemap as $name => [$element, $values]) {
            $aremultiplecheckboxes = $element->getAttribute('type') === 'checkbox' && count($values) > 1;
            $ismultiselect = $element->tagName === 'select' && $element->hasAttribute('multiple');
            if (!in_array($name, $this->unmappableduplicatefieldnames) && ($aremultiplecheckboxes || $ismultiselect)) {
                $this->mappableduplicatefieldnames[] = $name;
            }
        }
    }

    /**
     * For all selects, checkboxes and radios in the UI, collects the available option values.
     *
     * This can be opted-out of by setting `qpy:warn-on-unknown-option` on the input element. In the case of checkboxes
     * and radios, any element having the attribute will exclude all inputs with the same name.
     *
     * At first glance, this function seems to be more suited to be placed in
     * {@see question_ui_metadata_extractor}. It is here for two reasons:
     * - The {@see \qtype_questionpy_renderer} uses the render warnings when it also renders the UI. The metadata
     *   extractor is used in other methods.
     * - While we should discourage it, it is possible for inputs to be inside `qpy:if-role` or `qpy:feedback`
     *   elements. {@see question_ui_metadata_extractor} doesn't resolve those.
     *
     * @return array<string, available_opts_info>
     * @see check_for_and_preserve_unknown_options
     */
    private function extract_available_options(): array {
        $infobyname = [];

        /** @var DOMElement $select */
        foreach ($this->xpath->query('//xhtml:select[not(@qpy:warn-on-unknown-option = "no")]') as $select) {
            $name = $select->getAttribute('name');
            if (!$name) {
                continue;
            }

            $optvalues = [];
            /** @var DOMElement $option */
            foreach ($this->xpath->query('./xhtml:option | ./xhtml:optgroup/xhtml:option', $select) as $option) {
                $optvalues[] = $option->hasAttribute('value') ? $option->getAttribute('value') : $option->textContent;
            }

            $warn = $select->getAttributeNS(constants::NAMESPACE_QPY, 'warn-on-unknown-option') !== 'no';

            $infobyname[$name] = new available_opts_info('select', array_unique($optvalues), $warn);
        }

        /** @var DOMElement $input */
        foreach ($this->xpath->query('//xhtml:input[(@type="checkbox" or @type="radio")]') as $input) {
            $name = $input->getAttribute('name');
            if (!$name) {
                continue;
            }

            $info = $infobyname[$name] ??= new available_opts_info($input->getAttribute('type'), [], true);

            if ($input->getAttributeNS(constants::NAMESPACE_QPY, 'warn-on-unknown-option') === 'no') {
                $info->warnonunknownoption = false;
            }

            $value = $input->hasAttribute('value') ? $input->getAttribute('value') : 'on';
            if (!in_array($value, $info->availableoptions)) {
                $info->availableoptions[] = $value;
            }
        }

        foreach ($infobyname as $info) {
            sort($info->availableoptions);
        }

        return $infobyname;
    }

    /**
     * Checks the last response for invalid values.
     *
     * @param available_opts_info[] $availableoptsinfobyname
     * @param question_attempt $attempt
     * @return invalid_option_warning[]
     * @throws coding_exception
     * @see extract_available_options
     * @throws \core\exception\coding_exception
     */
    private function check_for_and_preserve_unknown_options(array $availableoptsinfobyname, question_attempt $attempt): array {
        $response = utils::get_qpy_response($attempt);

        $warnings = [];
        foreach ($availableoptsinfobyname as $name => $info) {
            if (!$info->warnonunknownoption || !isset($response->{$name})) {
                continue;
            }

            $lastvalues = $response->{$name};
            if (!is_array($lastvalues)) {
                // Happens when a multi-valued field is given only one value (which is fine).
                $lastvalues = [$lastvalues];
            }

            foreach ($lastvalues as $lastvalue) {
                if (!in_array($lastvalue, $info->availableoptions)) {
                    // We don't preserve values for any type other than selects because it would be difficult to then remove the
                    // invalid value:
                    // For multi-valued fields, there would be no way for us to know whether the intention was to replace the
                    // invalid value or add a new one.
                    // For single-valued fields, while we can assume that any valid value should overwrite the invalid value, that
                    // would add a fair bit of complexity for little benefit.

                    $warnings[] = new invalid_option_warning(
                        $name,
                        $lastvalue,
                        $info->availableoptions,
                        preserved: $info->type === 'select'
                    );
                }
            }
        }
        return $warnings;
    }
}
