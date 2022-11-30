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

namespace qtype_questionpy\form;

/**
 * Mangles the names of QuestionPy package-specific form element names to prevent collisions.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class form_name_mangler {

    /**
     * Prefixes a form element name so that it is distinguishable from Moodle's own options in the submitted form data.
     *
     * @param string $name original form element name, as used in the question package
     * @return string mangled name
     */
    public static function mangle(string $name): string {
        return "qpy_form_$name";
    }

    /**
     * Does the opposite of {@see mangle}: If the name is mangled, strips away the prefix. Otherwise, returns null.
     *
     * @param string $name possibly mangled form element name
     * @return ?string unmangled name or null
     */
    public static function unmangle(string $name): ?string {
        if (strlen($name) > 9 && substr($name, 0, 9) !== "qpy_form_") {
            // Not a package-specific QuestionPy form element.
            return null;
        }

        return substr($name, 9);
    }
}
