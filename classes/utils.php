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

namespace qtype_questionpy;

use core\exception\coding_exception;
use qtype_questionpy\local\form\elements\repetition_element;
use question_attempt;

/**
 * Utility functions used in multiple places.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class utils {
    /**
     * Returns `$array[$key]` after setting it to a new array if it does not exist.
     *
     * @param array $array
     * @param string $key
     * @return array
     */
    public static function &ensure_exists(array &$array, string $key): array {
        if (!isset($array[$key])) {
            $array[$key] = [];
        }

        return $array[$key];
    }

    /**
     * Given an array and a key such as `abc[def]`, returns `$array["abc"]["def"]`.
     *
     * If any of the key's parts don't exist or resolve to null, this function returns null.
     *
     * @param array $array
     * @param string $key
     * @return mixed
     */
    public static function array_get_nested(array $array, string $key) {
        // Explode a $name like qpy_form[abc][def] into an array ["qpy_form", "abc", "def"].
        $parts = explode('[', str_replace(']', '', $key));

        $current = $array;
        foreach ($parts as $key) {
            $current = $current[$key] ?? null;
            if ($current === null) {
                return null;
            }
        }

        return $current;
    }

    /**
     * Given a key such as `abc[def]`, returns an array `[ "abc" => [ "def" => $value ] ]`.
     *
     * @param string $key
     * @param mixed $value
     * @return array
     */
    public static function array_create_nested(string $key, $value): array {
        // Explode a $name like qpy_form[abc][def] into an array ["qpy_form", "abc", "def"].
        $parts = explode('[', str_replace(']', '', $key));

        $array = [];
        $current = &$array;
        foreach ($parts as $key) {
            if (!is_array($current)) {
                $current = [];
            }
            $current = &$current[$key];
        }
        $current = $value;

        return $array;
    }

    /**
     * Within `$array`, recursively looks for any arrays with numeric-only keys with gaps and reindexes them.
     *
     * This causes {@see json_encode} to serialize these arrays (with gaps) to JSON objects rather than JSON
     * arrays. {@see repetition_element}s produce numeric arrays with gaps when repetitions are removed.
     *
     * @param array $array
     * @return void
     */
    public static function reindex_integer_arrays(array &$array): void {
        $numeric = true;
        foreach ($array as $key => &$value) {
            if (!is_integer($key)) {
                $numeric = false;
            }

            if (is_array($value)) {
                self::reindex_integer_arrays($value);
            }
        }

        if ($numeric) {
            $array = array_values($array);
        }
    }

    /**
     * Parses the JSON-encoded QuestionPy response from either a specific submission or a question attempt.
     *
     * @param question_attempt|array $qa either the last submission (a.k.a. qt data) or the entire question attempt, in which case
     *                                   the last submitted response is used.
     * @return object|null
     * @throws coding_exception
     */
    public static function get_qpy_response(question_attempt|array $qa): ?object {
        if (is_array($qa)) {
            $responsestr = $qa[constants::QT_VAR_RESPONSE] ?? null;
        } else {
            $responsestr = $qa->get_last_qt_var(constants::QT_VAR_RESPONSE);
        }

        if (!$responsestr) {
            return null;
        }

        // We want to ensure that dynamic data with a depth of 16 can be used.
        // Since the `data` field if part of the response object, we need to accept a recursion depth of 16 + 2 = 18.
        $response = json_decode($responsestr, depth: 18);
        if (json_last_error() != JSON_ERROR_NONE) {
            throw new coding_exception('Could not decode response JSON: ' . json_last_error_msg());
        }
        if (!is_object($response)) {
            throw new coding_exception('Expected response JSON to be an object, got: ' . gettype($response));
        }

        return $response;
    }
}
