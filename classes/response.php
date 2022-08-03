<?php
// This file is part of Moodle - http://moodle.org/
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

namespace qtype_questionpy;

use moodle_exception;

/**
 * Represents an HTTP response.
 *
 * @package    qtype_questionpy
 * @copyright  2022 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class response {
    /**
     * @var int response code
     */
    protected $code;

    /**
     * @var string data string
     */
    protected $data;

    /**
     * Constructs a response object.
     *
     * @param int $code response code
     * @param string $data
     */
    public function __construct(int $code, string $data = '') {
        $this->code = $code;
        $this->data = $data;
    }

    /**
     * Returns the response code.
     *
     * @return int
     */
    public function get_code(): int {
        return $this->code;
    }

    /**
     * Returns data as string or parsed associative array.
     *
     * @param bool $json if true, parse string to associative array
     * @throws moodle_exception
     * @return string|array
     */
    public function get_data(bool $json = true) {
        if (!$json) {
            return $this->data;
        }

        // Parse JSON.
        $result = json_decode($this->data, true);
        if (is_null($result)) {
            throw new moodle_exception(get_string('json_parsing_error', 'qtype_questionpy'),
                "qtype_questionpy", '', $this->data);
        }
        return $result;
    }
}
