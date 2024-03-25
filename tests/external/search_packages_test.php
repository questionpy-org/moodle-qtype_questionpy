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

use context_course;
use context_user;
use core_favourites\local\service\user_favourite_service;
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
     * This method is called before each test.
     */
    public function setUp(): void {
        $this->resetAfterTest();
        $this->setGuestUser();
    }

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
     * Test that the user needs to be logged in.
     *
     * @covers \qtype_questionpy\external\search_packages::execute
     * @throws moodle_exception
     */
    public function test_user_needs_to_be_logged_in(): void {
        $this->setUser();
        $this->expectException(\require_login_exception::class);
        search_packages::execute('Test query', [], 'allmine', 'desc', 'date', 3, 5, null);
    }

    /**
     * Test the service with invalid category parameter.
     *
     * @covers \qtype_questionpy\external\search_packages::execute
     * @throws moodle_exception
     */
    public function test_with_invalid_category_value(): void {
        $this->expectException(\invalid_parameter_exception::class);
        $expected = implode(', ', search_packages::CATEGORIES);
        $this->expectExceptionMessageMatches("/.*$expected.*/i");
        search_packages::execute('Test query', [], 'allmine', 'desc', 'date', 3, 5, null);
    }

    /**
     * Test the service with invalid category parameter.
     *
     * @covers \qtype_questionpy\external\search_packages::execute
     * @throws moodle_exception
     */
    public function test_with_invalid_sort_value(): void {
        $this->expectException(\invalid_parameter_exception::class);
        $expected = implode(', ', search_packages::SORT);
        $this->expectExceptionMessageMatches("/.*$expected.*/i");
        search_packages::execute('Test query', [], 'all', 'alphabetically', 'desc', 3, 5, null);
    }

    /**
     * Test the service with invalid order parameter.
     *
     * @covers \qtype_questionpy\external\search_packages::execute
     * @throws moodle_exception
     */
    public function test_with_invalid_order_value(): void {
        $this->expectException(\invalid_parameter_exception::class);
        $expected = implode(', ', search_packages::ORDER);
        $this->expectExceptionMessageMatches("/.*$expected.*/i");
        search_packages::execute('Test query', [], 'all', 'alpha', 'recentlyused', 3, 5, null);
    }

    /**
     * Provides invalid limits.
     *
     * @return array[]
     */
    public static function invalid_limit_provider(): array {
        return [
            [PHP_INT_MIN],
            [-1],
            [0],
            [101],
            [PHP_INT_MAX],
        ];
    }

    /**
     * Test the service with invalid limit parameters.
     *
     * @param int $limit
     * @covers \qtype_questionpy\external\search_packages::execute
     * @dataProvider invalid_limit_provider
     * @throws moodle_exception
     */
    public function test_with_invalid_limit(int $limit): void {
        $this->expectException(\invalid_parameter_exception::class);
        $this->expectExceptionMessageMatches('/.*1 to 100.*/');
        search_packages::execute('Test query', [], 'all', 'alpha', 'asc', $limit, 5, null);
    }

    /**
     * Provides invalid page values.
     *
     * @return array[]
     */
    public static function invalid_page_value_provider(): array {
        return [
            [PHP_INT_MIN],
            [-1],
        ];
    }

    /**
     * Test the service with invalid limit parameters.
     *
     * @param int $page
     * @covers \qtype_questionpy\external\search_packages::execute
     * @dataProvider invalid_page_value_provider
     * @throws moodle_exception
     */
    public function test_with_invalid_page_value(int $page): void {
        $this->expectException(\invalid_parameter_exception::class);
        $this->expectExceptionMessageMatches("/.*can not be negative.*/");
        search_packages::execute('Test query', [], 'all', 'alpha', 'asc', 1, $page, null);
    }

    /**
     * Tests the service without available packages.
     *
     * @covers \qtype_questionpy\external\search_packages::execute
     * @throws moodle_exception
     */
    public function test_search_without_available_packages(): void {
        // Execute service.
        $res = search_packages::execute('', [], 'all', 'alpha', 'asc', 1, 0, null);
        $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);

        $this->assertEqualsCanonicalizing([
            'packages' => [],
            'count' => 0,
            'total' => 0,
        ], $res);
    }

    /**
     * Acts as a provider for {@see test_user_and_server_versions_get_returned}.
     *
     * @return array[]
     */
    public static function as_user_provider(): array {
        return [
            [true],
            [false],
        ];
    }

    /**
     * Tests that packages uploaded by the user and packages retrieved by the server are returned by the service.
     *
     * @param bool $asuser Whether the packages were uploaded as user or not.
     * @covers \qtype_questionpy\external\search_packages::execute
     * @dataProvider as_user_provider
     * @throws moodle_exception
     */
    public function test_user_and_server_versions_get_returned(bool $asuser): void {
        // Create packages and their versions.
        $totalpackages = 2;
        $totalversions = 3;

        $versions = [];
        for ($i = 0; $i < $totalpackages; $i++) {
            $namespace = "n$i";
            for ($j = 0; $j < $totalversions; $j++) {
                $versionid = package_provider(['namespace' => $namespace, 'version' => "0.$j.0"])->store($asuser);
                $versions[$namespace][] = $versionid;
            }
        }

        // Execute service.
        $res = search_packages::execute('', [], 'all', 'alpha', 'asc', $totalpackages, 0, null);
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
            // Query containing only seperated chars.
            ['q p y', null, [
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
                'ns3' => [['en' => 'en: match'], ['en' => 'en: match']],
            ], ['ns1' => ['en', null], 'ns2' => ['en', 'en'], 'ns3' => ['en', 'en']]],
            // Match in fallback language while preferred language does exist but with no match.
            ['match', 'de', [
                'ns1' => [['en' => 'en: match', 'de' => 'de: x'], []],
                'ns2' => [['en' => 'en: x'], ['en' => 'en: match', 'de' => 'de: x']],
                'ns3' => [['en' => 'en: match', 'de' => 'de: x'], ['en' => 'en: match', 'de' => 'de: x']],
            ], []],
            // Match in fallback language and preferred language.
            ['match', 'de', [
                'ns1' => [['en' => 'en: match', 'de' => 'de: match'], []],
                'ns2' => [['en' => 'en: x'], ['en' => 'en: match', 'de' => 'de: match']],
                'ns3' => [['en' => 'en: match', 'de' => 'de: match'], ['en' => 'en: match', 'de' => 'de: match']],
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
     * @throws moodle_exception
     */
    public function test_query(string $query, ?string $language, array $packages, array $expected): void {
        global $USER;

        // Store every package in the database.
        foreach ($packages as $namespace => [$names, $description]) {
            package_provider(['namespace' => $namespace, 'name' => $names, 'description' => $description])->store(true);
        }

        // Set current language if provided.
        if ($language) {
            $USER->lang = $language;
        }

        // Execute the service.
        $res = search_packages::execute($query, [], 'all', 'alpha', 'asc', count($packages), 0, null);
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
        $totalpackages = count($names);
        foreach ($names as $name) {
            package_provider(['namespace' => "ns$name", 'name' => ['en' => $name]])->store(true);
        }

        // Sort the array of names so that we can use it as a reference.
        sort($names, SORT_STRING);

        // The smallest valid limit is one.
        $limit = max($totalpackages, 1);

        // Execute service with ascending order.
        $res = search_packages::execute('', [], 'all', 'alpha', 'asc', $limit, 0, null);
        $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);

        $actualnamespaces = array_column($res['packages'], 'name');
        $this->assertEquals($names, $actualnamespaces);

        // Execute service with descending order.
        $res = search_packages::execute('', [], 'all', 'alpha', 'desc', $limit, 0, null);
        $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);

        $actualnamespaces = array_column($res['packages'], 'name');
        $this->assertEquals(array_reverse($names), $actualnamespaces);

        $this->assert_count_and_total($res, $totalpackages, $totalpackages);
    }

    /**
     * Modifies the creation time of a package given a package version id and the requested creation time.
     *
     * @param int $pkgversionid
     * @param int $timecreated
     * @throws moodle_exception
     */
    private static function modify_package_creation_time(int $pkgversionid, int $timecreated): void {
        global $DB;
        $packageid = $DB->get_field('qtype_questionpy_pkgversion', 'packageid', ['id' => $pkgversionid]);
        $update = ['id' => $packageid, 'timecreated' => $timecreated];
        $DB->update_record('qtype_questionpy_package', (object) $update, true);
    }

    /**
     * Provides categories of which the date-sorting is done via the `timecreated`-field.
     *
     * @return array[]
     */
    public static function category_provider(): array {
        return [
            ['all'],
            ['custom'],
        ];
    }
    /**
     * Tests the date-sorting of the service.
     *
     * A test for the `recentlyused`-category can be found at {@see test_date_sort_with_recentlyused_category}.
     *
     * @param string $category
     * @dataProvider category_provider
     * @covers \qtype_questionpy\external\search_packages::execute
     * @throws moodle_exception
     */
    public function test_date_sort(string $category): void {
        // Create multiple packages with different creation times.
        $totalpackages = 3;

        $namespaces = [];
        for ($i = 0; $i < $totalpackages; $i++) {
            $namespaces[] = "ns$i";
            $pkgversionid = package_provider(['namespace' => "ns$i"])->store(true);
            $this->modify_package_creation_time($pkgversionid, $i);
        }

        // Expected values per sorting order.
        $expected = [
            'asc' => $namespaces,
            'desc' => array_reverse($namespaces),
        ];

        foreach ($expected as $order => $expectednamespaces) {
            $res = search_packages::execute('', [], $category, 'date', $order, $totalpackages, 0, null);
            $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);

            // Check that package order is correct.
            $actualnamespaces = array_column($res['packages'], 'namespace');
            $this->assertEquals($expectednamespaces, $actualnamespaces);

            $this->assert_count_and_total($res, $totalpackages, $totalpackages);
        }
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
        for ($i = 0; $i < $totalpackages; $i++) {
            package_provider(['namespace' => "ns$i"])->store(true);
        }

        // Calculate the amount of full pages and the size of the page after the last full page.
        $fullpages = intdiv($totalpackages, $limit);
        $lastpagesize = $totalpackages % $limit;

        // Check full pages.
        for ($page = 0; $page < $fullpages; $page++) {
            $res = search_packages::execute('', [], 'all', 'alpha', 'asc', $limit, $page, null);
            $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);
            $this->assert_count_and_total($res, $limit, $totalpackages);
        }

        // Check first page that is not full.
        $res = search_packages::execute('', [], 'all', 'alpha', 'asc', $limit, $fullpages, null);
        $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);
        $this->assert_count_and_total($res, $lastpagesize, $totalpackages);

        // Check that next page is empty.
        $res = search_packages::execute('', [], 'all', 'alpha', 'asc', $limit, $fullpages + 1, null);
        $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);
        $this->assert_count_and_total($res, 0, $totalpackages);
    }

    /**
     * Adds a package to the last used table.
     *
     * TODO: use appropriate {@see package}-method to do this when available.
     *
     * @param int $pkgversionid
     * @param int $contextid
     * @param int $timeused
     * @throws moodle_exception
     */
    public static function add_last_used_entry(int $pkgversionid, int $contextid, int $timeused = 0): void {
        global $DB;
        $packageid = $DB->get_field('qtype_questionpy_pkgversion', 'packageid', ['id' => $pkgversionid], MUST_EXIST);
        $DB->insert_record('qtype_questionpy_lastused', [
            'contextid' => $contextid,
            'packageid' => $packageid,
            'timeused' => $timeused,
        ]);
    }

    /**
     * Tests that a context id must be provided if the category is `recentlyused`.
     *
     * @covers \qtype_questionpy\external\search_packages::execute
     * @throws moodle_exception
     */
    public function test_recently_used_requires_context_id_to_be_set(): void {
        $this->expectException(\invalid_parameter_exception::class);
        $this->expectExceptionMessageMatches('/context id must be provided/');
        search_packages::execute('Test query', [], 'recentlyused', 'alpha', 'asc', 1, 0, null);
    }

    /**
     * Tests that a context id must be valid.
     *
     * @covers \qtype_questionpy\external\search_packages::execute
     * @throws moodle_exception
     */
    public function test_recently_used_requires_context_to_be_valid(): void {
        $this->expectException(\invalid_parameter_exception::class);
        $this->expectExceptionMessageMatches('/Context does not exist/');
        search_packages::execute('Test query', [], 'recentlyused', 'alpha', 'asc', 1, 0, -1);
    }

    /**
     * Tests the date-sorting of the service with the `recentylused`-category.
     *
     * The data should be sorted according to the `timeused`-field and not the `timecreated`-field.
     *
     * @covers \qtype_questionpy\external\search_packages::execute
     * @throws moodle_exception
     */
    public function test_date_sort_with_recentlyused_category(): void {
        global $PAGE;

        // Create multiple packages with different creation times.
        $totalpackages = 3;

        $namespaces = [];
        $ids = [];
        for ($i = 0; $i < $totalpackages; $i++) {
            $namespaces[] = "ns$i";
            $pkgversionid = package_provider(['namespace' => "ns$i"])->store(true);
            $ids[] = $pkgversionid;
            $this->modify_package_creation_time($pkgversionid, $i);
        }

        // Add packages in a different order to the last used table.
        $indices = [0, 2, 1];
        $reorderednamespaces = [];
        foreach ($indices as $timeused => $index) {
            $reorderednamespaces[] = $namespaces[$index];
            self::add_last_used_entry($ids[$index], $PAGE->context->id, $timeused);
        }

        // Expected values per sorting order.
        $expected = [
            'asc' => $reorderednamespaces,
            'desc' => array_reverse($reorderednamespaces),
        ];

        foreach ($expected as $order => $expectednamespaces) {
            $res = search_packages::execute('', [], 'recentlyused', 'date', $order, $totalpackages, 0, $PAGE->context->id);
            $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);

            // Check that package order is correct.
            $actualnamespaces = array_column($res['packages'], 'namespace');
            $this->assertEquals($expectednamespaces, $actualnamespaces);

            $this->assert_count_and_total($res, $totalpackages, $totalpackages);
        }
    }

    /**
     * Tests that the service works with the recentlyused-category even if no packages are recently used.
     *
     * @covers \qtype_questionpy\external\search_packages::execute
     * @throws moodle_exception
     * */
    public function test_recently_used_with_no_recently_used_packages(): void {
        // Create and set user.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Create a course and enrol user.
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = context_course::instance($course->id);
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // Execute service.
        $res = search_packages::execute('', [], 'recentlyused', 'alpha', 'asc', 1, 0, $coursecontext->id);
        $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);
        $this->assert_count_and_total($res, 0, 0);
    }

    /**
     * Tests that recently used packages are not available across different courses.
     *
     * @covers \qtype_questionpy\external\search_packages::execute
     * @return void
     * @throws moodle_exception
     */
    public function test_recently_used_package_not_available_across_different_courses(): void {
        // Create and set user.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Create two courses and enrol user.
        $course1 = $this->getDataGenerator()->create_course();
        $course1context = context_course::instance($course1->id);
        $this->getDataGenerator()->enrol_user($user->id, $course1->id);

        $course2 = $this->getDataGenerator()->create_course();
        $course2context = context_course::instance($course2->id);
        $this->getDataGenerator()->enrol_user($user->id, $course2->id);

        // Create package in one course and use it.
        $id1 = package_provider(['namespace' => 'ns1'])->store(true);
        self::add_last_used_entry($id1, $course1context->id);
        $id2 = package_provider(['namespace' => 'ns2'])->store(true);
        self::add_last_used_entry($id2, $course2context->id);

        // Execute service with both context ids.
        $res = search_packages::execute('', [], 'recentlyused', 'alpha', 'asc', 1, 0, $course1context->id);
        $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);
        $this->assert_count_and_total($res, 1, 1);
        $this->assertEquals('ns1', $res['packages'][0]['namespace']);

        $res = search_packages::execute('', [], 'recentlyused', 'alpha', 'asc', 1, 0, $course2context->id);
        $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);
        $this->assert_count_and_total($res, 1, 1);
        $this->assertEquals('ns2', $res['packages'][0]['namespace']);
    }

    /**
     * Tests that if only server packages exist, no package will be returned.
     *
     * @covers \qtype_questionpy\external\search_packages::execute
     * @return void
     * @throws moodle_exception
     */
    public function test_custom_with_only_server_packages_returns_nothing(): void {
        package_provider()->store(false);
        $res = search_packages::execute('', [], 'custom', 'alpha', 'asc', 1, 0, null);
        $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);
        $this->assert_count_and_total($res, 0, 0);
    }

    /**
     * Tests that packages uploaded by the current user are returned.
     *
     * @covers \qtype_questionpy\external\search_packages::execute
     * @return void
     * @throws moodle_exception
     */
    public function test_custom_with_user_uploaded_package(): void {
        package_provider()->store(true);
        $res = search_packages::execute('', [], 'custom', 'alpha', 'asc', 1, 0, null);
        $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);
        $this->assert_count_and_total($res, 1, 1);
    }

    /**
     * Tests that if a package has a version uploaded by a user and there is also a version provided by the server, both
     * versions get returned.
     *
     * @covers \qtype_questionpy\external\search_packages::execute
     * @return void
     * @throws moodle_exception
     */
    public function test_custom_with_user_version_and_server_version_are_both_returned(): void {
        // Create server package version.
        package_provider(['namespace' => 'ns1', 'version' => '0.1.0'])->store(false);
        // Create user package version.
        package_provider(['namespace' => 'ns1', 'version' => '0.2.0'])->store(true);

        // Execute service.
        $res = search_packages::execute('', [], 'custom', 'alpha', 'asc', 1, 0, null);
        $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);

        // Both version should be returned.
        $this->assert_count_and_total($res, 1, 1);
        $versions = $res['packages'][0]['versions'];
        $this->assertCount(2, $versions);
        $this->assertEqualsCanonicalizing(['0.1.0', '0.2.0'], array_column($versions, 'version'));
    }

    /**
     * Favourites one or multiple packages given their id(s).
     *
     * @param user_favourite_service $ufservice
     * @param context_user $context
     * @param int ...$ids the package version ids to be marked as favourite
     * @throws moodle_exception
     */
    private static function favourite(user_favourite_service $ufservice, context_user $context, int ...$ids): void {
        global $DB;

        [$insql, $inparams] = $DB->get_in_or_equal($ids);
        $packageids = $DB->get_fieldset_select('qtype_questionpy_pkgversion', 'packageid', "id $insql", $inparams);
        foreach ($packageids as $packageid) {
            $ufservice->create_favourite('qtype_questionpy', 'package', $packageid, $context);
        }
    }

    /**
     * Tests that only packages marked as favourite are returned. Server provided and user uploaded packages are used.
     * It also tests that the `isfavourite`-property is set correctly.
     *
     * @covers \qtype_questionpy\external\search_packages::execute
     * @return void
     * @throws moodle_exception
     */
    public function test_favourites_returns_packages_marked_as_favourite_and_isfavourite_is_set_correctly(): void {
        // Create two users and assign them to the same course.
        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_and_enrol($course);
        $user2 = $this->getDataGenerator()->create_and_enrol($course);

        // Create two packages provided by the server.
        $pkgversionidserver = package_provider(['namespace' => 'ns1'])->store(false);
        package_provider(['namespace' => 'ns2'])->store(false);

        // Upload two packages as user one.
        $this->setUser($user1);
        $pkgversionidotheruser = package_provider(['namespace' => 'ns3'])->store(true);
        package_provider(['namespace' => 'ns4'])->store(true);

        // Set user two and upload two packages.
        $this->setUser($user2);
        $pkgversioniduser = package_provider(['namespace' => 'ns5'])->store(true);
        package_provider(['namespace' => 'ns6'])->store(true);

        // Favourite one package of each kind as user two.
        $usercontext = context_user::instance($user2->id);
        $ufservice = \core_favourites\service_factory::get_service_for_user_context($usercontext);
        self::favourite($ufservice, $usercontext, $pkgversionidserver, $pkgversionidotheruser, $pkgversioniduser);
        $favourites = ['ns1', 'ns3', 'ns5'];

        // Check if only packages marked as favourite are returned.
        $res = search_packages::execute('', [], 'favourites', 'alpha', 'asc', 3, 0, null);
        $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);
        $this->assert_count_and_total($res, 3, 3);
        $namespaces = array_column($res['packages'], 'namespace');
        $this->assertEqualsCanonicalizing($favourites, $namespaces);

        // Check if isfavourite-property is set correctly.
        $res = search_packages::execute('', [], 'all', 'alpha', 'asc', 6, 0, null);
        $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);
        $this->assert_count_and_total($res, 6, 6);

        foreach ($res['packages'] as $package) {
            if (in_array($package['namespace'], $favourites)) {
                self::assertTrue($package['isfavourite']);
            } else {
                self::assertFalse($package['isfavourite']);
            }
        }
    }

    /**
     * Tests that only packages which were marked as favourite by the current user are returned even if another user has
     * also marked packages as favourite.
     *
     * @covers \qtype_questionpy\external\search_packages::execute
     * @return void
     * @throws moodle_exception
     */
    public function test_favourites_are_not_shared_across_users(): void {
        // Create two users and get the user favourite service.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user1context = context_user::instance($user1->id);
        $user2context = context_user::instance($user2->id);
        $user1service = \core_favourites\service_factory::get_service_for_user_context($user1context);
        $user2service = \core_favourites\service_factory::get_service_for_user_context($user2context);

        // Create two server packages.
        $pkgversion1 = package_provider(['namespace' => 'ns1'])->store(false);
        $pkgversion2 = package_provider(['namespace' => 'ns2'])->store(false);

        // Both users favourite different packages.
        self::favourite($user1service, $user1context, $pkgversion1);
        self::favourite($user2service, $user2context, $pkgversion2);

        // Check that each user has only the correct favourite set.
        foreach ([[$user1, 'ns1'], [$user2, 'ns2']] as [$user, $ns]) {
            $this->setUser($user);
            $res = search_packages::execute('', [], 'favourites', 'alpha', 'asc', 1, 0, null);
            $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);
            $this->assert_count_and_total($res, 1, 1);
            $this->assertEquals($ns, $res['packages'][0]['namespace']);
        }
    }

    // TODO: add tests for filtering by tags when localized tags are supported.
}
