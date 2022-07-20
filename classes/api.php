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

/**
 * Helper class for communicating to the application server.
 *
 * @package    qtype_questionpy
 * @copyright  2022 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {

    /**
     * Application server host.
     */
    const HOST = 'http://localhost';

    /**
     * Application server port.
     */
    const PORT = '9020';

    /**
     * Concatenates host url with path and validates the outcome.
     *
     * @param string $path
     * @return false|string url on success or false on failure.
     */
    private static function create_url(string $path) {
        $url = self::HOST . ':' . self::PORT . $path;

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        return $url;
    }

    /**
     * Performs a GET request to the given path on the application server.
     *
     * @param string $path
     * @param float $timeout
     * @return bool|string result on success or false on failure.
     */
    public static function get(string $path = '', float $timeout = 30) {
        // Create url to the endpoint.
        $url = self::create_url($path);

        if (!$url) {
            return false;
        }

        // Prepare GET request.
        $curlhandle = curl_init();

        curl_setopt($curlhandle, CURLOPT_URL, $url);
        curl_setopt($curlhandle, CURLOPT_VERBOSE, false);
        curl_setopt($curlhandle, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($curlhandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlhandle, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($curlhandle, CURLOPT_TIMEOUT, $timeout);

        // Execute GET request.
        $result = curl_exec($curlhandle);
        $statuscode = curl_getinfo($curlhandle, CURLINFO_RESPONSE_CODE);

        curl_close($curlhandle);

        if ($statuscode != 200) {
            return false;
        }

        return $result;
    }

    /**
     * Retrieves QuestionPy packages from the application server.
     *
     * @return bool|array packages on success or false on failure.
     */
    public static function get_packages() {
        // Perform GET request.
        $result = self::get('/packages');

        if (!$result) {
            return false;
        }

        // Decode result and return array.
        return json_decode($result, true);
    }
}
