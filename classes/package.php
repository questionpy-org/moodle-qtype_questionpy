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

use phpDocumentor\Reflection\Types\This;

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

    /**
     * Persist this package in the database.
     * Localized data is stored in qtype_questionpy_language.
     * Tags are mapped package_id->tag in the table  qtype_questionpy_tags.
     * @param int $questionid
     * @param int $contextid
     * @return void
     * @throws \dml_exception
     */
    public function store_in_db(int $questionid = 0, int $contextid = 0) {
        global $DB;

        // Store the language independent package data.
        $packagedata = [
            "questionid" => $questionid,
            "contextid" => $contextid,
            "package_hash" => $this->hash,
            "short_name" => $this->shortname,
            "version" => $this->version,
            "type" => $this->type,
            "author" => $this->author,
            "url" => $this->url,
            "icon" => $this->icon,
            "license" => $this->license
        ];
        $packageid = $DB->insert_record('qtype_questionpy_package', $packagedata);

        // For each language store the localized package data as a separate record.
        foreach ($this->languages as $language) {
            $languagedata = [
                "package_id" => $packageid,
                "language" => $language,
                "name" => $this->get_localized_property($this->name, [$language]),
                "description" => $this->get_localized_property($this->description, [$language])
            ];
            $DB->insert_record('qtype_questionpy_language', $languagedata);
        }

        // Store each tag with the package hash in the tag table.
        foreach ($this->tags as $tag) {
            $tagsdata = [
                "package_id" => $packageid,
                "tag" => $tag,
            ];
            $DB->insert_record('qtype_questionpy_tags', $tagsdata);
        }
    }

    /**
     * Deletes the package including all related data from:
     *  - qtype_questionpy_package
     *  - qtype_questionpy_language
     *  - qtype_questionpy_tags
     * @return bool
     * @throws \Throwable
     * @throws \coding_exception
     * @throws \dml_transaction_exception
     */
    public function delete_from_db(): boolean {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $packageid = $DB->get_field('qtype_questionpy_package', 'id', ['package_hash' => $this->hash]);
        try {
            $DB->delete_records('qtype_questionpy_package', ['id' => $packageid]);
            $DB->delete_records('qtype_questionpy_language', ['package_id' => $packageid]);
            $DB->delete_records('qtype_questionpy_tags', ['package_id' => $packageid]);
        } catch (\dml_exception $e) {
            $DB->rollback_delegated_transaction($transaction, $e);
            return false;
        }
        $DB->commit_delegated_transaction($transaction);
        return true;
    }

    /**
     * Get a specific package by its hash from the db.
     * @param string $hash
     * @return package
     * @throws \dml_exception
     */
    public static function get_from_db(string $hash): package {
        global $DB;
        $package = (array) $DB->get_record('qtype_questionpy_package', ['package_hash' => $hash]);
        $languagedata = $DB->get_records('qtype_questionpy_language', ['package_id' => $package["id"]]);
        $language = [];
        $name = [];
        $description = [];
        foreach ($languagedata as $record) {
            $language[] = $record->language;
            $name[$record->language] = $record->name;
            $description[$record->language] = $record->description;
        }
        $tagdata = $DB->get_records('qtype_questionpy_tags', ['package_id' => $package["id"]]);
        $tags = [];
        foreach ($tagdata as $record) {
            $tags[] = $record->tag;
        }
        $temp = [
            'languages' => $language,
            'name' => $name,
            'description' => $description,
            'tags' => $tags
        ];
        $package = array_merge($package, $temp);
        return self::from_array($package);
    }

}
