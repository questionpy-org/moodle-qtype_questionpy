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
use qtype_questionpy\array_converter\attributes\array_element_class;

/**
 * Represents a QuestionPy package and its versions on the application server.
 *
 * @package    qtype_questionpy
 * @copyright  2024 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class package_versions_info {
    /**
     * @var package_info $manifest
     */
    public package_info $manifest;

    /**
     * @var package_version_specific_info[] $versions
     */
    #[array_element_class(package_version_specific_info::class)]
    public array $versions;

    /**
     * Upserts the package info and versions in the database.
     *
     * Returns an array where the first element is the package id and the second element is an array of version ids sorted by their
     * version order.
     *
     * @param int|null $timestamp
     * @return array
     * @throws moodle_exception
     */
    public function upsert(?int $timestamp = null): array {
        global $DB;

        $timestamp ??= time();

        $packageid = $this->manifest->get_id();
        if ($packageid === false) {
            $packageid = $this->manifest->insert($timestamp);
            $versionids = [];
            foreach ($this->versions as $index => $version) {
                $versionids[] = $this->insert($packageid, $version, order: $index, timestamp: $timestamp);
            }
        } else {
            // Get the latest package version stored in the DB and check if we need to update the package info.
            $latestexisting = $DB->get_field('qtype_questionpy_pkgversion', 'hash', ['packageid' => $packageid,
                'versionorder' => 0]);
            if ($this->versions[0]->hash !== $latestexisting) {
                $this->manifest->update($packageid, $timestamp);
            }
            $versionids = $this->update($packageid, $timestamp);
        }

        return [$packageid, $versionids];
    }

    /**
     * Inserts a package version in the database.
     *
     * @param int $packageid
     * @param package_version_specific_info $version
     * @param int $order
     * @param int $timestamp
     * @return int
     * @throws moodle_exception
     */
    private function insert(int $packageid, package_version_specific_info $version, int $order, int $timestamp): int {
        global $DB;

        return $DB->insert_record('qtype_questionpy_pkgversion', ['packageid' => $packageid,
            'version' => $version->version, 'hash' => $version->hash, 'versionorder' => $order, 'timemodified' => $timestamp,
            'timecreated' => $timestamp], bulk: true);
    }

    /**
     * Updates the package versions of an existing package.
     *
     * @param int $packageid
     * @param int $timestamp
     * @return array
     * @throws moodle_exception
     */
    private function update(int $packageid, int $timestamp): array {
        global $DB;

        $existingrecords = $DB->get_records('qtype_questionpy_pkgversion', ['packageid' => $packageid], 'versionorder', 'hash, id');

        $existing = array_column($existingrecords, 'hash');
        $incoming = array_column($this->versions, 'hash');

        if ($existing === $incoming) {
            // There are no new or missing package versions.
            return array_column($existingrecords, 'id');
        }

        // Delete previously existing package versions.
        $old = array_diff($existing, $incoming);
        if (!empty($old)) {
            [$sql, $params] = $DB->get_in_or_equal($old, SQL_PARAMS_NAMED, 'hashes');
            $params['packageid'] = $packageid;
            $DB->delete_records_select('qtype_questionpy_pkgversion', "packageid = :packageid AND hash $sql", $params);
        }

        // Add new or update previously existing package versions.
        $versionids = [];
        $new = array_flip(array_diff($incoming, $existing));
        foreach ($this->versions as $index => $version) {
            if (isset($new[$version->hash])) {
                $versionids[] = $this->insert($packageid, $version, order: $index, timestamp: $timestamp);
            } else {
                // The get_records(...) returns an array indexed by the first field which we set to be the hash of the version.
                $versionid = $existingrecords[$version->hash]->id;
                $versionids[] = $versionid;
                $DB->update_record('qtype_questionpy_pkgversion', ['id' => $versionid, 'versionorder' => $index,
                    'timemodified' => $timestamp]);
            }
        }
        return $versionids;
    }
}
