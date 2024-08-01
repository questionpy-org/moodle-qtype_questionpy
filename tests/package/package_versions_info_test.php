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
use function qtype_questionpy\package_versions_info_provider;

defined('MOODLE_INTERNAL') || die;

require_once(dirname(__DIR__) . '/data_provider.php');

/**
 * Unit tests for the questionpy package_versions_info class.
 *
 * @package    qtype_questionpy
 * @copyright  2024 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class package_versions_info_test extends \advanced_testcase {
    /**
     * Adds package versions to the database.
     *
     * @param array $versions
     * @return package_versions_info
     * @throws moodle_exception
     */
    private function get_package_versions_info(array $versions): package_versions_info {
        $prepared = [];
        foreach ($versions as $version) {
            $prepared[] = ['version' => $version];
        }
        return package_versions_info_provider(null, $prepared);
    }

    /**
     * Returns the database records of the package versions.
     *
     * @param package_versions_info $pvi
     * @return array
     * @throws moodle_exception
     */
    private function get_version_records(package_versions_info $pvi): array {
        global $DB;
        [$sql, $params] = $DB->get_in_or_equal(array_column($pvi->versions, 'hash'));
        return $DB->get_records_select('qtype_questionpy_pkgversion', "hash $sql", $params, 'versionorder');
    }

    /**
     * Tests that the package record is modified if the latest version does change.
     *
     * @covers \qtype_questionpy\package\package_version_specific_info::upsert
     * @throws moodle_exception
     */
    public function test_package_data_gets_updated_if_newer_versions_are_added_or_removed(): void {
        global $DB;
        $this->resetAfterTest();

        $versionsarray = [['0.0.1'], ['1.0.0', '0.0.1'], ['0.0.1']];

        foreach ($versionsarray as $time => $versions) {
            $pvi = $this->get_package_versions_info($versions);
            $pvi->upsert($time);

            $record = $DB->get_record('qtype_questionpy_package', ['namespace' => $pvi->manifest->namespace]);
            $this->assertEquals($time, $record->timemodified);
        }

        $this->assertEquals(0, $record->timecreated);
    }

    /**
     * Tests that the package record is not modified if the latest version does not change.
     *
     * @covers \qtype_questionpy\package\package_version_specific_info::upsert
     * @throws moodle_exception
     */
    public function test_package_data_does_not_get_updated_if_only_older_versions_are_added_or_removed(): void {
        global $DB;
        $this->resetAfterTest();

        $versionsarray = [['1.0.0'], ['1.0.0', '0.1.0'], ['1.0.0', '0.0.1'], ['1.0.0']];

        foreach ($versionsarray as $time => $versions) {
            $pvi = $this->get_package_versions_info($versions);
            $pvi->upsert($time);
        }

        $record = $DB->get_record('qtype_questionpy_package', ['namespace' => $pvi->manifest->namespace]);
        $this->assertEquals(0, $record->timecreated);
        $this->assertEquals(0, $record->timemodified);
    }

    /**
     * Tests that the version records are correctly modified if the versions are changed.
     *
     * @covers \qtype_questionpy\package\package_version_specific_info::upsert
     * @throws moodle_exception
     */
    public function test_versions_get_updated_if_there_are_changes(): void {
        global $DB;
        $this->resetAfterTest();

        $expectedtimecreatedmodified = [
            ['0.1.0' => [0, 0]],
            ['1.0.0' => [1, 1], '0.1.0' => [0, 1]],
            ['1.0.0' => [1, 2], '0.1.0' => [0, 2], '0.0.1' => [2, 2]],
            ['2.0.0' => [3, 3]],
        ];

        foreach ($expectedtimecreatedmodified as $time => $expected) {
            $versions = array_keys($expected);
            $pvi = $this->get_package_versions_info($versions);
            $pvi->upsert($time);
            $records = $this->get_version_records($pvi);

            // Check that the versions are sorted correctly/the versionorder-field is set correctly.
            $this->assertEquals($versions, array_column($records, 'version'));
            $this->assertEquals(range(0, count($versions) - 1), array_column($records, 'versionorder'));

            $this->assertEquals(count($versions), $DB->count_records('qtype_questionpy_pkgversion'));

            // Check creation and modification time.
            foreach ($records as $record) {
                [$expectedtimecreated, $expectedtimemodified] = $expected[$record->version];
                $this->assertEquals($expectedtimecreated, $record->timecreated);
                $this->assertEquals($expectedtimemodified, $record->timemodified);
            }
        }
    }

    /**
     * Tests that the version records are not modified if there are no changes.
     *
     * @covers \qtype_questionpy\package\package_version_specific_info::upsert
     * @throws moodle_exception
     */
    public function test_versions_do_not_get_updated_if_there_are_no_changes(): void {
        global $DB;
        $this->resetAfterTest();

        $versions = ['1.0.0', '0.1.0'];

        $pvi = $this->get_package_versions_info($versions);
        $pvi->upsert(0);
        $pvi->upsert(1);

        $this->assertEquals(count($versions), $DB->count_records('qtype_questionpy_pkgversion'));

        // Check creation and modification time.
        $records = $this->get_version_records($pvi);
        foreach ($records as $record) {
            $this->assertEquals(0, $record->timecreated);
            $this->assertEquals(0, $record->timemodified);
        }
    }
}
