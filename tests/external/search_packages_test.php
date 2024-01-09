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
use context_module;
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
        global $PAGE;
        $this->setUser();
        $this->expectException(\require_login_exception::class);
        search_packages::execute('Test query', [], 'allmine', 'desc', 'date', 3, 5, $PAGE->context->id);
    }

    /**
     * Test that the context needs to be valid.
     *
     * @covers \qtype_questionpy\external\search_packages::execute
     * @throws moodle_exception
     */
    public function test_context_id_needs_to_be_valid(): void {
        $this->expectException(\invalid_parameter_exception::class);
        $this->expectExceptionMessageMatches("/Context does not exist/");
        search_packages::execute('Test query', [], 'allmine', 'desc', 'date', 3, 5, -1);
    }

    /**
     * Test the service with invalid category parameter.
     *
     * @covers \qtype_questionpy\external\search_packages::execute
     * @throws moodle_exception
     */
    public function test_with_invalid_category_value(): void {
        global $PAGE;
        $this->expectException(\invalid_parameter_exception::class);
        $expected = implode(', ', search_packages::CATEGORIES);
        $this->expectExceptionMessageMatches("/.*$expected.*/i");
        search_packages::execute('Test query', [], 'allmine', 'desc', 'date', 3, 5, $PAGE->context->id);
    }

    /**
     * Test the service with invalid category parameter.
     *
     * @covers \qtype_questionpy\external\search_packages::execute
     * @throws moodle_exception
     */
    public function test_with_invalid_sort_value(): void {
        global $PAGE;
        $this->expectException(\invalid_parameter_exception::class);
        $expected = implode(', ', search_packages::SORT);
        $this->expectExceptionMessageMatches("/.*$expected.*/i");
        search_packages::execute('Test query', [], 'all', 'alphabetically', 'desc', 3, 5, $PAGE->context->id);
    }

    /**
     * Test the service with invalid order parameter.
     *
     * @covers \qtype_questionpy\external\search_packages::execute
     * @throws moodle_exception
     */
    public function test_with_invalid_order_value(): void {
        global $PAGE;
        $this->expectException(\invalid_parameter_exception::class);
        $expected = implode(', ', search_packages::ORDER);
        $this->expectExceptionMessageMatches("/.*$expected.*/i");
        search_packages::execute('Test query', [], 'all', 'alpha', 'recentlyused', 3, 5, $PAGE->context->id);
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
        global $PAGE;
        $this->expectException(\invalid_parameter_exception::class);
        $this->expectExceptionMessageMatches("/.*1 to 100.*/");
        search_packages::execute('Test query', [], 'all', 'alpha', 'asc', $limit, 5, $PAGE->context->id);
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
        global $PAGE;
        $this->expectException(\invalid_parameter_exception::class);
        $this->expectExceptionMessageMatches("/.*can not be negative.*/");
        search_packages::execute('Test query', [], 'all', 'alpha', 'asc', 1, $page, $PAGE->context->id);
    }

    /**
     * Provides valid but unsupported categories.
     *
     * @return array
     */
    public static function category_provider(): array {
        return [
            ['favourites'],
            ['mine'],
        ];
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
        global $PAGE;
        $this->expectException(\invalid_parameter_exception::class);
        search_packages::execute('Test query', [], $category, 'alpha', 'asc', 3, 5, $PAGE->context->id);
    }

    /**
     * Tests the service without available packages.
     *
     * @covers \qtype_questionpy\external\search_packages::execute
     * @throws moodle_exception
     */
    public function test_search_without_available_packages(): void {
        global $PAGE;
        // Execute service.
        $res = search_packages::execute('', [], 'all', 'alpha', 'asc', 1, 0, $PAGE->context->id);
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
        global $PAGE;
        // Create packages and their versions.
        $totalpackages = 2;
        $totalversions = 3;

        $versions = [];
        for ($i = 0; $i < $totalpackages; $i++) {
            $namespace = "n$i";
            for ($j = 0; $j < $totalversions; $j++) {
                $versionid = package_provider(['namespace' => $namespace, 'version' => "0.$j.0"])->store(0, $asuser);
                $versions[$namespace][] = $versionid;
            }
        }

        // Execute service.
        $res = search_packages::execute('', [], 'all', 'alpha', 'asc', $totalpackages, 0, $PAGE->context->id);
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
     * Tests that every package in a course context gets returned.
     *
     * @covers \qtype_questionpy\external\search_packages::execute
     * @throws moodle_exception
     */
    public function test_versions_get_returned_with_relevant_contexts(): void {
        // Create and enrol two users in the same course.
        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_and_enrol($course);
        $user2 = $this->getDataGenerator()->create_and_enrol($course);

        // Create two quizzes.
        $record = ['course' => $course];
        $quiz1 = $this->getDataGenerator()->create_module('quiz', $record);
        $quiz2 = $this->getDataGenerator()->create_module('quiz', $record);

        // Get contexts.
        $coursecontext = context_course::instance($course->id);
        $quiz1context = context_module::instance($quiz1->cmid);
        $quiz2context = context_module::instance($quiz2->cmid);

        // Set a user and create a package in each quiz.
        $this->setUser($user1);
        package_provider(['namespace' => 'ns1'])->store($quiz1context->id);
        package_provider(['namespace' => 'ns2'])->store($quiz2context->id);

        // Set the other user and use every context to retrieve the package.
        $this->setUser($user2);
        foreach ([$coursecontext, $quiz1context, $quiz2context] as $context) {
            $res = search_packages::execute('', [], 'all', 'alpha', 'asc', 2, 0, $context->id);
            $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);

            // Check if every version in DB is returned under the correct package.
            $this->assertCount(2, $res['packages']);
            $this->assertCount(1, $res['packages'][0]['versions']);
            $this->assertCount(1, $res['packages'][1]['versions']);

            // The amount of package versions should not change the package count.
            $this->assert_count_and_total($res, 2, 2);
        }
    }

    /**
     * Tests that even for the same package but different versions the context gets respected.
     *
     * @covers \qtype_questionpy\external\search_packages::execute
     * @throws moodle_exception
     */
    public function test_that_context_get_respected_with_same_package_but_different_version(): void {
        // Create two users in two separate courses.
        $course1 = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_and_enrol($course1);
        $course2 = $this->getDataGenerator()->create_course();
        $user2 = $this->getDataGenerator()->create_and_enrol($course2);

        // Get contexts.
        $course1context = context_course::instance($course1->id);
        $course2context = context_course::instance($course2->id);

        // Create two different package versions of same package with both users in their courses.
        $this->setUser($user1);
        package_provider(['namespace' => 'ns1', 'version' => '0.1.0'])->store($course1context->id);
        $this->setUser($user2);
        package_provider(['namespace' => 'ns1', 'version' => '0.2.0'])->store($course2context->id);

        // Check that context gets respected.
        $expected = [
            $user1->id => [$course1context->id, '0.1.0'],
            $user2->id => [$course2context->id, '0.2.0'],
        ];

        foreach ($expected as $userid => [$coursecontextid, $version]) {
            $this->setUser($userid);

            $res = search_packages::execute('', [], 'all', 'alpha', 'asc', 2, 0, $coursecontextid);
            $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);

            $this->assert_count_and_total($res, 1, 1);
            $versions = $res['packages'][0]['versions'];
            $this->assertCount(1, $versions);
            $this->assertEquals($version, $versions[0]['version']);
        }
    }

    /**
     * Tests that packages with a different (course) context are ignored.
     *
     * @covers \qtype_questionpy\external\search_packages::execute
     * @throws moodle_exception
     */
    public function test_versions_are_ignored_with_irrelevant_contexts(): void {
        // Create two users in two separate courses.
        $course1 = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_and_enrol($course1);
        $course2 = $this->getDataGenerator()->create_course();
        $user2 = $this->getDataGenerator()->create_and_enrol($course2);

        // Get contexts.
        $course1context = context_course::instance($course1->id);
        $course2context = context_course::instance($course2->id);

        // Set a user and create a package inside its course.
        $this->setUser($user1);
        package_provider(['namespace' => 'ns1'])->store($course1context->id);

        // Set the other user and retrieve all packages.
        $this->setUser($user2);
        $res = search_packages::execute('', [], 'all', 'alpha', 'asc', 1, 0, $course2context->id);
        $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);

        // No packages should be available for that user.
        $this->assert_count_and_total($res, 0, 0);
    }

    /**
     * Tests that having packages with multiple different sources returns correct values.
     *
     * @covers \qtype_questionpy\external\search_packages::execute
     * @throws moodle_exception
     */
    public function test_mixed_versions_are_returned_correctly(): void {
        // Create two courses and get contexts.
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        // Enrol two users in both courses.
        $user1 = $this->getDataGenerator()->create_and_enrol($course1);
        $user2 = $this->getDataGenerator()->create_and_enrol($course1);
        $this->getDataGenerator()->enrol_user($user1->id, $course2->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course2->id);

        // Get contexts.
        $course1context = context_course::instance($course1->id);
        $course2context = context_course::instance($course2->id);

        // Server package - should be always available.
        package_provider(['namespace' => 'ns1'])->store(0, false);

        // Both users create a package in both quizzes.
        $this->setUser($user1);
        package_provider(['namespace' => 'ns2'])->store($course1context->id);
        package_provider(['namespace' => 'ns3'])->store($course2context->id);
        $this->setUser($user2);
        package_provider(['namespace' => 'ns4'])->store($course1context->id);
        package_provider(['namespace' => 'ns5'])->store($course2context->id);

        // Create expected namespaces array for each user in each course.
        $expected = [
            $user1->id => [
                $course1context->id => ['ns1', 'ns2', 'ns3', 'ns4'],
                $course2context->id => ['ns1', 'ns2', 'ns3', 'ns5'],
            ],
            $user2->id => [
                $course1context->id => ['ns1', 'ns2', 'ns4', 'ns5'],
                $course2context->id => ['ns1', 'ns3', 'ns4', 'ns5'],
            ],
        ];

        // Check the returned packages for each user in each course.
        foreach ($expected as $userid => $coursecontext) {
            $this->setUser($userid);
            foreach ($coursecontext as $coursecontextid => $expectednamespaces) {
                $res = search_packages::execute('', [], 'all', 'alpha', 'asc', 4, 0, $coursecontextid);
                $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);

                $namespaces = array_column($res['packages'], 'namespace');
                $this->assertEqualsCanonicalizing($expectednamespaces, $namespaces);

                $this->assert_count_and_total($res, 4, 4);
            }
        }

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
        global $PAGE, $USER;

        // Store every package in the database.
        foreach ($packages as $namespace => [$names, $description]) {
            package_provider(['namespace' => $namespace, 'name' => $names, 'description' => $description])->store();
        }

        // Set current language if provided.
        if ($language) {
            $USER->lang = $language;
        }

        // Execute the service.
        $res = search_packages::execute($query, [], 'all', 'alpha', 'asc', count($packages), 0, $PAGE->context->id);
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
        global $PAGE;

        $totalpackages = count($names);
        foreach ($names as $name) {
            package_provider(['namespace' => "ns$name", 'name' => ['en' => $name]])->store();
        }

        // Sort the array of names so that we can use it as a reference.
        sort($names, SORT_STRING);

        // The smallest valid limit is one.
        $limit = max($totalpackages, 1);

        // Execute service with ascending order.
        $res = search_packages::execute('', [], 'all', 'alpha', 'asc', $limit, 0, $PAGE->context->id);
        $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);

        $actualnamespaces = array_column($res['packages'], 'name');
        $this->assertEquals($names, $actualnamespaces);

        // Execute service with descending order.
        $res = search_packages::execute('', [], 'all', 'alpha', 'desc', $limit, 0, $PAGE->context->id);
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
        global $PAGE;

        // Create multiple packages with different creation times.
        $totalpackages = 3;

        $namespaces = [];
        for ($i = 0; $i < $totalpackages; $i++) {
            $namespaces[] = "ns$i";
            package_provider(['namespace' => "ns$i"])->store();
            $this->waitForSecond();
        }

        // Execute service with ascending order.
        $res = search_packages::execute('', [], 'all', 'date', 'asc', $totalpackages, 0, $PAGE->context->id);
        $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);

        // Check that package order is correct.
        $actualnamespaces = array_column($res['packages'], 'namespace');
        $this->assertEquals($namespaces, $actualnamespaces);

        // Execute service with descending order.
        $res = search_packages::execute('', [], 'all', 'date', 'desc', $totalpackages, 0, $PAGE->context->id);
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
        global $PAGE;

        for ($i = 0; $i < $totalpackages; $i++) {
            package_provider(['namespace' => "ns$i"])->store();
        }

        // Calculate the amount of full pages and the size of the page after the last full page.
        $fullpages = intdiv($totalpackages, $limit);
        $lastpagesize = $totalpackages % $limit;

        // Check full pages.
        for ($page = 0; $page < $fullpages; $page++) {
            $res = search_packages::execute('', [], 'all', 'alpha', 'asc', $limit, $page, $PAGE->context->id);
            $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);
            $this->assert_count_and_total($res, $limit, $totalpackages);
        }

        // Check first page that is not full.
        $res = search_packages::execute('', [], 'all', 'alpha', 'asc', $limit, $fullpages, $PAGE->context->id);
        $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);
        $this->assert_count_and_total($res, $lastpagesize, $totalpackages);

        // Check that next page is empty.
        $res = search_packages::execute('', [], 'all', 'alpha', 'asc', $limit, $fullpages + 1, $PAGE->context->id);
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
     * Tests that recently used packages can be retrieved by providing the context id of a course.
     *
     * @covers \qtype_questionpy\external\search_packages::execute
     * @throws moodle_exception
     */
    public function test_recently_used_works_with_course_context_id(): void {
        // Create and set user.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Create a course with a quiz.
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = context_course::instance($course->id);
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course]);
        $quizcontext = context_module::instance($quiz->cmid);

        // Enrol user.
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // Add a package to last used table under the quiz context.
        $id = package_provider()->store();
        self::add_last_used_entry($id, $quizcontext->id);

        // Execute service with course context.
        $res = search_packages::execute('', [], 'recentlyused', 'alpha', 'asc', 1, 0, $coursecontext->id);
        $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);
        $this->assert_count_and_total($res, 1, 1);
    }

    /**
     * Tests that recently used packages are available across different quizzes in a course.
     *
     * @covers \qtype_questionpy\external\search_packages::execute
     * @return void
     * @throws moodle_exception
     */
    public function test_recently_used_package_available_in_same_course_different_quiz(): void {
        // Create and set user.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Create a course and enrol user.
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // Create two quizzes.
        $record = ['course' => $course];
        $quiz1 = $this->getDataGenerator()->create_module('quiz', $record);
        $quiz2 = $this->getDataGenerator()->create_module('quiz', $record);

        // Get contexts.
        $quiz1context = context_module::instance($quiz1->cmid);
        $quiz2context = context_module::instance($quiz2->cmid);

        // Create package in one quiz and use it.
        $id = package_provider(['namespace' => 'ns1'])->store();
        self::add_last_used_entry($id, $quiz1context->id);

        // Execute service with context id of the other quiz.
        $res = search_packages::execute('', [], 'recentlyused', 'alpha', 'asc', 1, 0, $quiz2context->id);
        $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);
        $this->assert_count_and_total($res, 1, 1);
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
        $this->getDataGenerator()->enrol_user($user->id, $course1->id);
        $course2 = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $course2->id);

        // Create two quizzes; one in each course.
        $quiz1 = $this->getDataGenerator()->create_module('quiz', ['course' => $course1]);
        $quiz2 = $this->getDataGenerator()->create_module('quiz', ['course' => $course2]);

        // Get contexts.
        $quiz1context = context_module::instance($quiz1->cmid);
        $quiz2context = context_module::instance($quiz2->cmid);

        // Create package in one quiz and use it.
        $id1 = package_provider(['namespace' => 'ns1'])->store();
        self::add_last_used_entry($id1, $quiz1context->id);
        $id2 = package_provider(['namespace' => 'ns2'])->store();
        self::add_last_used_entry($id2, $quiz2context->id);

        // Execute service with both context ids.
        $res = search_packages::execute('', [], 'recentlyused', 'alpha', 'asc', 1, 0, $quiz1context->id);
        $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);
        $this->assert_count_and_total($res, 1, 1);
        $this->assertEquals('ns1', $res['packages'][0]['namespace']);

        $res = search_packages::execute('', [], 'recentlyused', 'alpha', 'asc', 1, 0, $quiz2context->id);
        $res = external_api::clean_returnvalue(search_packages::execute_returns(), $res);
        $this->assert_count_and_total($res, 1, 1);
        $this->assertEquals('ns2', $res['packages'][0]['namespace']);
    }

    // TODO: add tests for filtering by tags when localized tags are supported.
}
