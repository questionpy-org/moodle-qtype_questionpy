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

/**
 * Represents a QuestionPy package version stored in the database.
 *
 * It contains the metadata of a package version. A package version belongs to a {@see package}.
 *
 * @package    qtype_questionpy
 * @copyright  2023 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class package_version {

    /**
     * @var string package version id
     */
    public $id;

    /**
     * @var string package id
     */
    public $packageid;

    /**
     * @var string package hash
     */
    public $hash;

    /**
     * @var string package version
     */
    public $version;

    /**
     * Retrieves a package version by its id.
     *
     * @param string $pkgversionid
     * @return package_version
     * @throws moodle_exception
     */
    public static function get_by_id(string $pkgversionid): package_version {
        global $DB;
        $record = $DB->get_record('qtype_questionpy_pkgversion', ['id' => $pkgversionid]);
        return array_converter::from_array(self::class, (array) $record);
    }

    /**
     * Retrieves a package version by its hash.
     *
     * @param string $hash
     * @return package_version
     * @throws moodle_exception
     */
    public static function get_by_hash(string $hash): package_version {
        global $DB;
        $record = $DB->get_record('qtype_questionpy_pkgversion', ['hash' => $hash]);
        return array_converter::from_array(self::class, (array) $record);
    }

    /**
     * Deletes the package version from the database.
     * If the package has only one version, the package related data is also deleted.
     *
     * @throws moodle_exception
     */
    public function delete(): void {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        $versioncount = $DB->count_records('qtype_questionpy_pkgversion', ['packageid' => $this->packageid]);
        $DB->delete_records('qtype_questionpy_pkgversion', ['hash' => $this->hash, 'packageid' => $this->packageid]);

        if ($versioncount === 1) {
            // Only one package version exists, therefore we also delete package related data.
            $DB->delete_records('qtype_questionpy_language', ['packageid' => $this->packageid]);
            $DB->delete_records('qtype_questionpy_tags', ['packageid' => $this->packageid]);
            $DB->delete_records('qtype_questionpy_package', ['id' => $this->packageid]);
        }

        $transaction->allow_commit();
    }
}
