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

use context_user;
use external_api;
use moodle_exception;
use function qtype_questionpy\package_provider;

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__DIR__) . '/data_provider.php');

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for {@see favourite_package}.
 *
 * @runTestsInSeparateProcesses
 *
 * @package    qtype_questionpy
 * @author     Jan Britz
 * @copyright  2024 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class favourite_package_test extends \externallib_advanced_testcase {

    /**
     * This method is called before each test.
     */
    public function setUp(): void {
        $this->resetAfterTest();
        $this->setGuestUser();
    }

    /**
     * Returns the package id of the given package version id.
     *
     * @param int $pkgversionid
     * @return int
     * @throws moodle_exception
     */
    private static function get_id(int $pkgversionid): int {
        global $DB;
        return $DB->get_field('qtype_questionpy_pkgversion', 'packageid', ['id' => $pkgversionid], MUST_EXIST);
    }

    /**
     * Asserts that the given packages are retrievable via the user favourite service.
     *
     * @param int $userid
     * @param int[] $packageids
     * @throws moodle_exception
     */
    private function assert_marked_as_favourite(int $userid, array $packageids): void {
        $context = context_user::instance($userid);
        $ufservice = \core_favourites\service_factory::get_service_for_user_context($context);
        $favourites = $ufservice->find_favourites_by_type('qtype_questionpy', 'package');
        $expected = array_column($favourites, 'itemid');
        $this->assertEqualsCanonicalizing($expected, $packageids);
    }

    /**
     * Test that the user needs to be logged in.
     *
     * @covers \qtype_questionpy\external\favourite_package::execute
     * @throws moodle_exception
     */
    public function test_favourite_needs_user_to_be_logged_in(): void {
        $this->setUser();
        $this->expectException(\require_login_exception::class);
        favourite_package::execute(0, true);
    }

    /**
     * Test that you can not favourite not existing packages.
     *
     * @covers \qtype_questionpy\external\favourite_package::execute
     * @throws moodle_exception
     */
    public function test_favourite_with_not_existing_package_id_does_not_work(): void {
        global $USER;
        $res = favourite_package::execute(42, true);
        $res = external_api::clean_returnvalue(favourite_package::execute_returns(), $res);
        $this->assertFalse($res);
        $this->assert_marked_as_favourite($USER->id, []);
    }

    /**
     * Test that you can favourite a package that was uploaded by the user.
     *
     * @covers \qtype_questionpy\external\favourite_package::execute
     * @throws moodle_exception
     */
    public function test_favourite_works_with_user_package(): void {
        global $USER;
        $packageid = self::get_id(package_provider()->store());
        $res = favourite_package::execute($packageid, true);
        $res = external_api::clean_returnvalue(favourite_package::execute_returns(), $res);
        self::assertTrue($res);
        $this->assert_marked_as_favourite($USER->id, [$packageid]);
    }

    /**
     * Test that you can favourite a package that is provided by the application server.
     *
     * @covers \qtype_questionpy\external\favourite_package::execute
     * @throws moodle_exception
     */
    public function test_favourite_works_with_server_package(): void {
        global $USER;
        $packageid = self::get_id(package_provider()->store());
        $res = favourite_package::execute($packageid, true);
        $res = external_api::clean_returnvalue(favourite_package::execute_returns(), $res);
        self::assertTrue($res);
        $this->assert_marked_as_favourite($USER->id, [$packageid]);
    }

    /**
     * Test that you can favourite a package that was uploaded by a different user.
     *
     * @covers \qtype_questionpy\external\favourite_package::execute
     * @throws moodle_exception
     */
    public function test_favourite_works_with_packages_uploaded_by_other_user(): void {
        // Create two users.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        // Upload a package as user one.
        $this->setUser($user1);
        $packageid = self::get_id(package_provider()->store());

        // Favourite the package as user two.
        $this->setUser($user2);
        $res = favourite_package::execute($packageid, true);
        $res = external_api::clean_returnvalue(favourite_package::execute_returns(), $res);
        self::assertTrue($res);
        $this->assert_marked_as_favourite($user2->id, [$packageid]);
    }


    /**
     * Test that you can mark one package multiple times as favourite.
     *
     * @covers \qtype_questionpy\external\favourite_package::execute
     * @throws moodle_exception
     */
    public function test_favourite_works_when_marking_same_package_multiple_times_as_favourite(): void {
        global $USER;
        $packageid = self::get_id(package_provider()->store());
        for ($i = 0; $i < 3; $i++) {
            $res = favourite_package::execute($packageid, true);
            $res = external_api::clean_returnvalue(favourite_package::execute_returns(), $res);
            $this->assertTrue($res);
            $this->assert_marked_as_favourite($USER->id, [$packageid]);
        }
    }

    /**
     * Test that you can unfavourite not existing packages.
     *
     * @covers \qtype_questionpy\external\favourite_package::execute
     * @throws moodle_exception
     */
    public function test_unfavourite_with_not_existing_package_id_does_work(): void {
        global $USER;
        $res = favourite_package::execute(42, false);
        $res = external_api::clean_returnvalue(favourite_package::execute_returns(), $res);
        $this->assertTrue($res);
        $this->assert_marked_as_favourite($USER->id, []);
    }

    /**
     * Test that you can unfavourite existing packages.
     *
     * @covers \qtype_questionpy\external\favourite_package::execute
     * @throws moodle_exception
     */
    public function test_unfavourite_with_existing_package_id_does_work(): void {
        global $USER;

        // Create a package and favourite it via the user favourite service.
        $packageid = self::get_id(package_provider()->store());
        $context = context_user::instance($USER->id);
        $ufservice = \core_favourites\service_factory::get_service_for_user_context($context);
        $ufservice->create_favourite('qtype_questionpy', 'package', $packageid, $context);

        // Unfavourite the package.
        $res = favourite_package::execute($packageid, false);
        $res = external_api::clean_returnvalue(favourite_package::execute_returns(), $res);
        $this->assertTrue($res);
        $this->assert_marked_as_favourite($USER->id, []);
    }
}
