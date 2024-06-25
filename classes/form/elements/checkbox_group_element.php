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

use coding_exception;
use qtype_questionpy\array_converter\array_converter;
use qtype_questionpy\array_converter\converter_config;
use qtype_questionpy\form\context\render_context;

defined('MOODLE_INTERNAL') || die;

/**
 * Element grouping one or more checkboxes with a `Select all/none` button.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class checkbox_group_element extends form_element {
    /** @var checkbox_element[] */
    public array $checkboxes = [];

    /**
     * Initializes the element.
     *
     * @param checkbox_element ...$checkboxes
     */
    public function __construct(checkbox_element ...$checkboxes) {
        $this->checkboxes = $checkboxes;
    }

    /**
     * Render this item to the given context.
     *
     * @param render_context $context target context
     * @throws coding_exception
     * @package qtype_questionpy
     */
    public function render_to(render_context $context): void {
        $groupid = $context->next_unique_int();

        foreach ($this->checkboxes as $checkbox) {
            $checkbox->render_to($context, $groupid);
        }

        $context->moodleform->add_checkbox_controller($groupid);
    }
}

array_converter::configure(checkbox_group_element::class, function (converter_config $config) {
    $config->array_elements("checkboxes", checkbox_element::class);
});
