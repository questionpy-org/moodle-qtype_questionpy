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

namespace qtype_questionpy\package;

use qtype_questionpy\array_converter\array_converter;

/**
 * Represents a QuestionPy package.
 *
 * @package    qtype_questionpy
 * @copyright  2023 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class package_base {

    /**
     * @var string package shortname
     */
    protected $shortname;

    /**
     * @var string package namespace
     */
    protected $namespace;

    /**
     * @var array package name
     */
    protected $name;

    /**
     * @var string package type
     */
    protected $type;

    /**
     * @var string|null package author
     */
    protected $author;

    /**
     * @var string|null package url
     */
    protected $url;

    /**
     * @var array|null package languages
     */
    protected $languages;

    /**
     * @var array|null package description
     */
    protected $description;

    /**
     * @var string|null package icon
     */
    protected $icon;

    /**
     * @var string|null package license
     */
    protected $license;

    /**
     * @var array|null package tags
     */
    protected $tags;

    /**
     * Constructs package class.
     *
     * @param string $shortname
     * @param string $namespace
     * @param array $name
     * @param string $type
     * @param string|null $author
     * @param string|null $url
     * @param array|null $languages
     * @param array|null $description
     * @param string|null $icon
     * @param string|null $license
     * @param array|null $tags
     */
    public function __construct(string $shortname, string $namespace, array $name, string $type,
                                string $author = null, string $url = null, array $languages = null,
                                array  $description = null, string $icon = null, string $license = null,
                                array  $tags = null) {
        $this->shortname = $shortname;
        $this->namespace = $namespace;
        $this->name = $name;
        $this->type = $type;

        $this->author = $author;
        $this->url = $url;
        $this->languages = $languages;
        $this->description = $description;
        $this->icon = $icon;
        $this->license = $license;
        $this->tags = $tags;
    }

    /**
     * Creates a localized array representation of the package.
     *
     * @param array|null $languages
     * @return array array representation of the package
     */
    public function as_localized_array(array $languages): array {
        return array_merge(
            array_converter::to_array($this),
            [
                'name' => $this->get_localized_name($languages),
                'description' => $this->get_localized_description($languages),
            ]
        );
    }

    /**
     * Retrieves the best available localisation of a package property.
     *
     * @param string[]|null $property
     * @param string[] $languages
     * @return string
     */
    private function get_localized_property(?array $property, array $languages): string {
        // If property does not exist (e.g. description) return empty string.
        if (!isset($property)) {
            return '';
        }

        // Return first (and therefore best) available localisation string.
        foreach ($languages as $language) {
            if (isset($property[$language])) {
                return $property[$language];
            }
        }

        // No preferred localisation found - retrieve first available string or return empty string.
        return array_values($property)[0] ?? '';
    }

    /**
     * Retrieves the best available localized name of the package.
     *
     * @param array $languages preferred languages
     * @return string localized package name
     */
    public function get_localized_name(array $languages): string {
        return self::get_localized_property($this->name, $languages);
    }

    /**
     * Retrieves the best available localized description of the package.
     *
     * @param array $languages preferred languages
     * @return string localized package description
     */
    public function get_localized_description(array $languages): string {
        return self::get_localized_property($this->description, $languages);
    }
}
