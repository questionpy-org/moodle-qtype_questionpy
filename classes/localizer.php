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

/**
 * Helper class for localisation.
 *
 * @package    qtype_questionpy
 * @copyright  2022 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class localizer {
    /**
     * Generates sorted list with languages from most to least preferred.
     *
     * @return array preferred languages
     */
    public static function get_preferred_languages(): array {
        $languages = [];

        // Get current language and every parent language.
        $language = current_language();
        do {
            $languages[] = $language;
        } while ($language = get_parent_language($language));

        // Fallback is english.
        if (!in_array('en', $languages)) {
            $languages[] = 'en';
        }

        return $languages;
    }
}
