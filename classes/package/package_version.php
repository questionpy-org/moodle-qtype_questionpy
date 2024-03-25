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
     * @var int package version id
     */
    public int $id;

    /**
     * @var int package id
     */
    public int $packageid;

    /**
     * @var string package hash
     */
    public string $hash;

    /**
     * @var string package version
     */
    public string $version;

    /**
     * @var bool package was uploaded by user
     */
    public bool $ismine;

    /**
     * @var bool package is provided by the application server
     */
    public bool $istrusted;

    /**
     * Constructs sql fragment used to retrieve package versions.
     *
     * @param string $where
     * @param array $params
     * @return array A list containing the constructed sql fragment and an array of parameters.
     */
    public static function sql_get(string $where = '', array $params = []): array {
        global $USER;
        if (!empty($where)) {
            $where = "WHERE $where";
        }
        $sql = "
            SELECT pv.id, pv.packageid, pv.hash, pv.version,
                   CASE WHEN s1.id IS NULL THEN 0 ELSE 1 END AS ismine,
                   CASE WHEN s2.id IS NULL THEN 0 ELSE 1 END AS istrusted
            FROM {qtype_questionpy_pkgversion} pv
            LEFT JOIN {qtype_questionpy_source} s1
            ON pv.id = s1.pkgversionid AND s1.userid = :userid
            LEFT JOIN {qtype_questionpy_source} s2
            ON pv.id = s2.pkgversionid AND s2.userid IS NULL
            $where
        ";
        return [$sql, array_merge(['userid' => $USER->id], $params)];
    }

    /**
     * Retrieves a package version by its id.
     *
     * @param int $pkgversionid
     * @return package_version
     * @throws moodle_exception
     */
    public static function get_by_id(int $pkgversionid): package_version {
        global $DB;
        [$sql, $params] = self::sql_get('pv.id = :id', ['id' => $pkgversionid]);
        $record = $DB->get_record_sql($sql, $params);
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
        [$sql, $params] = self::sql_get('pv.hash = :hash', ['hash' => $hash]);
        $record = $DB->get_record_sql($sql, $params);
        return array_converter::from_array(self::class, (array) $record);
    }

    /**
     * Retrieves a package version by its package and version string.
     *
     * @param int $packageid
     * @param string $version
     * @return false|package_version
     * @throws moodle_exception
     */
    public static function get_by_package_and_version(int $packageid, string $version) {
        global $DB;
        [$joinsql, $joinparams] = self::sql_get('pv.packageid = :packageid AND pv.version = :version',
            ['packageid' => $packageid, 'version' => $version]);
        $record = $DB->get_record_sql($joinsql, $joinparams);
        if (!$record) {
            return false;
        }
        return array_converter::from_array(self::class, (array) $record);
    }

    /**
     * Retrieves every package provided by the application server.
     *
     * @return package_version[]
     * @throws moodle_exception
     */
    public static function get_by_server(): array {
        global $DB;
        $packages = [];
        [$joinsql, $joinparams] = self::sql_get('s2.id IS NOT NULL');
        $records = $DB->get_records_sql($joinsql, $joinparams);
        foreach ($records as $record) {
            $packages[] = array_converter::from_array(self::class, (array) $record);
        }
        return $packages;
    }

    /**
     * Deletes the package version source from the database.
     *
     * If a package version has only one source, the package version is also deleted.
     * If the package has only one version, the package related data is also deleted.
     *
     * @param bool $asuser
     * @throws moodle_exception
     */
    public function delete(bool $asuser): void {
        global $DB, $USER;

        $transaction = $DB->start_delegated_transaction();

        // Delete a source of the package version.
        if ($asuser) {
            if (!$this->ismine) {
                throw new moodle_exception('');
            }
            $DB->delete_records('qtype_questionpy_source', ['pkgversionid' => $this->id, 'userid' => $USER->id]);
        } else {
            $DB->delete_records('qtype_questionpy_source', ['pkgversionid' => $this->id, 'userid' => null]);
        }

        if ($DB->count_records('qtype_questionpy_source', ['pkgversionid' => $this->id]) > 0) {
            // There are still other sources for the package version.
            return;
        }

        $DB->delete_records('qtype_questionpy_pkgversion', ['packageid' => $this->packageid, 'hash' => $this->hash]);
        if ($DB->count_records('qtype_questionpy_pkgversion', ['packageid' => $this->packageid]) > 0) {
            // There are still other package versions.
            return;
        }

        // Delete package related data.
        $DB->delete_records('qtype_questionpy_language', ['packageid' => $this->packageid]);
        $DB->delete_records('qtype_questionpy_tags', ['packageid' => $this->packageid]);
        $DB->delete_records('qtype_questionpy_package', ['id' => $this->packageid]);

        $transaction->allow_commit();
    }
}
