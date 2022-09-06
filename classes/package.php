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

use TypeError;

/**
 * Represents a QuestionPy package.
 *
 * @package    qtype_questionpy
 * @copyright  2022 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class package {

    /**
     * @var string package hash
     */
    public $hash;

    /**
     * @var string package shortname
     */
    private $shortname;

    /**
     * @var array package name
     */
    private $name;

    /**
     * @var string package version
     */
    private $version;

    /**
     * @var string package type
     */
    private $type;

    /**
     * @var string|null package author
     */
    private $author;

    /**
     * @var string|null package url
     */
    private $url;

    /**
     * @var array|null package languages
     */
    private $languages;

    /**
     * @var array|null package description
     */
    private $description;

    /**
     * @var string|null package icon
     */
    private $icon;

    /**
     * @var string|null package license
     */
    private $license;

    /**
     * @var array|null package tags
     */
    private $tags;

    /**
     * Constructs package class.
     *
     * @param string $hash
     * @param string $shortname
     * @param array $name
     * @param string $version
     * @param string $type
     * @param string|null $author
     * @param string|null $url
     * @param array|null $languages
     * @param array|null $description
     * @param string|null $icon
     * @param string|null $license
     * @param array|null $tags
     */
    public function __construct(string $hash, string $shortname, array $name, string $version, string $type,
                                string $author = null, string $url = null, array $languages = null,
                                array $description = null, string $icon = null, string $license = null,
                                array $tags = null) {

        $this->hash = $hash;
        $this->shortname = $shortname;
        $this->name = $name;
        $this->version = $version;
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
     * Creates package object from array.
     *
     * @param array $package
     * @return package
     */
    public static function from_array(array $package): package {
        if (!(isset($package['package_hash'])
            && isset($package['short_name'])
            && isset($package['name'])
            && isset($package['version'])
            && isset($package['type'])))
        {
            throw new TypeError('Package array is missing required fields.');
        }

        return new self(
            $package['package_hash'],
            $package['short_name'],
            $package['name'],
            $package['version'],
            $package['type'],

            $package['author'] ?? null,
            $package['url'] ?? null,
            $package['languages'] ?? null,
            $package['description'] ?? null,
            $package['icon'] ?? null,
            $package['license'] ?? null,
            $package['tags'] ?? null
        );
    }

    /**
     * Creates a localized array representation of the package.
     *
     * @param array|null $languages
     * @return array array representation of the package
     */
    public function as_localized_array(array $languages): array {
        return [
            'package_hash' => $this->hash,
            'shortname' => $this->shortname,
            'name' => $this->get_localized_name($languages),
            'version' => $this->version,
            'type' => $this->type,

            'author' => $this->author,
            'url' => $this->url,
            'languages' => $this->languages,
            'description' => $this->get_localized_description($languages),
            'icon' => $this->icon,
            'license' => $this->license,
            'tags' => $this->tags
        ];
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
