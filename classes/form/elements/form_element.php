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

use qtype_questionpy\array_converter\array_converter;
use qtype_questionpy\array_converter\converter_config;
use qtype_questionpy\form\qpy_renderable;

defined('MOODLE_INTERNAL') || die;

/**
 * Base class for QuestionPy form elements.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class form_element implements qpy_renderable {
}

array_converter::configure(form_element::class, function (converter_config $config) {
    $config
        ->discriminate_by("kind")
        ->variant("checkbox", checkbox_element::class)
        ->variant("checkbox_group", checkbox_group_element::class)
        ->variant("group", group_element::class)
        ->variant("hidden", hidden_element::class)
        ->variant("radio_group", radio_group_element::class)
        ->variant("repetition", repetition_element::class)
        ->variant("select", select_element::class)
        ->variant("static_text", static_text_element::class)
        ->variant("input", text_input_element::class)
        ->variant("textarea", text_area_element::class);
});
