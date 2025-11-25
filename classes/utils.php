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
    public static function array_get_nested(array $array, string $key): mixed {
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
     * Given a key such as `abc[def]`, sets `$array['abc']['def'] = $value`, creating missing arrays along the way.
     *
     * @param array $array
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public static function array_set_nested(array &$array, string $key, mixed $value): void {
        // Explode a $name like qpy_form[abc][def] into an array ["qpy_form", "abc", "def"].
        $parts = explode('[', str_replace(']', '', $key));

        $current = &$array;
        foreach ($parts as $key) {
            if (!is_array($current)) {
                $current = [];
            }
            $current = &$current[$key];
        }
        $current = $value;
    }

    /**
     * Given a key such as `abc[def]`, returns an array `[ "abc" => [ "def" => $value ] ]`.
     *
     * @param string $key
     * @param mixed $value
     * @return array
     */
    public static function array_create_nested(string $key, mixed $value): array {
        $array = [];
        self::array_set_nested($array, $key, $value);
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

    /**
     * Parses the JSON-encoded response data for all WYSIWYG editors from either a specific submission or a question attempt.
     *
     * @param question_attempt|array $qa Either the last submission (a.k.a. qt data) or the entire question attempt, in which case
     *                                   the last submitted response is used.
     * @return array An associative array of editor names to editor data objects, or an empty array if the last submission
     *               didn't include any editor data.
     * @throws coding_exception
     */
    public static function get_qpy_editors_data(question_attempt|array $qa): array {
        if (is_array($qa)) {
            $str = $qa[constants::QT_VAR_EDITORS] ?? null;
        } else {
            // In the unlikely event that the editor is removed between steps, the editors qt var would be missing.
            // We don't want to use the old editor data in that case, so we choose the step based on the response qt var.
            $lastresponsestep = $qa->get_last_step_with_qt_var(constants::QT_VAR_RESPONSE);
            $str = $lastresponsestep->get_qt_var(constants::QT_VAR_EDITORS);
        }

        if (!$str) {
            return [];
        }

        $editors = json_decode($str, depth: 3);
        if (json_last_error() != JSON_ERROR_NONE) {
            throw new coding_exception('Could not decode editor data JSON: ' . json_last_error_msg());
        }
        if (!is_object($editors)) {
            throw new coding_exception('Expected editor data JSON to be an object, got: ' . gettype($editors));
        }

        $editors = (array) $editors;

        foreach ($editors as $editorname => $editordata) {
            if (!is_object($editordata)) {
                throw new coding_exception("Expected editor data for '$editorname' to be an object, got: " . gettype($editordata));
            }

            if (
                    !isset($editordata->text) || !is_string($editordata->text)
                    || !isset($editordata->format) || !is_numeric($editordata->format)
            ) {
                throw new coding_exception("Editor data for editor '$editorname' has wrong shape.");
            }
        }

        return $editors;
    }
}
