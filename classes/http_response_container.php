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
 * Represents an HTTP cURL response.
 *
 * @package    qtype_questionpy
 * @copyright  2022 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class http_response_container {
    /**
     * @var int response code
     */
    public $code;

    /**
     * @var string data string
     */
    private $data;

    /**
     * @var array|null cached array of data
     */
    private $json;

    /**
     * Constructs a response object.
     *
     * @param int $code response code
     * @param string $data
     */
    public function __construct(int $code, string $data = '') {
        $this->code = $code;
        $this->data = $data;
        $this->json = null;
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

        // Check if data is already cached.
        if (!is_null($this->json)) {
            return $this->json;
        }

        // Parse data.
        $this->json = json_decode($this->data, true);
        if (is_null($this->json)) {
            throw new moodle_exception('json_parsing_error', 'qtype_questionpy', '');
        }

        return $this->json;
    }
}
