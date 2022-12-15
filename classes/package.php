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

use moodle_exception;
use qtype_questionpy\array_converter\array_converter;
use qtype_questionpy\array_converter\converter_config;

defined('MOODLE_INTERNAL') || die;

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
                                array  $description = null, string $icon = null, string $license = null,
                                array  $tags = null) {
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

    /**
     * Persist this package in the database.
     * Localized data is stored in qtype_questionpy_language.
     * Tags are mapped packageid->tag in the table  qtype_questionpy_tags.
     *
     * @param int $contextid
     * @return void
     * @throws moodle_exception
     */
    public function store_in_db(int $contextid = 0) {
        global $DB;

        // Store the language independent package data.
        $packagedata = [
            "contextid" => $contextid,
            "hash" => $this->hash,
            "shortname" => $this->shortname,
            "version" => $this->version,
            "type" => $this->type,
            "author" => $this->author,
            "url" => $this->url,
            "icon" => $this->icon,
            "license" => $this->license
        ];

        $transaction = $DB->start_delegated_transaction();
        $packageid = $DB->insert_record('qtype_questionpy_package', $packagedata);

        // For each language store the localized package data as a separate record.
        $languagedata = array();
        foreach ($this->languages as $language) {
            $languagedata[] = [
                "packageid" => $packageid,
                "language" => $language,
                "name" => $this->get_localized_property($this->name, [$language]),
                "description" => $this->get_localized_property($this->description, [$language])
            ];
        }

        // Store each tag with the package hash in the tag table.
        $tagsdata = array();
        foreach ($this->tags as $tag) {
            $tagsdata[] = [
                "packageid" => $packageid,
                "tag" => $tag,
            ];
        }

        $DB->insert_records('qtype_questionpy_tags', $tagsdata);
        $DB->insert_records('qtype_questionpy_language', $languagedata);
        $transaction->allow_commit();
    }

    /**
     * Deletes the package including all related data from:
     *  - qtype_questionpy_package
     *  - qtype_questionpy_language
     *  - qtype_questionpy_tags
     *
     * @throws moodle_exception
     */
    public function delete_from_db() {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        $packageid = $DB->get_field('qtype_questionpy_package', 'id', ['hash' => $this->hash]);
        $DB->delete_records('qtype_questionpy_package', ['id' => $packageid]);
        $DB->delete_records('qtype_questionpy_language', ['packageid' => $packageid]);
        $DB->delete_records('qtype_questionpy_tags', ['packageid' => $packageid]);
        $transaction->allow_commit();
    }

    /**
     * Get a specific package by its hash from the db.
     *
     * @param string $hash
     * @return package
     * @throws \dml_exception
     */
    public static function get_record_by_hash(string $hash): package {
        global $DB;
        $package = (array)$DB->get_record('qtype_questionpy_package', ['hash' => $hash]);
        list($language, $name, $description) = self::get_languagedata($package["id"]);
        $tags = self::get_tagdata($package["id"]);
        $temp = [
            'languages' => $language,
            'name' => $name,
            'description' => $description,
            'tags' => $tags
        ];
        $package = array_merge($package, $temp);
        return array_converter::from_array(self::class, $package);
    }

    /**
     * Get packages from the db matching given conditions. Note: only conditions stored in the package table
     * are applicable.
     *
     * @param array $conditions
     * @return array
     * @throws \dml_exception
     */
    public static function get_records(array $conditions = null): array {
        global $DB;
        $records = $DB->get_records('qtype_questionpy_package', $conditions);
        $packages = array();
        foreach ($records as $package) {
            $package = (array)$package;
            list($language, $name, $description) = self::get_languagedata($package["id"]);
            $tags = self::get_tagdata($package["id"]);
            $temp = [
                'languages' => $language,
                'name' => $name,
                'description' => $description,
                'tags' => $tags
            ];
            $package = array_merge($package, $temp);
            $packages[] = array_converter::from_array(self::class, $package);
        }
        return $packages;
    }

    /**
     * Get the records from the qtype_questionpy_language table given the foreign key packageid.
     *
     * @param int $packageid
     * @return array
     * @throws \dml_exception
     */
    private static function get_languagedata(int $packageid) {
        global $DB;
        $languagedata = $DB->get_records('qtype_questionpy_language', ['packageid' => $packageid]);
        $language = [];
        $name = [];
        $description = [];
        foreach ($languagedata as $record) {
            $language[] = $record->language;
            $name[$record->language] = $record->name;
            $description[$record->language] = $record->description;
        }
        return array($language, $name, $description);
    }

    /**
     * Get the records from the qtype_questionpy_tags table given the foreign key packageid.
     *
     * @param int $packageid
     * @return array
     * @throws \dml_exception
     */
    private static function get_tagdata(int $packageid) {
        global $DB;
        $tagdata = $DB->get_records('qtype_questionpy_tags', ['packageid' => $packageid]);
        $tags = [];
        foreach ($tagdata as $record) {
            $tags[] = $record->tag;
        }
        return $tags;
    }

    /**
     * Provides the differences between two packages, i.e. an array with all the parameters which are different in the
     * two objects.
     * When retrieving packages from the DB, the values in the{@see package::$languages} array are sometimes swapped.
     * Comparing equality with == is therefore not sufficient.
     *
     * @param package $package
     * @return array
     */
    public function difference_from(package $package): array {
        $difference = array();
        $package = (array)$package;
        foreach ((array)$this as $key => $value) {
            if (array_key_exists($key, $package)) {
                if (is_array($value)) {
                    $temp = array_diff($value, $package[$key]);
                    if (count($temp)) {
                        $difference[$key] = $temp;
                    }
                } else if ($value != $package[$key]) {
                    $difference[$key] = [$value, $package[$key]];
                }
            } else {
                $difference[$key] = [$value, null];
            }
        }
        return $difference;
    }

    /**
     * Checks if two packages are semantically equal (==).
     *
     * @param package $package
     * @return bool true if equal, false otherwise
     */
    public function equals(package $package): bool {
        return empty($this->difference_from($package));
    }
}

array_converter::configure(package::class, function (converter_config $config) {
    $config
        ->rename("hash", "package_hash")
        ->rename("shortname", "short_name")
        // The DB rows are also read using array_converter, but their columns are named differently to the json fields.
        ->alias("hash", "hash")
        ->alias("shortname", "shortname");
});
