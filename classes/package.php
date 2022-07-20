<?php

namespace qtype_questionpy;

/**
 * Helper class for QuestionPy packages.
 *
 * @package    qtype_questionpy
 * @copyright  2022 Martin Gauk, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class package {

    /**
     * Replaces language arrays of a QuestionPy package with text in an appropriate language.
     *
     * @param $package array QuestionPy package
     * @return array transformed QuestionPy package
     */
    public static function localize(array $package, string $fallback = 'en'): array {
        // Get current language
        $current_language = current_language();

        // TODO: maybe check if 'languages' is populated -> if not, package is corrupt
        // Select first available language as initial value
        $selected_language = $package['languages'][0];

        // Iterate over all available languages in the package
        foreach ($package['languages'] as $language) {

            switch ($language) {
                // Found preferred language - exit loop
                case $current_language:
                    $selected_language = $language;
                    break 2;

                // Found fallback language - keep on looking for preferred language
                case $fallback:
                    $selected_language = $language;
                    break;

                default:
                    break;
            }

        }

        // Copy package and replace language arrays with text
        $new_package = $package;

        $new_package['description'] = $package['description'][$selected_language];
        $new_package['name'] = $package['name'][$selected_language];

        return $new_package;
    }
}