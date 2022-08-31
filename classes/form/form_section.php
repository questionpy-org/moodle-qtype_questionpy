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

use qtype_questionpy\form\elements\form_elements;

class form_section implements renderable {
    public string $header;
    public form_elements $elements;

    public function __construct(string $header, form_elements $elements) {
        $this->header = $header;
        $this->elements = $elements;
    }

    public static function from_array(array $array): self {
        return new self(
            $array["header"],
            form_elements::from_array($array["elements"])
        );
    }

    public function render_to($context): void {
        $context->add_element("header", "qpy_section_" . spl_object_hash($this), $this->header);
        foreach ($this->elements as $element) {
            $element->render_to($context);
        }
    }
}
