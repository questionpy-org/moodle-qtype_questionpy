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

namespace qtype_questionpy\form;

use qtype_questionpy\array_converter\array_converter;
use qtype_questionpy\array_converter\converter_config;
use qtype_questionpy\form\elements\form_element;

defined('MOODLE_INTERNAL') || die;

/**
 * Collapsible form section introduced by a header.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class form_section implements renderable {
    /** @var string */
    public string $header;
    /** @var form_element[] */
    public array $elements;

    /**
     * Initialize a new form section.
     *
     * @param string $header
     * @param form_element[] $elements
     */
    public function __construct(string $header, array $elements) {
        $this->header = $header;
        $this->elements = $elements;
    }

    /**
     * Render this item to the given context.
     *
     * @param render_context $context target context
     */
    public function render_to(render_context $context): void {
        $context->add_element("header", "qpy_section_" . $context->next_unique_int(), $this->header);
        foreach ($this->elements as $element) {
            $element->render_to($context);
        }
    }
}

array_converter::configure(form_section::class, function (converter_config $config) {
    $config->array_elements("elements", form_element::class);
});
