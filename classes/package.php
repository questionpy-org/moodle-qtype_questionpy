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
 * Helper class for QuestionPy packages.
 *
 * @package    qtype_questionpy
 * @copyright  2022 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class package {

    /**
     * Replaces language arrays of a QuestionPy package with text in an appropriate language.
     *
     * @param array $package QuestionPy package
     * @return array transformed QuestionPy package
     */
    public static function localize(array $package): array {
        // Get current language.
        $currentlanguage = current_language();

        // TODO: maybe check if 'languages' is populated -> if not, package is corrupt.
        // Select first available language as initial value.
        $selectedlanguage = $package['languages'][0];

        // Iterate over all available languages in the package.
        foreach ($package['languages'] as $language) {

            switch ($language) {
                // Found preferred language - exit loop.
                case $currentlanguage:
                    $selectedlanguage = $language;
                    break 2;

                // Found fallback language - keep on looking for preferred language.
                case 'en':
                    $selectedlanguage = $language;
                    break;

                default:
                    break;
            }

        }

        // Copy package and replace language arrays with text.
        $newpackage = $package;

        $newpackage['description'] = $package['description'][$selectedlanguage];
        $newpackage['name'] = $package['name'][$selectedlanguage];

        return $newpackage;
    }
}
