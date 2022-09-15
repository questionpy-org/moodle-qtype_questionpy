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
 * Helper class for cURL.
 *
 * @package    qtype_questionpy
 * @copyright  2022 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class connector {

    /**
     * @var string server url
     */
    private string $serverurl;

    /**
     * @var int cURL timeout
     */
    private int $timeout;

    /**
     * @var false|resource cURL handle
     */
    private $curlhandle;

    /**
     * Constructs connector class.
     *
     * @param string $serverurl
     * @param int $timeout
     * @throws moodle_exception
     */
    public function __construct(string $serverurl, int $timeout = 30) {
        // Sanitize url.
        $this->serverurl = rtrim($serverurl, '/');

        $this->timeout = $timeout;

        // Initialize curl handle.
        $this->curlhandle = curl_init();

        if (!$this->curlhandle) {
            throw new moodle_exception('curl_init_error', 'qtype_questionpy', '',
                    curl_errno($this->curlhandle), curl_error($this->curlhandle));
        }

        $this->set_opts([
            CURLOPT_VERBOSE => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $this->timeout
        ]);
    }

    /**
     * Destructs connector class.
     */
    public function __destruct() {
        curl_close($this->curlhandle);
    }

    /**
     * Set an option for cURL transfer.
     *
     * @param int $option
     * @param mixed $value
     * @throws moodle_exception
     */
    private function set_opt(int $option, $value): void {
        $success = curl_setopt($this->curlhandle, $option, $value);

        if (!$success) {
            throw new moodle_exception('curl_set_opt_error', 'qtype_questionpy', '',
                    curl_errno($this->curlhandle), curl_error($this->curlhandle));
        }
    }

    /**
     * Set multiple options for cURL transfer.
     *
     * @param array $options cURL options and values
     * @throws moodle_exception
     */
    private function set_opts(array $options): void {
        $success = curl_setopt_array($this->curlhandle, $options);

        if (!$success) {
            throw new moodle_exception('curl_set_opt_error', 'qtype_questionpy', '',
                    curl_errno($this->curlhandle), curl_error($this->curlhandle));
        }
    }

    /**
     * Concatenates host url with path and sets the resulting url.
     *
     * @param string $path
     * @throws moodle_exception
     */
    private function set_url(string $path = ''): void {
        $url = $this->serverurl . '/' . ltrim($path, '/');
        $this->set_opt(CURLOPT_URL, $url);
    }

    /**
     * Perform cURL session.
     *
     * @throws moodle_exception
     * @return http_response_container data received from server
     */
    private function exec(): http_response_container {
        $data = curl_exec($this->curlhandle);

        // Check for cURL failure.
        if ($data === false) {
            throw new moodle_exception('curl_exec_error', 'qtype_questionpy', '',
                    curl_errno($this->curlhandle), curl_error($this->curlhandle));
        }

        // Create response.
        $responsecode = curl_getinfo($this->curlhandle, CURLINFO_RESPONSE_CODE);
        return new http_response_container($responsecode, $data);
    }

    /**
     * Performs a GET request to the given path on the application server.
     *
     * @param string $path
     * @throws moodle_exception
     * @return http_response_container data received from server
     */
    public function get(string $path = ''): http_response_container {
        // Set url to the endpoint.
        $this->set_url($path);

        // Setup GET request.
        $this->set_opt(CURLOPT_HTTPGET, true);

        // Execute GET request.
        return $this->exec();
    }

    /**
     * Performs a POST request to the given path on the application server.
     * If `$data` is a string, the Content-Type will be set to application/json.
     * If `$data` is an array, the Content-Type will be set to multipart/form-data.
     *
     * @param string $path
     * @param string|array|null $data
     * @throws moodle_exception
     * @return http_response_container data received from server
     */
    public function post(string $path = '', $data = null): http_response_container {
        // Set url to the endpoint.
        $this->set_url($path);

        // Setup POST request.
        $this->set_opt(CURLOPT_POST, true);

        // Set data and content type if data is available.
        if (!is_null($data)) {
            $contenttype = is_string($data) ? 'application/json' : 'multipart/form-data';
            $this->set_opts([
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_HTTPHEADER => "Content-Type: $contenttype"
            ]);
        }

        // Execute POST request.
        return $this->exec();
    }

}
