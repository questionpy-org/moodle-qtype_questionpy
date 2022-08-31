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

use qtype_questionpy\form\elements\form_element;

class qpy_form implements renderable {
    /** @var form_element[] */
    public array $general;
    /** @var form_section[] */
    public array $sections;

    /**
     * @param form_element[] $general
     * @param form_section[] $sections
     */
    public function __construct(array $general = [], array $sections = []) {
        $this->general = $general;
        $this->sections = $sections;
    }

    public static function from_array(array $array): self {
        return new self(
            array_map([form_element::class, "from_array_any"], $array["general"]),
            array_map([form_section::class, "from_array_any"], $array["sections"])
        );
    }

    public function render_to($context): void {
        foreach ($this->general as $element) {
            $element->render_to($context);
        }

        foreach ($this->sections as $section) {
            $section->render_to($context);
        }
    }
}
