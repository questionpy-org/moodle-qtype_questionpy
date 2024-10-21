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

use qtype_questionpy\array_converter\attributes\array_polymorphic;
use qtype_questionpy\form\qpy_renderable;

/**
 * Base class for QuestionPy form elements.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[array_polymorphic("kind", variants: [
    "checkbox" => checkbox_element::class,
    "checkbox_group" => checkbox_group_element::class,
    "group" => group_element::class,
    "hidden" => hidden_element::class,
    "radio_group" => radio_group_element::class,
    "repetition" => repetition_element::class,
    "select" => select_element::class,
    "static_text" => static_text_element::class,
    "input" => text_input_element::class,
    "textarea" => text_area_element::class,
], fallbackvariant: fallback_element::class)]
abstract class form_element implements qpy_renderable {
}
