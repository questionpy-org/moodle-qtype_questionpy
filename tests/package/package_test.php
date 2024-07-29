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
use function qtype_questionpy\package_provider;

defined('MOODLE_INTERNAL') || die;

require_once(dirname(__DIR__) . '/data_provider.php');

/**
 * Unit tests for the questionpy package class.
 *
 * @package    qtype_questionpy
 * @copyright  2022 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class package_test extends \advanced_testcase {
    /**
     * Tests the method get_by_version.
     *
     * @covers \package::get_by_version
     * @return void
     * @throws moodle_exception
     */
    public function test_get_by_version(): void {
        $this->resetAfterTest();

        // Store a package.
        $rawpackage = package_provider();
        $pkgversionid = $rawpackage->store();

        // Get the package.
        package::get_by_version($pkgversionid);
    }


    /**
     * Asserts that the record counts are equal to the expected counts.
     *
     * @param int $pkgversion
     * @param int $package
     * @param int $language
     * @param int $pkgtag
     * @param int $tag
     * @return void
     * @throws moodle_exception
     */
    private function assert_records_count(int $pkgversion, int $package, int $language, int $pkgtag, int $tag) {
        global $DB;

        $this->assertEquals($pkgversion, $DB->count_records('qtype_questionpy_pkgversion'), 'pkgversion');
        $this->assertEquals($package, $DB->count_records('qtype_questionpy_package'), 'package');
        $this->assertEquals($language, $DB->count_records('qtype_questionpy_language'), 'language');
        $this->assertEquals($pkgtag, $DB->count_records('qtype_questionpy_pkgtag'), 'pkgtag');
        $this->assertEquals($tag, $DB->count_records('qtype_questionpy_tag'), 'tag');
    }

    /**
     * Tests the method delete_from_db.
     *
     * @covers \package::delete
     * @depends test_get_by_version
     * @return void
     * @throws moodle_exception
     */
    public function test_delete(): void {
        $this->resetAfterTest();

        // Store a package.
        $pkgversionid = package_provider(['languages' => ['en', 'de'], 'tags' => ['a']])->store();
        $package = package::get_by_version($pkgversionid);

        // Delete the package.
        $package->delete();
        $this->assert_records_count(0, 0, 0, 0, 0);
    }

    /**
     * Tests the method delete with multiple versions of the same package.
     *
     * @covers \package::delete
     * @depends test_get_by_version
     * @return void
     * @throws moodle_exception
     */
    public function test_delete_with_multiple_versions(): void {
        $this->resetAfterTest();

        // Store two versions of the same package.
        package_provider(['version' => '1.0.0', 'languages' => ['en'], 'tags' => ['a']])->store();
        $pkgversionid = package_provider(['version' => '2.0.0', 'languages' => ['en'], 'tags' => ['a']])->store();
        $package = package::get_by_version($pkgversionid);

        // Delete the package.
        $package->delete();
        $this->assert_records_count(0, 0, 0, 0, 0);
    }

    /**
     * Tests the method delete with multiple versions of the same package.
     *
     * @covers \package::delete
     * @depends test_get_by_version
     * @return void
     * @throws moodle_exception
     */
    public function test_delete_with_multiple_packages(): void {
        $this->resetAfterTest();

        // Store two packages.
        $package1 = package_provider(['namespace' => 'ns1', 'tags' => ['a', 'b']])->store();
        $package1 = package::get_by_version($package1);

        $package2 = package_provider(['namespace' => 'ns2', 'tags' => ['b', 'c']])->store();
        $package2 = package::get_by_version($package2);

        $package1->delete();
        $this->assert_records_count(1, 1, 2, 2, 2);

        $package2->delete();
        $this->assert_records_count(0, 0, 0, 0, 0);
    }

    /**
     * Tests if the difference between two semantically equal packages is empty.
     *
     * @covers \qtype_questionpy\package::difference_from
     * @covers \qtype_questionpy\package::equals
     * @return void
     */
    public function test_difference_from(): void {
        $package1 = new package(0, 'shortname', 'namespace', [], 'type', 'author', 'url', ['en', 'de']);
        $package2 = new package(1, 'shortname', 'namespace', [], 'type', 'author', 'url', ['de', 'en']);

        $difference = $package1->difference_from($package2);
        $this->assertEmpty($difference);
        $this->assertTrue($package1->equals($package2));
    }
}
