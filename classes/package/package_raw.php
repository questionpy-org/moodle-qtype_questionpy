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

use moodle_exception;
use qtype_questionpy\array_converter\array_converter;
use qtype_questionpy\array_converter\converter_config;

defined('MOODLE_INTERNAL') || die;

/**
 * Represents a QuestionPy package from a server.
 *
 * It contains metadata about a package version and its package. The raw package can be stored in the database and the
 * data will then be accessible through {@see package} and {@see package_version}.
 *
 * @package    qtype_questionpy
 * @copyright  2023 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class package_raw extends package_base {

    /**
     * @var string package hash
     */
    public string $hash;

    /**
     * @var string package version
     */
    public string $version;

    /**
     * Constructs package class.
     *
     * @param string $hash
     * @param string $shortname
     * @param string $namespace
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
    public function __construct(string $hash, string $shortname, string $namespace, array $name, string $version,
                                string $type, ?string $author = null, ?string $url = null, ?array $languages = null,
                                ?array $description = null, ?string $icon = null, ?string $license = null,
                                ?array $tags = null) {
        $this->hash = $hash;
        $this->version = $version;
        parent::__construct(
            $shortname, $namespace, $name, $type, $author, $url, $languages, $description, $icon, $license, $tags
        );
    }

    /**
     * Persists a package version in the database.
     *
     * @param int $packageid
     * @return int
     * @throws moodle_exception
     */
    private function store_pkgversion(int $packageid, int $timecreated): int {
        global $DB;

        return $DB->insert_record('qtype_questionpy_pkgversion', [
            'packageid' => $packageid,
            'hash' => $this->hash,
            'version' => $this->version,
            'timecreated' => $timecreated,
        ]);
    }

    /**
     * Persists a package in the database.
     *
     * @param int $timestamp
     * @return int
     * @throws moodle_exception
     */
    private function store_package(int $timestamp): int {
        global $DB;

        $packageid = $DB->insert_record('qtype_questionpy_package', [
            'shortname' => $this->shortname,
            'namespace' => $this->namespace,
            'type' => $this->type,
            'author' => $this->author,
            'url' => $this->url,
            'icon' => $this->icon,
            'license' => $this->license,
            'timemodified' => $timestamp,
            'timecreated' => $timestamp,
        ]);

        if ($this->languages) {
            // For each language store the localized package data as a separate record.
            $languagedata = [];
            foreach ($this->languages as $language) {
                $languagedata[] = [
                    'packageid' => $packageid,
                    'language' => $language,
                    'name' => $this->get_localized_name([$language]),
                    'description' => $this->get_localized_description([$language]),
                ];
            }
            $DB->insert_records('qtype_questionpy_language', $languagedata);
        }

        if ($this->tags) {
            // Store each tag with the package id in the tag table.
            $tagsdata = [];
            foreach ($this->tags as $tag) {
                $tagsdata[] = [
                    'packageid' => $packageid,
                    'tag' => $tag,
                ];
            }
            $DB->insert_records('qtype_questionpy_tags', $tagsdata);
        }
        return $packageid;
    }

    /**
     * Persist this package in the database.
     *
     * Localized data is stored in qtype_questionpy_language.
     * Tags are mapped packageid->tag in the table  qtype_questionpy_tags.
     *
     * @return int the ID of the inserted record in the DB
     * @throws moodle_exception
     */
    public function store(): int {
        global $DB;

        $timestamp = time();

        $packageid = $DB->get_field('qtype_questionpy_package', 'id', [
            'shortname' => $this->shortname,
            'namespace' => $this->namespace,
        ]);

        if (!$packageid) {
            // Package does not exist -> add it.
            $transaction = $DB->start_delegated_transaction();
            $packageid = $this->store_package($timestamp);
        } else {
            // Package does already exist - check if the version also exists.
            $pkgversion = package_version::get_by_package_and_version($packageid, $this->version);
            if ($pkgversion) {
                // Package version does already exist.
                if ($pkgversion->hash !== $this->hash) {
                    // Version does not match existing hash.
                    // TODO: decide what to do.
                    throw new moodle_exception('same_version_different_hash_error', 'qtype_questionpy');
                }
                return $pkgversion->id;
            }
            // Package version does not exist.
            $transaction = $DB->start_delegated_transaction();
        }
        // Create package version.
        $pkgversionid = $this->store_pkgversion($packageid, $timestamp);

        $transaction->allow_commit();
        return $pkgversionid;
    }
}

array_converter::configure(package_raw::class, function (converter_config $config) {
    $config
        ->rename("hash", "package_hash")
        ->rename("shortname", "short_name")
        // The DB rows are also read using array_converter, but their columns are named differently to the json fields.
        ->alias("hash", "hash")
        ->alias("shortname", "shortname");
});
