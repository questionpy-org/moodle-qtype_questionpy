<?php
namespace qtype_questionpy;

/**
 * Helper class for communicating to the application server.
 *
 * @package    qtype_questionpy
 * @copyright  2022 Martin Gauk, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {

    // Host of the application server
    const HOST = 'http://localhost';

    // Port of the application server
    const PORT = '9020';

    /**
     * Concatenates host url with path and validates the outcome.
     *
     * @param string $path
     * @return false|string url on success or false on failure.
     */
    private static function create_url(string $path) {
        $url = api::HOST . ':' . api::PORT . $path;

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
        // Create url to the endpoint
        $url = api::create_url($path);

        if (!$url) {
            return false;
        }

        // Prepare GET request
        $curlhandle = curl_init();

        curl_setopt($curlhandle, CURLOPT_URL, $url);
        curl_setopt($curlhandle, CURLOPT_VERBOSE, false);
        curl_setopt($curlhandle, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($curlhandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlhandle, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($curlhandle, CURLOPT_TIMEOUT, $timeout);

        // Execute GET request
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
        // Perform GET request
        $result = api::get('/packages');

        if (!$result) {
            return false;
        }

        // Decode result and return array
        return json_decode($result, true);
    }
}