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
 * @/runTestsInSeparateProcesses
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
     * @covers \qtype_questionpy\external\favourite_package::favourite_package_execute
     * @throws moodle_exception
     */
    public function test_favourite_needs_user_to_be_logged_in(): void {
        global $PAGE;
        $this->setUser();
        $this->expectException(\require_login_exception::class);
        favourite_package::favourite_package_execute(0, $PAGE->context->id);
    }

    /**
     * Test that the context needs to be valid.
     *
     * @covers \qtype_questionpy\external\favourite_package::favourite_package_execute
     * @throws moodle_exception
     */
    public function test_favourite_needs_context_id_to_be_valid(): void {
        $this->expectException(\invalid_parameter_exception::class);
        $this->expectExceptionMessageMatches("/Context does not exist/");
        favourite_package::favourite_package_execute(0, -1);
    }

    /**
     * Test that you can not favourite not existing packages.
     *
     * @covers \qtype_questionpy\external\favourite_package::favourite_package_execute
     * @throws moodle_exception
     */
    public function test_favourite_with_not_existing_package_id_does_not_work(): void {
        global $USER, $PAGE;
        $res = favourite_package::favourite_package_execute(42, $PAGE->context->id);
        $res = external_api::clean_returnvalue(favourite_package::favourite_package_returns(), $res);
        $this->assertFalse($res);
        $this->assert_marked_as_favourite($USER->id, []);
    }

    /**
     * Test that you can favourite a package that was uploaded by the user.
     *
     * @covers \qtype_questionpy\external\favourite_package::favourite_package_execute
     * @throws moodle_exception
     */
    public function test_favourite_works_with_user_package(): void {
        global $USER, $PAGE;
        $packageid = self::get_id(package_provider()->store());
        $res = favourite_package::favourite_package_execute($packageid, $PAGE->context->id);
        $res = external_api::clean_returnvalue(favourite_package::favourite_package_returns(), $res);
        self::assertTrue($res);
        $this->assert_marked_as_favourite($USER->id, [$packageid]);
    }

    /**
     * Test that you can favourite a package that is provided by the application server.
     *
     * @covers \qtype_questionpy\external\favourite_package::favourite_package_execute
     * @throws moodle_exception
     */
    public function test_favourite_works_with_server_package(): void {
        global $USER, $PAGE;
        $packageid = self::get_id(package_provider()->store(0, false));
        $res = favourite_package::favourite_package_execute($packageid, $PAGE->context->id);
        $res = external_api::clean_returnvalue(favourite_package::favourite_package_returns(), $res);
        self::assertTrue($res);
        $this->assert_marked_as_favourite($USER->id, [$packageid]);
    }

    /**
     * Test that you can favourite a package that was uploaded by a different user in same course.
     *
     * @covers \qtype_questionpy\external\favourite_package::favourite_package_execute
     * @throws moodle_exception
     */
    public function test_favourite_works_with_packages_uploaded_in_same_course(): void {
        // Create two users and enrol them in the same course.
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = context_course::instance($course->id);
        $user1 = $this->getDataGenerator()->create_and_enrol($course);
        $user2 = $this->getDataGenerator()->create_and_enrol($course);

        // Upload a package as user one.
        $this->setUser($user1);
        $packageid = self::get_id(package_provider()->store($coursecontext->id));

        // Favourite the package as user two.
        $this->setUser($user2);
        $res = favourite_package::favourite_package_execute($packageid, $coursecontext->id);
        $res = external_api::clean_returnvalue(favourite_package::favourite_package_returns(), $res);
        self::assertTrue($res);
        $this->assert_marked_as_favourite($user2->id, [$packageid]);
    }

    /**
     * Test that you can favourite a package that was uploaded by a different user in a different quiz in same course.
     *
     * @covers \qtype_questionpy\external\favourite_package::favourite_package_execute
     * @throws moodle_exception
     */
    public function test_favourite_works_with_packages_uploaded_in_same_course_different_quiz(): void {
        // Create two users, enrol them in the same course and create two quizzes in that course.
        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_and_enrol($course);
        $user2 = $this->getDataGenerator()->create_and_enrol($course);
        $quiz1 = $this->getDataGenerator()->create_module('quiz', ['course' => $course]);
        $quiz1context = context_module::instance($quiz1->cmid);
        $quiz2 = $this->getDataGenerator()->create_module('quiz', ['course' => $course]);
        $quiz2context = context_module::instance($quiz2->cmid);

        // Upload a package as user one in quiz one.
        $this->setUser($user1);
        $packageid = self::get_id(package_provider()->store($quiz1context->id));

        // Favourite the package as user two in quiz two.
        $this->setUser($user2);
        $res = favourite_package::favourite_package_execute($packageid, $quiz2context->id);
        $res = external_api::clean_returnvalue(favourite_package::favourite_package_returns(), $res);
        self::assertTrue($res);
        $this->assert_marked_as_favourite($user2->id, [$packageid]);
    }

    /**
     * Test that you can mark one package multiple times as favourite.
     *
     * @covers \qtype_questionpy\external\favourite_package::favourite_package_execute
     * @throws moodle_exception
     */
    public function test_favourite_works_when_marking_same_package_multiple_times_as_favourite(): void {
        global $USER, $PAGE;
        $packageid = self::get_id(package_provider()->store());
        for ($i = 0; $i < 3; $i++) {
            $res = favourite_package::favourite_package_execute($packageid, $PAGE->context->id);
            $res = external_api::clean_returnvalue(favourite_package::favourite_package_returns(), $res);
            $this->assertTrue($res);
            $this->assert_marked_as_favourite($USER->id, [$packageid]);
        }
    }

    /**
     * Test that you can not favourite packages from irrelevant contexts.
     *
     * @covers \qtype_questionpy\external\favourite_package::favourite_package_execute
     * @throws moodle_exception
     */
    public function test_favourite_does_not_work_with_packages_from_irrelevant_contexts(): void {
        // Create two users and enrol them into two different courses.
        $course1 = $this->getDataGenerator()->create_course();
        $course1context = context_course::instance($course1->id);
        $user1 = $this->getDataGenerator()->create_and_enrol($course1);
        $course2 = $this->getDataGenerator()->create_course();
        $course2context = context_course::instance($course2->id);
        $user2 = $this->getDataGenerator()->create_and_enrol($course2);

        // Upload a package as user one in course one.
        $this->setUser($user1);
        $packageid = self::get_id(package_provider()->store($course1context->id));

        // Favourite the package as user two in course two.
        $this->setUser($user2);
        $res = favourite_package::favourite_package_execute($packageid, $course2context->id);
        $res = external_api::clean_returnvalue(favourite_package::favourite_package_returns(), $res);
        $this->assertFalse($res);
        $this->assert_marked_as_favourite($user2->id, []);
    }

    /**
     * Test that the user needs to be logged in.
     *
     * @covers \qtype_questionpy\external\favourite_package::unfavourite_package_execute
     * @throws moodle_exception
     */
    public function test_unfavourite_needs_user_to_be_logged_in(): void {
        global $PAGE;
        $this->setUser();
        $this->expectException(\require_login_exception::class);
        favourite_package::unfavourite_package_execute(0, $PAGE->context->id);
    }

    /**
     * Test that the context needs to be valid.
     *
     * @covers \qtype_questionpy\external\favourite_package::unfavourite_package_execute
     * @throws moodle_exception
     */
    public function test_unfavourite_needs_context_id_to_be_valid(): void {
        $this->expectException(\invalid_parameter_exception::class);
        $this->expectExceptionMessageMatches("/Context does not exist/");
        favourite_package::unfavourite_package_execute(0, -1);
    }

    /**
     * Test that you can unfavourite not existing packages.
     *
     * @covers \qtype_questionpy\external\favourite_package::unfavourite_package_execute
     * @throws moodle_exception
     */
    public function test_unfavourite_with_not_existing_package_id_does_work(): void {
        global $USER, $PAGE;
        $res = favourite_package::unfavourite_package_execute(42, $PAGE->context->id);
        $res = external_api::clean_returnvalue(favourite_package::unfavourite_package_returns(), $res);
        $this->assertTrue($res);
        $this->assert_marked_as_favourite($USER->id, []);
    }

    /**
     * Test that you can unfavourite existing packages.
     *
     * @covers \qtype_questionpy\external\favourite_package::unfavourite_package_execute
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
        $res = favourite_package::unfavourite_package_execute($packageid, $context->id);
        $res = external_api::clean_returnvalue(favourite_package::unfavourite_package_returns(), $res);
        $this->assertTrue($res);
        $this->assert_marked_as_favourite($USER->id, []);
    }
}
