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

defined('MOODLE_INTERNAL') || die;

require_once(__DIR__ . '/data_provider.php');

/**
 * Unit tests for the questionpy question type class.
 *
 * @package    qtype_questionpy
 * @copyright  2022 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class package_test extends \advanced_testcase {

    /**
     * Provides valid package data.
     *
     * @return array
     */
    public function valid_package_data_provider(): array {
        return [
            'Minimal package data' => [[
                'package_hash' => 'hash',
                'short_name' => 'shortname',
                'namespace' => 'namespace',
                'name' => [],
                'version' => '1.0.0',
                'type' => 'question',
            ]],
            'Maximal package data' => [[
                'package_hash' => 'hash',
                'short_name' => 'shortname',
                'namespace' => 'namespace',
                'name' => [],
                'version' => '1.0.0',
                'type' => 'question',

                'author' => 'author',
                'url' => 'url',
                'languages' => [],
                'description' => [],
                'icon' => 'icon',
                'license' => 'license',
                'tags' => []
            ]],
            'With tags' => [[
                'package_hash' => 'hash',
                'short_name' => 'shortname',
                'namespace' => 'namespace',
                'name' => [],
                'version' => '1.0.0',
                'type' => 'question',
                'tags' => ['tag1', 'tag2']
            ]],
            'With one language' => [[
                'package_hash' => 'hash',
                'short_name' => 'shortname',
                'namespace' => 'namespace',
                'name' => ['en' => 'en_name'],
                'version' => '1.0.0',
                'type' => 'question',
                'languages' => ['en'],
                'description' => ['en' => 'en_description']
            ]],
            'With multiple languages' => [[
                'package_hash' => 'hash',
                'short_name' => 'shortname',
                'namespace' => 'namespace',
                'name' => ['en' => 'en_name', 'de' => 'de_name', 'fr' => 'fr_name'],
                'version' => '1.0.0',
                'type' => 'question',
                'languages' => ['en', 'de', 'fr'],
                'description' => ['en' => 'en_description', 'de' => 'de_description', 'fr' => 'fr_description']
            ]],
            'With multiple languages and tags' => [[
                'package_hash' => 'hash',
                'short_name' => 'shortname',
                'namespace' => 'namespace',
                'name' => ['en' => 'en_name', 'de' => 'de_name', 'fr' => 'fr_name'],
                'version' => '1.0.0',
                'type' => 'question',
                'languages' => ['en', 'de', 'fr'],
                'description' => ['en' => 'en_description', 'de' => 'de_description', 'fr' => 'fr_description'],
                'tags' => ['tag1', 'tag2']
            ]],

        ];
    }

    /**
     * Tests the method get_package valid input.
     *
     * @dataProvider valid_package_data_provider
     * @covers       \package::from_array
     * @param array $packagedata
     * @return void
     * @throws moodle_exception
     */
    public function test_from_array($packagedata): void {
        array_converter::from_array(package::class, $packagedata);
    }

    /**
     * Tests the method get_package with faulty input.
     *
     * @covers \package::from_array
     * @return void
     * @throws moodle_exception
     */
    public function test_faulty_from_array() {
        $this->expectException(moodle_exception::class);
        $faulty = ['faulty' => 'hash'];
        array_converter::from_array(package::class, $faulty);
    }

    /**
     * Tests the method get_localized_name.
     *
     * @covers \package::get_localized_name
     * @return void
     */
    public function test_get_localized_name(): void {
        $name = ['en' => 'english_name', 'de' => 'german_name', 'fr' => 'french_name'];
        $package = new package('hash', 'shortname', 'default', $name, '1.0.0', 'question');

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
        $package = new package('hash', 'shortname', 'default', $name, '1.0.0', 'question');
        $this->assertEquals($name['de'], $package->get_localized_name($languages));
    }

    /**
     * Tests the method get_localized_description.
     *
     * @covers \package::get_localized_description
     * @return void
     */
    public function test_get_localized_description(): void {
        $description = ['en' => 'english_description', 'de' => 'german_description', 'fr' => 'french_description'];
        $package = new package('hash', 'shortname', 'default', ['en' => 'english_name'], '1.0.0',
            'question', 'author', 'url', [], $description);

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
        $package = new package('hash', 'shortname', 'default', ['en' => 'english_name'], '1.0.0',
            'question', 'author', 'url', [], $description);
        $this->assertEquals($description['de'], $package->get_localized_description($languages));

        // Description is empty.
        $description = [];
        $package = new package('hash', 'shortname', 'default', ['en' => 'english_name'], '1.0.0',
            'question', 'author', 'url', [], $description);
        $this->assertEquals('', $package->get_localized_description($languages));

        // Description is not set.
        $description = null;
        $package = new package('hash', 'shortname', 'default', ['en' => 'english_name'], '1.0.0',
            'question', 'author', 'url', [], $description);
        $this->assertEquals('', $package->get_localized_description($languages));
    }

    /**
     * Tests the method store_in_db.
     *
     * @covers \package::store_in_db
     * @dataProvider valid_package_data_provider
     * @param array $packagedata
     * @return void
     * @throws moodle_exception
     */
    public function test_store_package_in_db($packagedata) {
        global $DB, $USER;
        $this->resetAfterTest();

        // Create and set example user.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $package = array_converter::from_array(package::class, $packagedata);
        $package->store_in_db();

        $timestamp = time();

        // Check qtype_questionpy_pkgversion table.
        $this->assertEquals(1, $DB->count_records('qtype_questionpy_pkgversion'));
        $record = $DB->get_record('qtype_questionpy_pkgversion', ['hash' => $packagedata['package_hash']]);
        $this->assertNotFalse($record);
        $this->assertEquals($packagedata['version'], $record->version);
        $this->assertGreaterThanOrEqual($timestamp, $record->timecreated);
        $this->assertEquals($USER->id, $record->userid);

        $packageid = $record->packageid;

        // Check qtype_questionpy_package table.
        $this->assertEquals(1, $DB->count_records('qtype_questionpy_package'));
        $record = $DB->get_record('qtype_questionpy_package', ['id' => $packageid]);
        $this->assertNotFalse($record);
        $this->assertEquals($packagedata['short_name'], $record->shortname);
        $this->assertEquals($packagedata['namespace'], $record->namespace);
        $this->assertEquals($packagedata['type'], $record->type);
        $this->assertEquals($packagedata['author'] ?? null, $record->author);
        $this->assertEquals($packagedata['url'] ?? null, $record->url);
        $this->assertEquals($packagedata['icon'] ?? null, $record->icon);
        $this->assertEquals($packagedata['license'] ?? null, $record->license);
        $this->assertGreaterThanOrEqual($timestamp, $record->timemodified);
        $this->assertGreaterThanOrEqual($timestamp, $record->timecreated);

        // Check qtype_questionpy_language table.
        $languages = $packagedata['languages'] ?? [];
        $this->assertEquals(count($languages), $DB->count_records('qtype_questionpy_language'));
        foreach ($languages as $language) {
            $record = $DB->get_record('qtype_questionpy_language', ['packageid' => $packageid, 'language' => $language]);
            $this->assertNotFalse($record);
            $this->assertEquals($packagedata['name'][$language], $record->name);
            $this->assertEquals($packagedata['description'][$language], $record->description);
        }

        // Check qtype_questionpy_tags table.
        $tags = $packagedata['tags'] ?? [];
        $this->assertEquals(count($tags), $DB->count_records('qtype_questionpy_tags'));
        foreach ($tags as $tag) {
            $record = $DB->get_record('qtype_questionpy_tags', ['packageid' => $packageid, 'tag' => $tag]);
            $this->assertNotFalse($record);
        }
    }

    /**
     * Tests the method store_in_db when it's called multiple times on the same package.
     *
     * @covers \package::store_in_db
     * @return void
     * @throws moodle_exception
     */
    public function test_store_package_twice_in_db() {
        global $DB;
        $this->resetAfterTest();

        $package = package_provider(['languages' => ['en', 'de'], 'tags' => ['tag_0', 'tag_1']]);
        $package->store_in_db();
        $package->store_in_db();

        $this->assertEquals(1, $DB->count_records('qtype_questionpy_pkgversion'));
        $this->assertEquals(1, $DB->count_records('qtype_questionpy_package'));
        $this->assertEquals(2, $DB->count_records('qtype_questionpy_language'));
        $this->assertEquals(2, $DB->count_records('qtype_questionpy_tags'));
    }

    /**
     * Tests the method store_in_db with multiple versions of the same package.
     *
     * @covers \package::store_in_db
     * @return void
     * @throws moodle_exception
     */
    public function test_store_different_versions_of_package_in_db() {
        global $DB;
        $this->resetAfterTest();

        $package1 = package_provider(['version' => '1.0.0', 'languages' => ['en'], 'tags' => ['tag_0']]);
        $package2 = package_provider(['version' => '2.0.0', 'languages' => ['en'], 'tags' => ['tag_0']]);

        $package1->store_in_db();
        $package2->store_in_db();

        $this->assertEquals(2, $DB->count_records('qtype_questionpy_pkgversion'));
        $this->assertEquals(1, $DB->count_records('qtype_questionpy_package'));
        $this->assertEquals(1, $DB->count_records('qtype_questionpy_language'));
        $this->assertEquals(1, $DB->count_records('qtype_questionpy_tags'));
    }

    /**
     * Tests the method delete_from_db.
     *
     * @covers \package::delete_from_db
     * @depends test_store_package_in_db
     * @return void
     * @throws moodle_exception
     */
    public function test_delete_from_db() {
        global $DB;
        $this->resetAfterTest();

        $package = package_provider(['languages' => ['en', 'de'], 'tags' => ['tag_0']]);
        $package->store_in_db();

        $package->delete_from_db();
        $this->assertEquals(0, $DB->count_records('qtype_questionpy_pkgversion'));
        $this->assertEquals(0, $DB->count_records('qtype_questionpy_package'));
        $this->assertEquals(0, $DB->count_records('qtype_questionpy_language'));
        $this->assertEquals(0, $DB->count_records('qtype_questionpy_tags'));
    }

    /**
     * Tests the method delete_from_db with multiple versions of the same package.
     *
     * @covers \package::delete_from_db
     * @depends test_store_different_versions_of_package_in_db
     * @return void
     * @throws moodle_exception
     */
    public function test_delete_from_db_with_multiple_versions() {
        global $DB;
        $this->resetAfterTest();

        $package1 = package_provider(['version' => '1.0.0', 'languages' => ['en'], 'tags' => ['tag_0']]);
        $package2 = package_provider(['version' => '2.0.0', 'languages' => ['en'], 'tags' => ['tag_0']]);

        $package1->store_in_db();
        $package2->store_in_db();

        $package1->delete_from_db();
        $this->assertEquals(1, $DB->count_records('qtype_questionpy_pkgversion'));
        $this->assertEquals(1, $DB->count_records('qtype_questionpy_package'));
        $this->assertEquals(1, $DB->count_records('qtype_questionpy_language'));
        $this->assertEquals(1, $DB->count_records('qtype_questionpy_tags'));

        $package2->delete_from_db();
        $this->assertEquals(0, $DB->count_records('qtype_questionpy_pkgversion'));
        $this->assertEquals(0, $DB->count_records('qtype_questionpy_package'));
        $this->assertEquals(0, $DB->count_records('qtype_questionpy_language'));
        $this->assertEquals(0, $DB->count_records('qtype_questionpy_tags'));
    }

    /**
     * Adds one package to db, then retrieves it. Tests if the retrieved package is the same as the original.
     *
     * @covers \qtype_questionpy\package::get_record_by_hash
     * @depends test_store_package_in_db
     * @return void
     * @throws moodle_exception
     */
    public function test_storing_and_retrieving_one_package_from_db() {
        $this->resetAfterTest();

        $initial = package_provider();
        $initial->store_in_db();
        [, $final] = package::get_record_by_hash($initial->hash);

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
        $package = package_provider();
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
        $package1 = package_provider(['languages' => ['en', 'de']]);
        $package2 = package_provider(['languages' => ['de', 'en']]);

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
     * @throws moodle_exception
     */
    public function test_get_records() {
        $this->resetAfterTest();

        $package1 = package_provider();
        $package2 = package_provider();
        $package1->store_in_db();
        $package2->store_in_db();

        $packages = package::get_record_by_hash($package1->hash);
        $this->assertTrue($package1->equals($packages[1]));
    }
}
