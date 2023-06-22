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

/**
 * Uppermost render context.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class root_render_context extends mform_render_context {

    /** @var int the next int which will be returned by {@see next_unique_int} */
    private int $nextuniqueint = 1;

    /**
     * Get a unique and deterministic integer for use in generated element names and IDs.
     *
     * @return int a unique and deterministic integer for use in generated element names and IDs.
     */
    public function next_unique_int(): int {
        return $this->nextuniqueint++;
    }

    /**
     * Replaces occurrences of `{ qpy:... }` with the appropriate contextual variable, if any.
     *
     * This render context returns the string unchanged.
     *
     * @param string $text string possibly containing `{ qpy:... }` format specifiers
     * @return string input string with format specifiers replaced
     */
    public function contextualize(string $text): string {
        return $text;
    }
}
