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

/**
 * Unit tests for the search_packages function.
 *
 * @package    qtype_questionpy
 * @copyright  2023 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later */

namespace qtype_questionpy\external;

use external_api;
use moodle_exception;
use function qtype_questionpy\package_provider;

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__DIR__) . '/data_provider.php');

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for {@see search_packages}.
 *
 * @runTestsInSeparateProcesses
 *
 * @package    qtype_questionpy
 * @author     Jan Britz
 * @copyright  2023 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_packages_test extends \externallib_advanced_testcase {

    /**
     * Asserts that count and total are set correctly.
     *
     * @param array $result
     * @param int $count
     * @param int $total
     * @return void
     */
    private function assert_count_and_total(array $result, int $count, int $total): void {
        // Check if correct values are set.
        $this->assertEquals($count, $result['count']);
        $this->assertEquals($total, $result['total']);

        // Check if the amount of packages is equal to $count.
        $this->assertCount($count, $result['packages']);
    }


    /**
     * Test the service with invalid category parameter.
     *
     * @covers \qtype_questionpy\external\search_packages::execute
     * @throws moodle_exception
     */
    public function test_with_invalid_category_value() {
        $this->resetAfterTest();
        $this->expectException(\invalid_parameter_exception::class);
        $expected = implode(', ', search_packages::CATEGORIES);
        $this->expectExceptionMessageMatches("/.*$expected.*/i");
        search_packages::execute('Test query', [], 'allmine', 'desc', 'date', 3, 5);
    }

    /**
     * Test the service with invalid category parameter.
     *
     * @covers \qtype_questionpy\external\search_packages::execute
     * @throws moodle_exception
     */
    public function test_with_invalid_sort_value() {
        $this->resetAfterTest();
        $this->expectException(\invalid_parameter_exception::class);
        $expected = implode(', ', search_packages::SORT);
        $this->expectExceptionMessageMatches("/.*$expected.*/i");
        search_packages::execute('Test query', [], 'all', 'alphabetically', 'desc', 3, 5);
    }

    /**
     * Test the service with invalid order parameter.
     *
     * @covers \qtype_questionpy\external\search_packages::execute
     * @throws moodle_exception
     */
    public function test_with_invalid_order_value(): void {
        $this->resetAfterTest();
        $this->expectException(\invalid_parameter_exception::class);
        $expected = implode(', ', search_packages::ORDER);
        $this->expectExceptionMessageMatches("/.*$expected.*/i");
        search_packages::execute('Test query', [], 'all', 'alpha', 'recentlyused', 3, 5);
    }

    /**
     * Provides valid but unsupported categories.
     *
     * @return array
     */
    public static function category_provider(): array {
        $parameters = [];
        foreach (['lastused', 'favourites', 'mine'] as $category) {
            $parameters[] = [$category];
        }
        return $parameters;
    }

    /**
     * Tests that the service throws an error for valid but unsupported categories.
     *
     * @param string $category
     * @covers \qtype_questionpy\external\search_packages::execute
     * @dataProvider category_provider
     * @return void
     * @throws moodle_exception
     */
    public function test_categories(string $category): void {
        $this->resetAfterTest();
        $this->expectException(\invalid_parameter_exception::class);
        search_packages::execute('Test query', [], $category, 'alpha', 'recentlyused', 3, 5);
    }

    /**
     * Tests the service without available packages.
     *
     * @covers \qtype_questionpy\external\search_packages::execute
     * @throws moodle_exception
     */
    public function test_search_without_filter_returns_every_package(): void {
        $this->resetAfterTest();

        // Execute service.
        $res = search_packages::execute('', [], 'all', 'alpha', 'asc', 1, 0);
        $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);

        $this->assertEqualsCanonicalizing([
            'packages' => [],
            'count' => 0,
            'total' => 0,
        ], $res);
    }

    /**
     * Tests that the service returns every version of a package.
     *
     * @covers \qtype_questionpy\external\search_packages::execute
     * @throws moodle_exception
     */
    public function test_search_respects_package_versions(): void {
        $this->resetAfterTest();

        // Create packages and their versions.
        $totalpackages = 2;
        $totalversions = 3;

        $versions = [];
        for ($i = 0; $i < $totalpackages; $i++) {
            $namespace = "n$i";
            for ($j = 0; $j < $totalversions; $j++) {
                $versionid = package_provider(['namespace' => $namespace, 'version' => "0.$j.0"])->store();
                $versions[$namespace][] = $versionid;
            }
        }

        // Execute service.
        $res = search_packages::execute('', [], 'all', 'alpha', 'asc', $totalpackages, 0);
        $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);

        // Check if every version in DB is returned under the correct package.
        $this->assertCount($totalpackages, $res['packages']);
        foreach ($res['packages'] as $package) {
            $this->assertCount($totalversions, $package['versions']);
            foreach ($package['versions'] as $version) {
                $this->assertContains($version['id'], $versions[$package['namespace']]);
            }
        }

        // The amount of package versions should not change the package count.
        $this->assert_count_and_total($res, $totalpackages, $totalpackages);
    }

    /**
     * Acts as a provider for {@see test_query}.
     *
     * The arguments have the following format:
     * - query
     * - language - to be set for the user
     * - packages - seperated by namespace; containing name and description translations
     * - expected - list of expected packages (namespaces) and the keys of the expected translations [name, description]
     *
     * @return array[]
     */
    public static function query_provider(): array {
        return [
            // Query without any text.
            ['', null, [
                'ns1' => [['en' => 'en: x'], []],
                'ns2' => [['en' => 'en: x'], ['en' => 'en: x']],
            ], ['ns1' => ['en', null], 'ns2' => ['en', 'en']]],
            // Query without match.
            ['match', null, [
                'ns1' => [['en' => 'en: x'], []],
                'ns2' => [['en' => 'en: x'], ['en' => 'en: x']],
            ], []],
            // Query which matches the text.
            ['en: match', null, [
                'ns1' => [['en' => 'en: match'], []],
                'ns2' => [['en' => 'en: x'], ['en' => 'en: match']],
                'ns3' => [['en' => 'en: match'], ['en' => 'en: match']],
            ], ['ns1' => ['en', null], 'ns2' => ['en', 'en'], 'ns3' => ['en', 'en']]],
            // Query with whole word.
            ['match', null, [
                'ns1' => [['en' => 'en: match'], []],
                'ns2' => [['en' => 'en: x'], ['en' => 'en: match']],
                'ns3' => [['en' => 'en: match'], ['en' => 'en: match']],
            ], ['ns1' => ['en', null], 'ns2' => ['en', 'en'], 'ns3' => ['en', 'en']]],
            // Query with part of word.
            ['atc', null, [
                'ns1' => [['en' => 'en: match'], []],
                'ns2' => [['en' => 'en: x'], ['en' => 'en: match']],
                'ns3' => [['en' => 'en: match'], ['en' => 'en: match']],
            ], ['ns1' => ['en', null], 'ns2' => ['en', 'en'], 'ns3' => ['en', 'en']]],
            // Query with different case - query uppercase / text lowercase.
            ['MATCH', null, [
                'ns1' => [['en' => 'en: match'], []],
                'ns2' => [['en' => 'en: x'], ['en' => 'en: match']],
                'ns3' => [['en' => 'en: match'], ['en' => 'en: match']],
            ], ['ns1' => ['en', null], 'ns2' => ['en', 'en'], 'ns3' => ['en', 'en']]],
            // Query with different case - query lowercase / text uppercase.
            ['match', null, [
                'ns1' => [['en' => 'en: MATCH'], []],
                'ns2' => [['en' => 'en: x'], ['en' => 'en: MATCH']],
                'ns3' => [['en' => 'en: MATCH'], ['en' => 'en: MATCH']],
            ], ['ns1' => ['en', null], 'ns2' => ['en', 'en'], 'ns3' => ['en', 'en']]],
            // Query with special characters.
            ['!"§$%&/()=?', null, [
                'ns1' => [['en' => 'en: !"§$%&/()=?'], []],
                'ns2' => [['en' => 'en: x'], ['en' => 'en: !"§$%&/()=?']],
                'ns3' => [['en' => 'en: !"§$%&/()=?'], ['en' => 'en: !"§$%&/()=?']],
            ], ['ns1' => ['en', null], 'ns2' => ['en', 'en'], 'ns3' => ['en', 'en']]],
            // Query with whitespace.
            [" \n\tmatch\n\t ", null, [
                'ns1' => [['en' => 'en: match'], []],
                'ns2' => [['en' => 'en: x'], ['en' => 'en: match']],
                'ns3' => [['en' => 'en: match'], ['en' => 'en: match']],
            ], ['ns1' => ['en', null], 'ns2' => ['en', 'en'], 'ns3' => ['en', 'en']]],
            // Match in fallback language while preferred language does not exist.
            ['match', 'de', [
                'ns1' => [['en' => 'en: match'], []],
                'ns2' => [['en' => 'en: x'], ['en' => 'en: match']],
                'ns3' => [['en' => 'en: match'], ['en' => 'en: match']]
            ], ['ns1' => ['en', null], 'ns2' => ['en', 'en'], 'ns3' => ['en', 'en']]],
            // Match in fallback language while preferred language does exist but with no match.
            ['match', 'de', [
                'ns1' => [['en' => 'en: match', 'de' => 'de: x'], []],
                'ns2' => [['en' => 'en: x'], ['en' => 'en: match', 'de' => 'de: x']],
                'ns3' => [['en' => 'en: match', 'de' => 'de: x'], ['en' => 'en: match', 'de' => 'de: x']]
            ], []],
            // Match in fallback language and preferred language.
            ['match', 'de', [
                'ns1' => [['en' => 'en: match', 'de' => 'de: match'], []],
                'ns2' => [['en' => 'en: x'], ['en' => 'en: match', 'de' => 'de: match']],
                'ns3' => [['en' => 'en: match', 'de' => 'de: match'], ['en' => 'en: match', 'de' => 'de: match']]
            ], ['ns1' => ['de', null], 'ns2' => ['en', 'de'], 'ns3' => ['de', 'de']]],
            // Match in fallback language and preferred language.
            ['match', 'de', [
                'ns1' => [['en' => 'en: match', 'de' => 'de: match'], []],
                'ns2' => [['en' => 'en: x'], ['en' => 'en: match', 'de' => 'de: match']],
                'ns3' => [['en' => 'en: match', 'de' => 'de: match'], ['en' => 'en: match', 'de' => 'de: match']]
            ], ['ns1' => ['de', null], 'ns2' => ['en', 'de'], 'ns3' => ['de', 'de']]],
            // Results must contain every word.
            ['cool match', null, [
                'ns1' => [['en' => 'en: match'], []],
                'ns2' => [['en' => 'en: x'], ['en' => 'en: match']],
                'ns3' => [['en' => 'en: match'], ['en' => 'en: match']],
            ], []],
            ['cool match', null, [
                'ns1' => [['en' => 'en: cool match'], []],
                'ns2' => [['en' => 'en: x'], ['en' => 'en: cool match']],
                'ns3' => [['en' => 'en: cool match'], ['en' => 'en: cool match']],
            ], ['ns1' => ['en', null], 'ns2' => ['en', 'en'], 'ns3' => ['en', 'en']]],
            // The order of the words should not matter.
            ['cool match', null, [
                'ns1' => [['en' => 'en: this match is cool'], []],
                'ns2' => [['en' => 'en: x'], ['en' => 'en: this match is cool']],
                'ns3' => [['en' => 'en: this match is cool'], ['en' => 'en: this match is cool']],
            ], ['ns1' => ['en', null], 'ns2' => ['en', 'en'], 'ns3' => ['en', 'en']]],
            // Ignore words with one character.
            ['! match ?', null, [
                'ns1' => [['en' => 'en: match'], []],
                'ns2' => [['en' => 'en: x'], ['en' => 'en: match']],
                'ns3' => [['en' => 'en: match'], ['en' => 'en: match']],
            ], ['ns1' => ['en', null], 'ns2' => ['en', 'en'], 'ns3' => ['en', 'en']]],
            // Ignore words with one character.
            ['a', null, [
                'ns1' => [['en' => 'en: match'], []],
                'ns2' => [['en' => 'en: x'], ['en' => 'en: match']],
                'ns3' => [['en' => 'en: match'], ['en' => 'en: match']],
            ], ['ns1' => ['en', null], 'ns2' => ['en', 'en'], 'ns3' => ['en', 'en']]],
            // TODO: test for sql injection.
            // TODO: test with parent and child language.
        ];
    }

    /**
     * Tests the service with different queries and languages.
     *
     * @param string $query
     * @param string|null $language
     * @param array $packages
     * @param array $expected
     * @covers \qtype_questionpy\external\search_packages::execute
     * @dataProvider query_provider
     * @return void
     * @throws moodle_exception
     */
    public function test_query(string $query, ?string $language, array $packages, array $expected): void {
        $this->resetAfterTest();

        // Store every package in the database.
        foreach ($packages as $namespace => [$names, $description]) {
            package_provider(['namespace' => $namespace, 'name' => $names, 'description' => $description])->store();
        }

        // Set current language if provided.
        if ($language) {
            set_config('lang', $language);
        }

        // Execute the service.
        $res = search_packages::execute($query, [], 'all', 'alpha', 'asc', count($packages), 0);
        $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);

        // Get every returned namespace and check if it is correct.
        $actualnamespaces = array_column($res['packages'], 'namespace');
        $this->assertEqualsCanonicalizing(array_keys($expected), $actualnamespaces);

        // Check each returned name and description.
        foreach ($res['packages'] as $package) {
            $namespace = $package['namespace'];
            [$namekey, $descriptionkey] = $expected[$namespace];
            [$names, $descriptions] = $packages[$namespace];

            $this->assertEquals($names[$namekey], $package['name']);
            $this->assertEquals($descriptions[$descriptionkey] ?? '', $package['description']);
        }

        $totalpackages = count($expected);
        $this->assert_count_and_total($res, $totalpackages, $totalpackages);
    }

    /**
     * Provides valid package names for.
     *
     * @return array[][]
     */
    public static function names_provider(): array {
        return [
            [[]], // No name.
            [['a']], // Only one name.
            [['a', 'b', 'c', 'd']], // Ordered list of characters.
            [['b', 'c', 'z', 'a']], // Unordered list of characters.
            [['a1a', 'a2a', 'b1a', 'a2b', 'b11']], // Mixed list.
        ];
    }

    /**
     * Tests the alpha-sorting of the service.
     *
     * @param array $names
     * @dataProvider names_provider
     * @covers \qtype_questionpy\external\search_packages::execute
     * @throws moodle_exception
     */
    public function test_alphabetical_sort(array $names): void {
        $this->resetAfterTest();

        $totalpackages = count($names);
        foreach ($names as $name) {
            package_provider(['namespace' => "ns$name", 'name' => ['en' => $name]])->store();
        }

        // Sort the array of names so that we can use it as a reference.
        sort($names, SORT_STRING);

        // Execute service with ascending order.
        $res = search_packages::execute('', [], 'all', 'alpha', 'asc', $totalpackages, 0);
        $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);

        $actualnamespaces = array_column($res['packages'], 'name');
        $this->assertEquals($names, $actualnamespaces);

        // Execute service with descending order.
        $res = search_packages::execute('', [], 'all', 'alpha', 'desc', $totalpackages, 0);
        $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);

        $actualnamespaces = array_column($res['packages'], 'name');
        $this->assertEquals(array_reverse($names), $actualnamespaces);

        $this->assert_count_and_total($res, $totalpackages, $totalpackages);
    }

    /**
     * Tests the date-sorting of the service.
     *
     * @covers \qtype_questionpy\external\search_packages::execute
     * @throws moodle_exception
     */
    public function test_date_sort(): void {
        $this->resetAfterTest();

        // Create multiple packages with different creation times.
        $totalpackages = 3;

        $namespaces = [];
        for ($i = 0; $i < $totalpackages; $i++) {
            $namespaces[] = "ns$i";
            package_provider(['namespace' => "ns$i"])->store();
            $this->waitForSecond();
        }

        // Execute service with ascending order.
        $res = search_packages::execute('', [], 'all', 'date', 'asc', $totalpackages, 0);
        $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);

        // Check that package order is correct.
        $actualnamespaces = array_column($res['packages'], 'namespace');
        $this->assertEquals($namespaces, $actualnamespaces);

        // Execute service with descending order.
        $res = search_packages::execute('', [], 'all', 'date', 'desc', $totalpackages, 0);
        $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);

        // Check that package order is correct.
        $actualnamespaces = array_column($res['packages'], 'namespace');
        $this->assertEquals(array_reverse($namespaces), $actualnamespaces);

        $this->assert_count_and_total($res, $totalpackages, $totalpackages);
    }


    /**
     * Provides total package count and a limit.
     *
     * @return array[]
     */
    public static function total_packages_and_limit_provider(): array {
        return [
            [1, 2], // Fewer packages than limit.
            [6, 2], // Only full pages.
            [7, 3], // Last page has only one item.
            [8, 3], // Last page is missing one item.
            [6, 4], // Last page is half as big as limit.
        ];
    }

    /**
     * Tests the date-sorting of the service.
     *
     * @param int $limit
     * @param int $totalpackages
     * @dataProvider total_packages_and_limit_provider
     * @covers \qtype_questionpy\external\search_packages::execute
     * @throws moodle_exception
     */
    public function test_limit_and_offset(int $limit, int $totalpackages): void {
        $this->resetAfterTest();

        for ($i = 0; $i < $totalpackages; $i++) {
            package_provider(['namespace' => "ns$i"])->store();
        }

        // Calculate the amount of full pages and the size of the page after the last full page.
        $fullpages = intdiv($totalpackages, $limit);
        $lastpagesize = $totalpackages % $limit;

        // Check full pages.
        for ($page = 0; $page < $fullpages; $page++) {
            $res = search_packages::execute('', [], 'all', 'alpha', 'asc', $limit, $page);
            $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);
            $this->assert_count_and_total($res, $limit, $totalpackages);
        }

        // Check first page that is not full.
        $res = search_packages::execute('', [], 'all', 'alpha', 'asc', $limit, $page);
        $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);
        $this->assert_count_and_total($res, $lastpagesize, $totalpackages);

        // Check that next page is empty.
        $res = search_packages::execute('', [], 'all', 'alpha', 'asc', $limit, $page + 1);
        $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);
        $this->assert_count_and_total($res, 0, $totalpackages);
    }

    // TODO: add tests for filtering by tags when localized tags are supported.
}
