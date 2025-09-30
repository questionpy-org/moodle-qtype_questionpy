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

namespace qtype_questionpy\local\array_converter;

use core\exception\moodle_exception;


/**
 * Exception class for {@see array_converter}.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2025 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class conversion_exception extends moodle_exception {
    /**
     * Constructs a new instance of the exception with details about the conversion failure.
     *
     * @param string $actualtype
     * @param string $typehint
     * @param string $debuginfo
     * @return void
     */
    public function __construct(string $actualtype, string $typehint, string $debuginfo) {
        parent::__construct(
            'array_converter_cannot_convert',
            'qtype_questionpy',
            null,
            (object) [
                'actualtype' => $actualtype,
                'typehint' => $typehint,
            ],
            $debuginfo
        );
    }
}
