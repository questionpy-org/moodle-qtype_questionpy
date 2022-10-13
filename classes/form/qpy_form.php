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
 * Question edit form of a QuestionPy question.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qpy_form implements renderable {
    /** @var form_element[] elements to be appended to the default "General" section */
    public array $general;
    /** @var form_section[] additional custom sections */
    public array $sections;

    /**
     * Initialize a new form section.
     *
     * @param form_element[] $general  elements to be appended to the default "General" section
     * @param form_section[] $sections additional custom sections
     */
    public function __construct(array $general = [], array $sections = []) {
        $this->general = $general;
        $this->sections = $sections;
    }

    /**
     * Render this item to the given context.
     *
     * @param render_context $context target context
     */
    public function render_to(render_context $context): void {
        foreach ($this->general as $element) {
            $element->render_to($context);
        }

        foreach ($this->sections as $section) {
            $section->render_to($context);
        }
    }
}

array_converter::configure(qpy_form::class, function (converter_config $config) {
    $config
        ->array_elements("general", form_element::class)
        ->array_elements("sections", form_section::class);
});
