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
 * Helper class for localisation.
 *
 * @package    qtype_questionpy
 * @copyright  2022 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class localizer {

    /**
     * Naive function to get the parent of a language.
     * Possibly a useful alternative to `get_parent_language` of Moodle?
     *
     * @param string $language
     * @return string parent language
     */
    private static function get_parent_language(string $language): string {
        $position = strrpos($language, '_');
        if (!$position) {
            return '';
        }
        return substr($language, 0, $position) ?? '';
    }

    /**
     * Populates language array and takes the parent language(s) into account.
     *
     * @param string $language the language to be added
     * @param array $languages
     * @return void
     */
    private static function populate_language_array(string $language, array &$languages): void {
        $language = str_replace('_utf8', '', $language);
        $languages[] = $language;
        while ($language = get_parent_language($language)) {
            $languages[] = $language;
        }
    }

    /**
     * Generates sorted list with languages from most to least preferred.
     *
     * @return array preferred languages
     */
    public static function get_preferred_languages(): array {
        global $CFG, $USER, $SESSION, $COURSE;

        $languages = [];

        if (!empty($SESSION->forcelang)) {
            self::populate_language_array($SESSION->forcelang, $languages);
        }
        if (!empty($COURSE->id) && $COURSE->id != SITEID && !empty($COURSE->lang)) {
            self::populate_language_array($COURSE->lang, $languages);
        }
        if (!empty($SESSION->lang)) {
            self::populate_language_array($SESSION->lang, $languages);
        }
        if (!empty($USER->lang)) {
            self::populate_language_array($USER->lang, $languages);
        }
        if (isset($CFG->lang)) {
            self::populate_language_array($CFG->lang, $languages);
        }
        $languages[] = 'en';

        return $languages;
    }
}
