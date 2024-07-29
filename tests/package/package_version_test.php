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
use qtype_questionpy\package\package_version;
use function qtype_questionpy\package_provider;

defined('MOODLE_INTERNAL') || die;

require_once(dirname(__DIR__) . '/data_provider.php');

/**
 * Unit tests for the questionpy package_version class.
 *
 * @package    qtype_questionpy
 * @copyright  2023 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class package_version_test extends \advanced_testcase {
    /**
     * Tests the get_by_id method.
     *
     * @covers \package_version::get_by_id
     * @throws moodle_exception
     */
    public function test_get_by_id(): void {
        $this->resetAfterTest();

        // Store a package.
        $hash = 'hash';
        $version = '1.0.0';

        $pkgversionid = package_provider(['hash' => $hash, 'version' => $version])->store();
        $package = package_version::get_by_id($pkgversionid);

        $this->assertEquals($hash, $package->hash);
        $this->assertEquals($version, $package->version);
    }

    /**
     * Tests the get_by_hash method.
     *
     * @covers \package_version::get_by_hash
     * @depends test_get_by_id
     * @throws moodle_exception
     */
    public function test_get_by_hash(): void {
        $this->resetAfterTest();

        // Store a package.
        $hash = 'hash';
        $version = '1.0.0';

        package_provider(['hash' => $hash, 'version' => $version])->store();
        $package = package_version::get_by_hash($hash);

        $this->assertEquals($hash, $package->hash);
        $this->assertEquals($version, $package->version);
    }

    /**
     * Tests the delete method.
     *
     * @covers \package_version::delete
     * @depends test_get_by_id
     * @throws moodle_exception
     */
    public function test_delete(): void {
        global $DB;
        $this->resetAfterTest();

        // Store a package.
        $pkgversionid = package_provider()->store();
        $package = package_version::get_by_id($pkgversionid);

        // Delete the package.
        $package->delete();

        $this->assertEquals(0, $DB->count_records('qtype_questionpy_pkgversion'));
        $this->assertEquals(0, $DB->count_records('qtype_questionpy_package'));
        $this->assertEquals(0, $DB->count_records('qtype_questionpy_language'));
        $this->assertEquals(0, $DB->count_records('qtype_questionpy_pkgtag'));
        $this->assertEquals(0, $DB->count_records('qtype_questionpy_tag'));
    }

    /**
     * Tests the delete method with multiple versions of the same package.
     *
     * @covers \package_version::delete
     * @depends test_get_by_id
     * @throws moodle_exception
     */
    public function test_delete_where_multiple_versions_exist(): void {
        global $DB;
        $this->resetAfterTest();

        // Store two packages.
        $pkgversionid1 = package_provider(['version' => '1.0.0', 'languages' => ['en'], 'tags' => ['tag']])->store();
        $package1 = package_version::get_by_id($pkgversionid1);

        $pkgversionid2 = package_provider(['version' => '2.0.0', 'languages' => ['de'], 'tags' => ['tag']])->store();
        $package2 = package_version::get_by_id($pkgversionid2);

        // Delete the first package.
        $package1->delete();

        $this->assertEquals(1, $DB->count_records('qtype_questionpy_pkgversion'));
        $this->assertEquals(1, $DB->count_records('qtype_questionpy_package'));
        $this->assertEquals(1, $DB->count_records('qtype_questionpy_language'));
        $this->assertEquals(1, $DB->count_records('qtype_questionpy_pkgtag'));
        $this->assertEquals(1, $DB->count_records('qtype_questionpy_tag'));

        // Delete the second package.
        $package2->delete();
        $this->assertEquals(0, $DB->count_records('qtype_questionpy_pkgversion'));
        $this->assertEquals(0, $DB->count_records('qtype_questionpy_package'));
        $this->assertEquals(0, $DB->count_records('qtype_questionpy_language'));
        $this->assertEquals(0, $DB->count_records('qtype_questionpy_pkgtag'));
        $this->assertEquals(0, $DB->count_records('qtype_questionpy_tag'));
    }
}
