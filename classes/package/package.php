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

use dml_exception;
use moodle_exception;
use qtype_questionpy\array_converter\array_converter;
use qtype_questionpy\array_converter\converter_config;

defined('MOODLE_INTERNAL') || die;

/**
 * Represents a QuestionPy package stored in the database.
 *
 * It contains the metadata of a package. Each package has at least one {@see package_version}.
 *
 * @package    qtype_questionpy
 * @copyright  2022 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class package extends package_base {

    /**
     * @var int package id
     */
    public int $id;

    /**
     * Constructs package class.
     *
     * @param int $id
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
    public function __construct(int $id, string $shortname, string $namespace, array $name, string $type,
                                ?string $author = null, ?string $url = null, ?array $languages = null,
                                ?array $description = null, ?string $icon = null, ?string $license = null,
                                ?array $tags = null) {
        $this->id = $id;
        parent::__construct(
            $shortname, $namespace, $name, $type, $author, $url, $languages, $description, $icon, $license, $tags
        );
    }

    /**
     * Retrieves each version of the package.
     *
     * @return array
     * @throws moodle_exception
     */
    public function get_version_array(): array {
        global $DB;
        return $DB->get_records('qtype_questionpy_pkgversion', ['packageid' => $this->id]);
    }

    /**
     * Returns the package of the given package version id.
     *
     * @param int $pkgversionid
     * @return package
     * @throws moodle_exception
     */
    public static function get_by_version(int $pkgversionid): package {
        global $DB;
        $packageid = $DB->get_field('qtype_questionpy_pkgversion', 'packageid', ['id' => $pkgversionid]);
        $package = self::get_package_data($packageid);
        return array_converter::from_array(self::class, (array) $package);
    }

    /**
     * Get packages from the db matching given conditions. Note: only conditions stored in the package version table
     * are applicable.
     *
     * @param array|null $conditions
     * @return package[]
     * @throws moodle_exception
     */
    public static function get_records(?array $conditions = null): array {
        global $DB;
        $packages = [];
        $records = $DB->get_records('qtype_questionpy_package', $conditions);
        foreach ($records as $record) {
            $package = self::get_package_data($record->id);
            $packages[] = array_converter::from_array(self::class, (array) $package);
        }
        return $packages;
    }

    /**
     * Get package related data like name, description and tags.
     *
     * @param string $packageid
     * @return object
     * @throws moodle_exception
     */
    private static function get_package_data(string $packageid): object {
        global $DB;
        $package = $DB->get_record('qtype_questionpy_package', ['id' => $packageid]);

        if (!$package) {
            throw new \coding_exception("The requested package with id '{$packageid}' was not found.");
        }

        [$package->languages, $package->name, $package->description] = self::get_language_data($package->id);
        $package->tags = self::get_tag_data($package->id);

        return $package;
    }

    /**
     * Get the records from the qtype_questionpy_language table given the foreign key packageid.
     *
     * @param int $packageid
     * @return array
     * @throws dml_exception
     */
    private static function get_language_data(int $packageid): array {
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
        return [$language, $name, $description];
    }

    /**
     * Get the records from the qtype_questionpy_tags table given the foreign key packageid.
     *
     * @param int $packageid
     * @return array
     * @throws dml_exception
     */
    private static function get_tag_data(int $packageid): array {
        global $DB;
        $tagdata = $DB->get_records('qtype_questionpy_tags', ['packageid' => $packageid]);
        $tags = [];
        foreach ($tagdata as $record) {
            $tags[] = $record->tag;
        }
        return $tags;
    }

    /**
     * Deletes the package and every version from the database.
     *
     * @return void
     * @throws dml_exception
     */
    public function delete(): void {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('qtype_questionpy_pkgversion', ['packageid' => $this->id]);
        $DB->delete_records('qtype_questionpy_language', ['packageid' => $this->id]);
        $DB->delete_records('qtype_questionpy_tags', ['packageid' => $this->id]);
        $DB->delete_records('qtype_questionpy_package', ['id' => $this->id]);
        $transaction->allow_commit();
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
        if ($this->id === $package->id) {
            return [];
        }

        $difference = [];
        $package = (array) $package;

        // Remove id from comparison.
        $self = (array) $this;
        unset($self['id']);

        foreach ($self as $key => $value) {
            if (array_key_exists($key, $package)) {
                if (is_array($value) && is_array($package[$key])) {
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
        ->rename("shortname", "short_name")
        // The DB rows are also read using array_converter, but their columns are named differently to the json fields.
        ->alias("shortname", "shortname");
});
