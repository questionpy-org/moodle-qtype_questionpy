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
use function qtype_questionpy\package_provider;

defined('MOODLE_INTERNAL') || die;

require_once(dirname(__DIR__) . '/data_provider.php');

/**
 * Unit tests for the questionpy package_raw class.
 *
 * @package    qtype_questionpy
 * @copyright  2023 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class package_raw_test extends \advanced_testcase {
    /**
     * Provides valid package data.
     *
     * @return array
     */
    public static function valid_package_data_provider(): array {
        return [
            'Minimal package data' => [
                [
                    'package_hash' => 'hash',
                    'short_name' => 'shortname',
                    'namespace' => 'namespace',
                    'name' => [],
                    'version' => '1.0.0',
                    'type' => 'question',
                ],
            ],
            'Maximal package data' => [
                [
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
                    'tags' => [],
                ],
            ],
            'With tags' => [
                [
                    'package_hash' => 'hash',
                    'short_name' => 'shortname',
                    'namespace' => 'namespace',
                    'name' => [],
                    'version' => '1.0.0',
                    'type' => 'question',
                    'tags' => ['tag1', 'tag2'],
                ],
            ],
            'With one language' => [
                [
                    'package_hash' => 'hash',
                    'short_name' => 'shortname',
                    'namespace' => 'namespace',
                    'name' => ['en' => 'en_name'],
                    'version' => '1.0.0',
                    'type' => 'question',
                    'languages' => ['en'],
                    'description' => ['en' => 'en_description'],
                ],
            ],
            'With multiple languages' => [
                [
                    'package_hash' => 'hash',
                    'short_name' => 'shortname',
                    'namespace' => 'namespace',
                    'name' => ['en' => 'en_name', 'de' => 'de_name', 'fr' => 'fr_name'],
                    'version' => '1.0.0',
                    'type' => 'question',
                    'languages' => ['en', 'de', 'fr'],
                    'description' => ['en' => 'en_description', 'de' => 'de_description', 'fr' => 'fr_description'],
                ],
            ],
            'With multiple languages and tags' => [
                [
                    'package_hash' => 'hash',
                    'short_name' => 'shortname',
                    'namespace' => 'namespace',
                    'name' => ['en' => 'en_name', 'de' => 'de_name', 'fr' => 'fr_name'],
                    'version' => '1.0.0',
                    'type' => 'question',
                    'languages' => ['en', 'de', 'fr'],
                    'description' => ['en' => 'en_description', 'de' => 'de_description', 'fr' => 'fr_description'],
                    'tags' => ['tag1', 'tag2'],
                ],
            ],
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
        array_converter::from_array(package_raw::class, $packagedata);
    }

    /**
     * Tests the method get_package with faulty input.
     *
     * @covers \package::from_array
     * @return void
     * @throws moodle_exception
     */
    public function test_faulty_from_array(): void {
        $this->expectException(moodle_exception::class);
        $faulty = ['faulty' => 'hash'];
        array_converter::from_array(package_raw::class, $faulty);
    }


    /**
     * Tests the store method.
     *
     * @covers \package::store
     * @dataProvider valid_package_data_provider
     * @param array $packagedata
     * @return void
     * @throws moodle_exception
     */
    public function test_store_package($packagedata): void {
        global $DB;
        $this->resetAfterTest();

        // Create and set example user.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $timestamp = time();

        $rawpackage = array_converter::from_array(package_raw::class, $packagedata);
        $rawpackage->store();

        // Check qtype_questionpy_pkgversion table.
        $this->assertEquals(1, $DB->count_records('qtype_questionpy_pkgversion'));
        $record = $DB->get_record('qtype_questionpy_pkgversion', ['hash' => $packagedata['package_hash']]);
        $this->assertNotFalse($record);
        $this->assertEquals($packagedata['version'], $record->version);
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

        // Check qtype_questionpy_tag and qtype_questionpy_pkgtag table.
        $tags = $packagedata['tags'] ?? [];
        $tagscount = count($tags);
        $this->assertEquals($tagscount, $DB->count_records('qtype_questionpy_tag'));
        $this->assertEquals($tagscount, $DB->count_records('qtype_questionpy_pkgtag'));
        foreach ($tags as $tag) {
            $tagid = $DB->get_field('qtype_questionpy_tag', 'id', ['tag' => $tag]);
            $this->assertNotFalse($tagid);

            $record = $DB->get_record('qtype_questionpy_pkgtag', ['packageid' => $packageid, 'tagid' => $tagid]);
            $this->assertNotFalse($record);
        }
    }

    /**
     * Tests that storing same package should not throw an exception and only store it once.
     *
     * @covers \package::store
     * @return void
     * @throws moodle_exception
     */
    public function test_store_package_twice(): void {
        global $DB;
        $this->resetAfterTest();

        $rawpackage = package_provider(['languages' => ['en', 'de'], 'tags' => ['tag_0', 'tag_1']]);
        $rawpackage->store();
        $rawpackage->store();

        $this->assertEquals(1, $DB->count_records('qtype_questionpy_pkgversion'));
        $this->assertEquals(1, $DB->count_records('qtype_questionpy_package'));
        $this->assertEquals(2, $DB->count_records('qtype_questionpy_language'));
        $this->assertEquals(2, $DB->count_records('qtype_questionpy_pkgtag'));
        $this->assertEquals(2, $DB->count_records('qtype_questionpy_tag'));
    }

    /**
     * Tests that storing same package as user should throw an exception.
     *
     * @covers \package::store
     * @return void
     * @throws moodle_exception
     */
    public function test_store_already_existing_package_with_different_hash_throws(): void {
        $this->resetAfterTest();
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('A package with the same version but different hash already exists.');

        $rawpackage1 = package_provider();
        $rawpackage2 = clone $rawpackage1;
        $rawpackage2->hash .= 'faulty';

        $rawpackage1->store();
        $rawpackage2->store();
    }

    /**
     * Tests the method store with multiple versions of the same package.
     *
     * @covers \package::store
     * @return void
     * @throws moodle_exception
     */
    public function test_store_different_versions_of_a_package(): void {
        global $DB;
        $this->resetAfterTest();

        $rawpackage1 = package_provider(['version' => '1.0.0', 'languages' => ['en'], 'tags' => ['tag_0']]);
        $rawpackage2 = package_provider(['version' => '2.0.0', 'languages' => ['en'], 'tags' => ['tag_0']]);

        $rawpackage1->store();
        $rawpackage2->store();

        $this->assertEquals(2, $DB->count_records('qtype_questionpy_pkgversion'));
        $this->assertEquals(1, $DB->count_records('qtype_questionpy_package'));
        $this->assertEquals(1, $DB->count_records('qtype_questionpy_language'));
        $this->assertEquals(1, $DB->count_records('qtype_questionpy_pkgtag'));
        $this->assertEquals(1, $DB->count_records('qtype_questionpy_tag'));
    }
}
