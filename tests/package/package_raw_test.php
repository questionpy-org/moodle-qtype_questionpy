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
}
