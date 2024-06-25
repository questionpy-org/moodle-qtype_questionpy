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

use qtype_questionpy\form\elements\repetition_element;

/**
 * Special {@see render_context} for a single iteration of a {@see repetition_element}.
 *
 * @see        root_render_context
 * @see        array_render_context
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2023 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repetition_render_context extends section_render_context {
    /** @var int running number of this repetition iteration, starting at 1 and not counting removed reps */
    private int $humanrepno;

    /**
     * Initializes a context for a single iteration of a {@see repetition_element}.
     *
     * @param render_context $parent context containing the repetition
     * @param string $name           name of the repetition, without index
     * @param int $repno             running number of this repetition iteration, starting at 0 and also counting removed reps
     * @param int $humanrepno        running number of this repetition iteration, starting at 1 and not counting removed reps
     */
    public function __construct(render_context $parent, string $name, int $repno, int $humanrepno) {
        $this->humanrepno = $humanrepno;
        parent::__construct($parent, $name . "[$repno]");
    }

    /**
     * Replaces occurrences of `{ qpy:repno }` with the current repetition number.
     *
     * @param string|null $text string possibly containing `{ qpy:repno }` format specifiers
     * @return string|null input string with format specifiers replaced
     */
    public function contextualize(?string $text): ?string {
        if (!$text) {
            return $text;
        }

        $text = preg_replace('/\{\s*qpy:repno\s*}/', $this->humanrepno, $text);
        return parent::contextualize($text);
    }
}
