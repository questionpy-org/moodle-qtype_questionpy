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
 * Represents a QuestionPy package.
 *
 * @package    qtype_questionpy
 * @copyright  2022 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class package {

    private $packagehash;
    private $shortname;
    private $name;
    private $version;
    private $type;

    private $author;
    private $url;
    private $languages;
    private $description;
    private $icon;
    private $license;
    private $tags;

    public function __construct(string $packagehash, string $shortname, array $name, string $version, string $type,
                                string $author = null, string $url = null, array $languages = null,
                                array $description = null, string $icon = null, string $license = null,
                                array $tags = null) {

        $this->packagehash = $packagehash;
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
        return new self(
            $package['package_hash'],
            $package['short_name'],
            $package['name'],
            $package['version'],
            $package['type'],

            $package['author'],
            $package['url'],
            $package['languages'],
            $package['description'],
            $package['icon'],
            $package['license'],
            $package['tags']
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
            'package_hash' => $this->packagehash,
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
     * Returns package hash.
     *
     * @return string package hash
     */
    public function get_hash(): string {
        return $this->packagehash;
    }

    /**
     * Retrieves the best available localisation of a package property.
     *
     * @param string[] $property
     * @param string[] $languages
     * @return string
     */
    private function get_localized_property(array $property, array $languages): string {
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
