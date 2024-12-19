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

use core\uuid;
use qtype_questionpy\form\context\render_context;


/**
 * Generates a unique ID which won't change across form saves.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2024 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generated_id_element extends form_element {
    /**
     * Trivial constructor.
     *
     * @param string $name
     */
    public function __construct(
        /** @var string $name */
        public string $name
    ) {
    }

    /**
     * Render this item to the given context.
     *
     * @param render_context $context target context
     * @package qtype_questionpy
     */
    public function render_to(render_context $context): void {
        $mangledname = $context->mangle_name($this->name);
        $value = $context->moodleform->optional_param(
            $mangledname,
            null,
            PARAM_ALPHANUMEXT
        ) ?? $context->data[$this->name] ?? $context->generate_uuid();

        $context->add_element('hidden', $mangledname, $value);
        $context->set_type($this->name, PARAM_ALPHANUMEXT);
    }
}
