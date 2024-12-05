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

namespace qtype_questionpy\attempt_ui;

use coding_exception;
use html_writer;

class invalid_option_warning {
    public function __construct(
        public string $name,
        public string $value,
        public array $availablevalues
    ) {
    }

    /**
     * @throws coding_exception
     */
    public function localize(): string {
        $availablevaluesstr = implode(', ', array_map(
            fn($value) => html_writer::tag('code', s($value)),
            $this->availablevalues
        ));

        return get_string('render_warning_invalid_value', 'qtype_questionpy', [
            'name' => html_writer::tag('code', s($this->name)),
            'value' => html_writer::tag('code', s($this->value)),
            'availablevalues' => $availablevaluesstr,
        ]);
    }
}
