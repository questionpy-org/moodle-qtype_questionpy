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

namespace qtype_questionpy\form\context;

use qtype_questionpy\utils;

/**
 * A nested render context.
 *
 * @see        root_render_context
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2023 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section_render_context extends mform_render_context {
    /** @var render_context context containing this section */
    private render_context $parent;

    /**
     * Initializes a new {@see section_render_context}.
     *
     * @param render_context $parent context containing this section
     * @param string $name           the name part which will be appended to `$parent`'s prefix
     */
    public function __construct(render_context $parent, string $name) {
        $this->parent = $parent;
        parent::__construct(
            $parent->moodleform, $parent->mform,
            $parent->mangle_name($name),
            utils::array_get_nested($parent->data, $name) ?? []
        );
    }

    /**
     * Get a unique and deterministic integer for use in generated element names and IDs.
     *
     * @return int a unique and deterministic integer for use in generated element names and IDs.
     */
    public function next_unique_int(): int {
        return $this->parent->next_unique_int();
    }

    /**
     * Replaces occurrences of `{ qpy:... }` with the appropriate contextual variable, if any.
     *
     * This implementation just delegates to the parent context, so that repetition numbers are also replaced within
     * groups.
     *
     * @param string $text string possibly containing `{ qpy:... }` format specifiers
     * @return string input string with format specifiers replaced
     */
    public function contextualize(string $text): string {
        return $this->parent->contextualize($text);
    }
}
