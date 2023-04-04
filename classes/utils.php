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

use Generator;

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

    public static function str_remove_prefix(string $haystack, string $prefix): string {
        if (self::str_starts_with($haystack, $prefix)) {
            return substr($haystack, strlen($prefix));
        }
        return $haystack;
    }

    /**
     * Given an array with possible nested arrays, generates flat entries keys reflect the paths in the input array.
     *
     * @param array $source  input array which might contain nested arrays
     * @param string $prefix prefix for all returned keys, as if `$source` where nested in an array with that key
     * @return Generator<string, mixed> flat generator of entries
     */
    public static function flatten(array $source, string $prefix): Generator {
        foreach ($source as $key => $value) {
            $fullkey = "{$prefix}[$key]";
            if (is_array($value)) {
                yield from self::flatten($value, $fullkey);
            } else {
                yield $fullkey => $value;
            }
        }
    }
}
