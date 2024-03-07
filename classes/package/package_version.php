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
    public bool $isfromserver;

    /**
     * Constructs sql fragment used to retrieve package versions.
     *
     * Following table aliases are used:
     *  pv (qtype_questionpy_pkgversion),
     *  s  (qtype_questionpy_source),
     *  v  (qtype_questionpy_visibility).
     *
     * Used by {@see get_where} and {@see get_many_where}.
     *
     * @param string $where
     * @param array $params
     * @return array A list containing the constructed sql fragment and an array of parameters.
     */
    public static function sql_get_where(string $where = '', array $params = []): array {
        global $USER;
        if (!empty($where)) {
            $where = "WHERE $where";
        }
        $sql = "
            SELECT pv.id, pv.packageid, pv.hash, pv.version, pv.isfromserver,
                   CASE WHEN s.userid = :currentuserid THEN 1 ELSE 0 END AS ismine
            FROM {qtype_questionpy_pkgversion} pv
            LEFT JOIN {qtype_questionpy_source} s
            ON pv.id = s.pkgversionid
            LEFT JOIN {qtype_questionpy_visibility} v
            ON s.id = v.sourceid
            $where
        ";
        return [$sql, array_merge(['currentuserid' => $USER->id], $params)];
    }

    /**
     *
     * For a list of available table aliases look at {@see sql_get_where}.
     *
     * @param string $where
     * @param array $params
     * @return package_version|null
     * @throws moodle_exception
     */
    public static function get_where(string $where = '', array $params = []): ?package_version {
        global $DB;
        [$sql, $params] = self::sql_get_where($where, $params);
        $record = $DB->get_record_sql($sql, $params);
        if (!$record) {
            return null;
        }
        return array_converter::from_array(self::class, (array) $record);
    }

    /**
     * Retrieves many packages from the database.
     *
     * For a list of available table aliases look at {@see sql_get_where}.
     *
     * @param string $where
     * @param array $params
     * @return package_version[]
     * @throws moodle_exception
     */
    public static function get_many_where(string $where = '', array $params = []): array {
        global $DB;
        [$sql, $params] = self::sql_get_where($where, $params);
        $records = $DB->get_records_sql($sql, $params);
        $packages = [];
        foreach ($records as $record) {
            $packages[] = array_converter::from_array(self::class, (array) $record);
        }
        return $packages;
    }

    /**
     * Retrieves a package version by its id.
     *
     * @param int $pkgversionid
     * @return package_version
     * @throws moodle_exception
     */
    public static function get_by_id(int $pkgversionid): ?package_version {
        return self::get_where('pv.id = :id', ['id' => $pkgversionid]);
    }

    /**
     * Retrieves a package version by its hash.
     *
     * @param string $hash
     * @return package_version
     * @throws moodle_exception
     */
    public static function get_by_hash(string $hash): ?package_version {
        return self::get_where('pv.hash = :hash', ['hash' => $hash]);
    }

    /**
     * Retrieves every package provided by the application server.
     *
     * @return package_version[]
     * @throws moodle_exception
     */
    public static function get_by_server(): array {
        return self::get_many_where('pv.isfromserver = 1');
    }

    /**
     * Deletes the package source from the database as a user.
     *
     * If a package version has only one source, the package version is also deleted.
     * If the package has only one version, the package related data is also deleted.
     *
     * @throws moodle_exception
     */
    public function delete_as_user(): void {
        $this->delete(true);
    }

    /**
     * Deletes the package version source from the database as the server.
     *
     * If a package version has only one source, the package version is also deleted.
     * If the package has only one version, the package related data is also deleted.
     *
     * @throws moodle_exception
     */
    public function delete_as_server(): void {
        $this->delete(false);
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
    private function delete(bool $asuser): void {
        global $DB, $USER;

        $transaction = $DB->start_delegated_transaction();

        // Delete a source of the package version.
        if ($asuser) {
            if (!$this->ismine) {
                $transaction->allow_commit();
                throw new moodle_exception('version_was_not_stored_by_user_error', 'qtype_questionpy');
            }
            $sourceid = $DB->get_field('qtype_questionpy_source', 'id', ['pkgversionid' => $this->id, 'userid' => $USER->id]);
            $DB->delete_records('qtype_questionpy_source', ['pkgversionid' => $this->id, 'userid' => $USER->id]);
            $DB->delete_records('qtype_questionpy_visibility', ['sourceid' => $sourceid]);
        }

        if ($DB->count_records('qtype_questionpy_source', ['pkgversionid' => $this->id]) > 0) {
            // There are still other sources for the package version.
            if ($asuser) {
                $DB->update_record('qtype_questionpy_pkgversion', (object) ['id' => $this->id, 'isfromserver' => 0]);
            }
            $transaction->allow_commit();
            return;
        }

        $DB->delete_records('qtype_questionpy_pkgversion', ['packageid' => $this->packageid, 'hash' => $this->hash]);
        if ($DB->count_records('qtype_questionpy_pkgversion', ['packageid' => $this->packageid]) > 0) {
            // There are still other package versions.
            $transaction->allow_commit();
            return;
        }

        // Delete package related data.
        $DB->delete_records('qtype_questionpy_language', ['packageid' => $this->packageid]);
        $DB->delete_records('qtype_questionpy_tags', ['packageid' => $this->packageid]);
        $DB->delete_records('qtype_questionpy_package', ['id' => $this->packageid]);

    }
}
