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
use qtype_questionpy\form\context\render_context;

/**
 * Fallback element used when the server sends an unsupported element.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2024 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fallback_element extends form_element {
    /** @var string|null */
    public ?string $name = null;

    /**
     * Render this item to the given context.
     *
     * @param render_context $context target context
     * @throws coding_exception
     * @package qtype_questionpy
     */
    public function render_to(render_context $context): void {
        $name = $this->name;
        if (!$name) {
            $name = "qpy_fallback_" . $context->next_unique_int();
        }

        $context->add_element(
            "warning",
            $name,
            null,
            get_string("form_fallback_element_text", "qtype_questionpy")
        );
    }
}
