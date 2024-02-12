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

use context;
use moodle_exception;
use qtype_questionpy\form\elements\repetition_element;

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
     * Determines whether a string starts with another.
     *
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public static function str_starts_with(string $haystack, string $needle): bool {
        // From https://stackoverflow.com/a/10473026.
        return substr_compare($haystack, $needle, 0, strlen($needle)) === 0;
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
        $parts = explode("[", str_replace("]", "", $key));

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
        $parts = explode("[", str_replace("]", "", $key));

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
     * Returns a list of relevant context ids related to the given context.
     *
     * If the given context is part of a course context, the course context id and every child context id are returned.
     * Else, only the id of the given context is returned inside the array.
     *
     * @param context $context
     * @return int[] relevant context ids
     * @throws moodle_exception
     */
    public static function get_relevant_context_ids(context $context): array {
        // If context is part of a course, get every context of that course.
        $coursecontext = $context->get_course_context(false);
        if ($coursecontext) {
            // Context is part of a course.
            $contexts = $coursecontext->get_child_contexts();
            $contextids = array_keys($contexts);
            $contextids[] = $coursecontext->id;
        } else {
            // Context is not part of a course.
            $contextids[] = $context->id;
        }
        return $contextids;
    }
}
