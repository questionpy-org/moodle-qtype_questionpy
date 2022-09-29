<?php
// This file is part of Moodle - http://moodle.org/
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

namespace qtype_questionpy;

/**
 * Unit Tests for the \qtype_questionpy\package class.
 *
 * @package    qtype_questionpy
 * @copyright  2022 Alexander Schmitz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \qtype_questionpy\package
 */
class package_test extends \advanced_testcase {

    /**
     * Data provider for {@see package}.
     *
     * @return package A sample package for the tests
     */
    public function package_provider1(): package {
        return package::from_array([
            'package_hash' => 'dkZZGAOgHTpBOSZMBGNM',
            'short_name' => 'adAqMNxOZNhuSUWflNui',
            'name' => [
                'en' => 'She piece local.',
                'de' => 'Style important.'
            ],
            'version' => '865.7797993.0--.0',
            'type' => 'questiontype',
            'author' => 'Mario Hunt',
            'url' => 'http://www.kane.com/',
            'languages' => [
                0 => 'en',
                1 => 'de'
            ],
            'description' => [
                'en' => 'en: Activity organization letter. Report alone why center.
                    Real outside glass maintain right hear.
                    Brother develop process work. Build ago north.
                    Develop with defense understand garden recently work.',
                'de' => 'de: Activity few enter medical side position. Safe need no guy price.
                    Source necessary our me series month seven born.
                    Anyone everything interest where accept apply. Expert great significant.'
            ],
            'icon' => 'https://placehold.jp/40e47e/598311/150x150.png',
            'license' => '',
            'tags' => [
                0 => 'fXuprCRqsLnQQYzFZgAt'
            ]
        ]);
    }

    /**
     * Data provider for {@see package}.
     *
     * @return package Same package as {@see package_provider1} but values in languages array are swapped.
     */
    public function package_provider2(): package {
        return package::from_array([
            'package_hash' => 'dkZZGAOgHTpBOSZMBGNM',
            'short_name' => 'adAqMNxOZNhuSUWflNui',
            'name' => [
                'en' => 'She piece local.',
                'de' => 'Style important.'
            ],
            'version' => '865.7797993.0--.0',
            'type' => 'questiontype',
            'author' => 'Mario Hunt',
            'url' => 'http://www.kane.com/',
            'languages' => [
                0 => 'de',
                1 => 'en'
            ],
            'description' => [
                'en' => 'en: Activity organization letter. Report alone why center.
                    Real outside glass maintain right hear.
                    Brother develop process work. Build ago north.
                    Develop with defense understand garden recently work.',
                'de' => 'de: Activity few enter medical side position. Safe need no guy price.
                    Source necessary our me series month seven born.
                    Anyone everything interest where accept apply. Expert great significant.'
            ],
            'icon' => 'https://placehold.jp/40e47e/598311/150x150.png',
            'license' => '',
            'tags' => [
                0 => 'fXuprCRqsLnQQYzFZgAt'
            ]
        ]);
    }

    /**
     * Test if after adding a package to the db, there is indeed one more record present.
     *
     * @covers \qtype_questionpy\package::store_in_db
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_storing_one_package_in_db() {
        global $DB;
        $this->resetAfterTest(true);

        $package = $this->package_provider1();
        $initial = count($DB->get_records('qtype_questionpy_package'));

        $package->store_in_db();
        $final = count($DB->get_records('qtype_questionpy_package'));

        $this->assertEquals(1, $final - $initial);
    }

    /**
     * Adds one Package to db, then retrieves it. Tests if the retrieved package is the same as the original.
     *
     * @covers \qtype_questionpy\package::get_record_by_hash
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_storing_and_retrieving_one_package_from_db() {
        global $DB;
        $this->resetAfterTest(true);

        $initial = $this->package_provider1();
        $initial->store_in_db();
        $final = package::get_record_by_hash($initial->hash);

        $difference = $initial->difference_from($final);
        $this->assertEmpty($difference);
    }

    /**
     * Tests if an error is thrown when a package hash is queried which is not existent in the DB
     *
     * @return void
     */
    public function test_retrieving_nonexistent_package_from_db() {
        global $DB;
        $package = $this->package_provider1();
        try {
            package::get_record_by_hash($package->hash);
            $this->fail('Package from data provider should not be in DB');
        } catch (\Exception $e) {
            return;
        }

    }


    /**
     * Tests if the difference between two semantically equal packages is empty.
     *
     * @covers \qtype_questionpy\package::difference_from
     * @covers \qtype_questionpy\package::equals
     * @return void
     */
    public function test_difference_from() {
        $package1 = $this->package_provider1();
        $package2 = $this->package_provider2();

        $difference = $package1->difference_from($package2);
        $this->assertNotEquals($package1, $package2, "Values in languages array should be swapped.");
        $this->assertEmpty($difference);
        $this->assertTrue($package1->equals($package2));
    }


    /**
     * Stores two packages in the DB.
     * Queries the two packages by the hash and tests if the original package is in the query result.
     *
     * @covers \qtype_questionpy\package::get_records
     * @return void
     * @throws \dml_exception
     */
    public function test_get_records() {
        global $DB;
        $this->resetAfterTest();

        $package1 = $this->package_provider1();
        $package2 = $this->package_provider2();
        $package1->store_in_db();
        $package2->store_in_db();

        $packages = package::get_records(["hash" => "dkZZGAOgHTpBOSZMBGNM"]);
        $this->assertTrue($package1->equals($packages[0]));
    }
}
