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

/**
 * The last response has fields set to values which don't seem to be available anymore.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2024 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class invalid_option_warning {
    /**
     * Trivial constructor.
     *
     * @param string $name name of the input field in question
     * @param string $value value from the last response, for which no option was found in the UI
     * @param array $availablevalues the available options present in the UI
     * @param bool $preserved true indicates that the HTML was modified in such a way that the invalid value will still be present
     *                        in future submissions unless explicitly changed
     */
    public function __construct(
        /** @var string $name name of the input field in question */
        public string $name,
        /** @var string $value value from the last response, for which no option was found in the UI */
        public string $value,
        /** @var array $availablevalues the available options present in the UI */
        public array $availablevalues,
        /**
         * @var bool $preserved true indicates that the HTML was modified in such a way that the invalid value will still be present
         *                      in future submissions unless explicitly changed
         */
        public bool $preserved,
    ) {
    }

    /**
     * Return a localized HTML string describing this warning to humans. Name and values are escaped.
     *
     * @return string
     * @throws coding_exception
     */
    public function localize(): string {
        $availablevaluesstr = implode(', ', array_map(
            fn($value) => html_writer::tag('code', s($value)),
            $this->availablevalues
        ));

        $msg = get_string('render_warning_invalid_value', 'qtype_questionpy', [
            'name' => html_writer::tag('code', s($this->name)),
            'value' => html_writer::tag('code', s($this->value)),
            'availablevalues' => $availablevaluesstr,
        ]);

        if ($this->preserved) {
            $msg .= ' ' . get_string('render_warning_invalid_value_preserved', 'qtype_questionpy');
        } else {
            $msg .= ' ' . get_string('render_warning_invalid_value_not_preserved', 'qtype_questionpy');
        }

        return $msg;
    }
}
