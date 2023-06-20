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

namespace qtype_questionpy\form\elements;

use qtype_questionpy\form\form_conditions;
use qtype_questionpy\form\form_help;
use qtype_questionpy\form\render_context;

/**
 * Element displaying static text and a label.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class static_text_element extends form_element {
    /** @var string */
    public string $name;
    /** @var string */
    public string $label;
    /** @var string */
    public string $text;

    use form_conditions, form_help;

    /**
     * Initializes the element.
     *
     * @param string $name
     * @param string $label
     * @param string $text
     */
    public function __construct(string $name, string $label, string $text) {
        $this->name = $name;
        $this->label = $label;
        $this->text = $text;
    }

    /**
     * Render this item to the given context.
     *
     * @param render_context $context target context
     */
    public function render_to(render_context $context): void {
        $element = $context->add_element(
            "static", $this->name,
            $context->contextualize($this->label), $context->contextualize($this->text)
        );

        $this->render_conditions($context, $this->name);
        $this->render_help($element);
    }
}
