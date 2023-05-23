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

use HTML_QuickForm_element;

/**
 * Trait for elements that can have a help text hidden behind a button.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2023 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait form_help {

    /** @var string|null */
    public ?string $help = null;

    /**
     * Renders the help button, if any. To be called by the {@see qpy_renderable::render_to() render_to} of elements.
     *
     * @param HTML_QuickForm_element $element target element
     */
    private function render_help(HTML_QuickForm_element $element): void {
        global $OUTPUT;
        if ($this->help) {
            $element->_helpbutton = $OUTPUT->render(new dynamic_help_icon($this->help, $element->getLabel()));
        }
    }

    /**
     * Sets the given help text and returns this instance for chaining.
     *
     * @param string $text the new help text
     * @return self $this
     */
    public function help(string $text): self {
        $this->help = $text;
        return $this;
    }
}
