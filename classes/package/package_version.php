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
use qtype_questionpy\last_used_service;

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
     * Constructs sql fragment used to retrieve package versions.
     *
     * @param string $where
     * @param array $params
     * @return array A list containing the constructed sql fragment and an array of parameters.
     */
    public static function sql_get(string $where = '', array $params = []): array {
        if (!empty($where)) {
            $where = "WHERE $where";
        }
        $sql = "
            SELECT id, packageid, hash, version
            FROM {qtype_questionpy_pkgversion}
            $where
            ORDER BY versionorder
        ";
        return [$sql, $params];
    }

    /**
     * Retrieves a package version from the database.
     *
     * @param string $where
     * @param array $params
     * @return package_version|null
     * @throws moodle_exception
     */
    public static function get(string $where = '', array $params = []): ?package_version {
        global $DB;
        [$sql, $params] = self::sql_get($where, $params);
        $record = $DB->get_record_sql($sql, $params);
        if ($record === false) {
            return null;
        }
        return array_converter::from_array(self::class, (array) $record);
    }

    /**
     * Retrieves many package versions from the database.
     *
     * @param string $where
     * @param array $params
     * @return package_version[]
     * @throws moodle_exception
     */
    public static function get_many(string $where = '', array $params = []): array {
        global $DB;
        $packages = [];
        [$sql, $params] = self::sql_get($where, $params);
        $records = $DB->get_records_sql($sql, $params);
        foreach ($records as $record) {
            $packages[] = array_converter::from_array(self::class, (array) $record);
        }
        return $packages;
    }

    /**
     * Retrieves a package version by its id.
     *
     * @param int $pkgversionid
     * @return package_version|null
     * @throws moodle_exception
     */
    public static function get_by_id(int $pkgversionid): ?package_version {
        return self::get('id = :id', ['id' => $pkgversionid]);
    }

    /**
     * Retrieves a package version by its hash.
     *
     * @param string $hash
     * @return package_version|null
     * @throws moodle_exception
     */
    public static function get_by_hash(string $hash): ?package_version {
        return self::get('hash = :hash', ['hash' => $hash]);
    }

    /**
     * Retrieves a package version by its package and version string.
     *
     * @param int $packageid
     * @param string $version
     * @return package_version|null
     * @throws moodle_exception
     */
    public static function get_by_package_and_version(int $packageid, string $version): ?package_version {
        return self::get('packageid = :packageid AND version = :version', ['packageid' => $packageid, 'version' => $version]);
    }

    /**
     * Deletes the package version from the database.
     *
     * If the package has only one version, the package related data is also deleted.
     *
     * @throws moodle_exception
     */
    public function delete(): void {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        $DB->delete_records('qtype_questionpy_pkgversion', ['packageid' => $this->packageid, 'hash' => $this->hash]);
        if ($DB->count_records('qtype_questionpy_pkgversion', ['packageid' => $this->packageid]) > 0) {
            // There are still other package versions.
            return;
        }

        // Delete package related data.
        $DB->delete_records('qtype_questionpy_language', ['packageid' => $this->packageid]);
        $DB->delete_records('qtype_questionpy_pkgtag', ['packageid' => $this->packageid]);
        $DB->execute("
            DELETE
            FROM {qtype_questionpy_tag}
            WHERE id NOT IN (
                SELECT tagid
                FROM {qtype_questionpy_pkgtag}
            )
        ");
        $DB->delete_records('qtype_questionpy_package', ['id' => $this->packageid]);

        // Remove the package from the last used table.
        last_used_service::remove_by_package($this->packageid);

        $transaction->allow_commit();
    }
}
