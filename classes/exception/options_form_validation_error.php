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

namespace qtype_questionpy\exception;

use GuzzleHttp\Exception\RequestException;

/**
 * Creates a throwable options form validation error.
 *
 * @package    qtype_questionpy
 * @author     Jan Britz
 * @copyright  2025 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class options_form_validation_error extends request_error {
    /**
     * Creates a throwable options form validation error.
     *
     * @param RequestException $origin
     * @param error_code $requesterrorcode
     * @param bool $temporary
     * @param string|null $reason
     * @param array $errors string to string mapping of field names to the error message
     */
    public function __construct(
        /** @var RequestException */
        public RequestException $origin,
        /** @var error_code */
        public error_code $requesterrorcode,
        /** @var bool */
        public bool $temporary,
        /** @var string|null */
        public ?string $reason,
        /** @var array<string, string> */
        public array $errors,
    ) {
        parent::__construct($origin, $requesterrorcode, $temporary, $reason);
    }
}
