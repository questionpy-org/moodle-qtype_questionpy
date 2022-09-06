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


use TypeError;

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
     */
    public function test_from_array(): void {
        $minimum = [
            'package_hash' => 'hash',
            'short_name' => 'shortname',
            'name' => [],
            'version' => '1.0.0',
            'type' => 'question',
        ];
        package::from_array($minimum);

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
        package::from_array($maximum);

        $this->expectException(TypeError::class);
        $faulty = ['faulty' => 'hash'];
        package::from_array($faulty);
    }

    /**
     * Tests the function as_localized_array.
     *
     * @coversNothing
     * @return void
     */
    public function test_as_localized_array(): void {
        // TODO: implement.
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
        $preferred = ['en', 'de', 'fr'];
        $this->assertEquals($name['en'], $package->get_localized_name($preferred));

        // Only one language in package exists in preferred language.
        $preferred = ['fi', 'eo', 'de'];
        $this->assertEquals($name['de'], $package->get_localized_name($preferred));

        // No preferred language exists in package.
        $preferred = ['fi', 'eo'];
        $this->assertEquals($name['en'], $package->get_localized_name($preferred));

        // Preferred language and fallback language in package does not exist.
        $name = ['de' => 'german_name', 'fr' => 'french_name'];
        $package = new package('hash', 'shortname', $name, '1.0.0', 'question');
        $this->assertEquals($name['de'], $package->get_localized_name($preferred));
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
        $preferred = ['en', 'de', 'fr'];
        $this->assertEquals($description['en'], $package->get_localized_description($preferred));

        // Only one language in package exists in preferred language.
        $preferred = ['fi', 'eo', 'de'];
        $this->assertEquals($description['de'], $package->get_localized_description($preferred));

        // No preferred language exists in package.
        $preferred = ['fi', 'eo'];
        $this->assertEquals($description['en'], $package->get_localized_description($preferred));

        // Preferred language and fallback language in package does not exist.
        $description = ['de' => 'german_description', 'fr' => 'french_description'];
        $package = new package('hash', 'shortname', ['en' => 'english_name'], '1.0.0', 'question',
                        'author', 'url', [], $description);
        $this->assertEquals($description['de'], $package->get_localized_description($preferred));

        // Description is empty.
        $description = [];
        $package = new package('hash', 'shortname', ['en' => 'english_name'], '1.0.0', 'question',
                        'author', 'url', [], $description);
        $this->assertEquals('', $package->get_localized_description($preferred));

        // Description is not set.
        $description = null;
        $package = new package('hash', 'shortname', ['en' => 'english_name'], '1.0.0', 'question',
            'author', 'url', [], $description);
        $this->assertEquals('', $package->get_localized_description($preferred));
    }
}
