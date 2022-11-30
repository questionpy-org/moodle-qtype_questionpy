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

namespace qtype_questionpy;

use moodle_exception;
use qtype_questionpy\array_converter\array_converter;
use TypeError;

defined('MOODLE_INTERNAL') || die;
require(__DIR__ . '/data_provider.php');


/**
 * Unit tests for the questionpy question type class.
 *
 * @package    qtype_questionpy
 * @copyright  2022 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class package_test extends \advanced_testcase {

    /**
     * Tests the function get_package.
     *
     * @covers \package::from_array
     * @return void
     * @throws moodle_exception
     */
    public function test_from_array(): void {
        $minimum = [
            'package_hash' => 'hash',
            'short_name' => 'shortname',
            'name' => [],
            'version' => '1.0.0',
            'type' => 'question',
        ];
        array_converter::from_array(package::class, $minimum);

        $maximum = [
            'package_hash' => 'hash',
            'short_name' => 'shortname',
            'name' => [],
            'version' => '1.0.0',
            'type' => 'question',

            'author' => 42,
            'url' => 'url',
            'languages' => [],
            'description' => [],
            'icon' => 'icon',
            'license' => 'license',
            'tags' => []
        ];
        array_converter::from_array(package::class, $maximum);

        $this->expectException(moodle_exception::class);
        $faulty = ['faulty' => 'hash'];
        array_converter::from_array(package::class, $faulty);
    }

    /**
     * Tests the function get_localized_name.
     *
     * @covers \package::get_localized_name
     * @return void
     */
    public function test_get_localized_name(): void {
        $name = ['en' => 'english_name', 'de' => 'german_name', 'fr' => 'french_name'];
        $package = new package('hash', 'shortname', $name, '1.0.0', 'question');

        // Every language in package exists in preferred language.
        $languages = ['en', 'de', 'fr'];
        $this->assertEquals($name['en'], $package->get_localized_name($languages));

        // Only one language in package exists in preferred language.
        $languages = ['fi', 'eo', 'de'];
        $this->assertEquals($name['de'], $package->get_localized_name($languages));

        // No preferred language exists in package.
        $languages = ['fi', 'eo'];
        $this->assertEquals($name['en'], $package->get_localized_name($languages));

        // Preferred language and fallback language in package does not exist.
        $name = ['de' => 'german_name', 'fr' => 'french_name'];
        $package = new package('hash', 'shortname', $name, '1.0.0', 'question');
        $this->assertEquals($name['de'], $package->get_localized_name($languages));
    }

    /**
     * Tests the function get_localized_description.
     *
     * @covers \package::get_localized_description
     * @return void
     */
    public function test_get_localized_description(): void {
        $description = ['en' => 'english_description', 'de' => 'german_description', 'fr' => 'french_description'];
        $package = new package('hash', 'shortname', ['en' => 'english_name'], '1.0.0', 'question',
                        'author', 'url', [], $description);

        // Every language in package exists in preferred language.
        $languages = ['en', 'de', 'fr'];
        $this->assertEquals($description['en'], $package->get_localized_description($languages));

        // Only one language in package exists in preferred language.
        $languages = ['fi', 'eo', 'de'];
        $this->assertEquals($description['de'], $package->get_localized_description($languages));

        // No preferred language exists in package.
        $languages = ['fi', 'eo'];
        $this->assertEquals($description['en'], $package->get_localized_description($languages));

        // Preferred language and fallback language in package does not exist.
        $description = ['de' => 'german_description', 'fr' => 'french_description'];
        $package = new package('hash', 'shortname', ['en' => 'english_name'], '1.0.0', 'question',
                        'author', 'url', [], $description);
        $this->assertEquals($description['de'], $package->get_localized_description($languages));

        // Description is empty.
        $description = [];
        $package = new package('hash', 'shortname', ['en' => 'english_name'], '1.0.0', 'question',
                        'author', 'url', [], $description);
        $this->assertEquals('', $package->get_localized_description($languages));

        // Description is not set.
        $description = null;
        $package = new package('hash', 'shortname', ['en' => 'english_name'], '1.0.0', 'question',
            'author', 'url', [], $description);
        $this->assertEquals('', $package->get_localized_description($languages));
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

        $package = package_provider1();
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

        $initial = package_provider1();
        $initial->store_in_db();
        list(,$final) = package::get_record_by_hash($initial->hash);

        $difference = $initial->difference_from($final);
        $this->assertEmpty($difference);
    }

    /**
     * Tests if an error is thrown when a package hash is queried which is not existent in the DB
     *
     * @covers \qtype_questionpy\package::get_record_by_hash
     * @return void
     */
    public function test_retrieving_nonexistent_package_from_db() {
        global $DB;
        $package = package_provider1();
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
        $package1 = package_provider1();
        $package2 = package_provider2();

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

        $package1 = package_provider1();
        $package2 = package_provider2();
        $package1->store_in_db();
        $package2->store_in_db();

        $packages = package::get_records(["hash" => "dkZZGAOgHTpBOSZMBGNM"]);
        $this->assertTrue($package1->equals($packages[0]));
    }
}
