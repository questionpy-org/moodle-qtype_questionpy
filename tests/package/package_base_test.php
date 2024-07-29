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

/**
 * Unit tests for the questionpy package_base class.
 *
 * @package    qtype_questionpy
 * @copyright  2023 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class package_base_test extends \advanced_testcase {
    /**
     * Tests the method get_localized_name.
     *
     * @covers \package::get_localized_name
     * @return void
     */
    public function test_get_localized_name(): void {
        $name = ['en' => 'english_name', 'de' => 'german_name', 'fr' => 'french_name'];
        $package = new package_base('shortname', 'default', $name, 'question');

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
        $package = new package_base('shortname', 'default', $name, 'question');
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
        $package = new package_base(
            'shortname',
            'default',
            ['en' => 'english_name'],
            'question',
            'author',
            'url',
            [],
            $description
        );

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
        $package = new package_base(
            'shortname',
            'default',
            ['en' => 'english_name'],
            'question',
            'author',
            'url',
            [],
            $description
        );
        $this->assertEquals($description['de'], $package->get_localized_description($languages));

        // Description is empty.
        $description = [];
        $package = new package_base(
            'shortname',
            'default',
            ['en' => 'english_name'],
            'question',
            'author',
            'url',
            [],
            $description
        );
        $this->assertEquals('', $package->get_localized_description($languages));

        // Description is not set.
        $description = null;
        $package = new package_base(
            'shortname',
            'default',
            ['en' => 'english_name'],
            'question',
            'author',
            'url',
            [],
            $description
        );
        $this->assertEquals('', $package->get_localized_description($languages));
    }
}
