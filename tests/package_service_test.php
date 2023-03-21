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

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . "/data_provider.php");

use coding_exception;
use context_course;
use context_user;
use dml_exception;
use file_exception;
use moodle_exception;
use qtype_questionpy\api\api;
use stored_file;
use stored_file_creation_exception;

/**
 * Unit tests for {@see package_service}.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2023 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class package_service_test extends \advanced_testcase {

    /** @var api */
    private api $api;

    /** @var package_service */
    private package_service $packageservice;

    /** @var context_course */
    private context_course $coursecontext;

    protected function setUp(): void {
        $this->api = $this->createMock(api::class);

        $this->packageservice = new package_service($this->api);

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->coursecontext = context_course::instance($course->id);

        $this->setUser($this->getDataGenerator()->create_user());
    }

    /**
     * Tests that save_uploaded_draft doesn't add any files or records when they already exist.
     *
     * @throws coding_exception
     * @throws moodle_exception
     * @throws dml_exception
     * @covers \qtype_questionpy\package_service::save_uploaded_draft()
     */
    public function test_save_uploaded_draft_should_do_nothing_when_already_stored(): void {
        $package = package_provider1();
        $packageid = $package->store_in_db();

        $draftid = $this->setup_draft_file()->get_itemid();
        $this->setup_package_file($packageid, $package->hash);

        $this->api
            ->method("package_extract_info")
            ->willReturn($package);

        [$resultid, $resultpackage] = $this->packageservice->save_uploaded_draft($draftid, $this->coursecontext->id);

        $this->assertEquals($packageid, $resultid);
        $this->assertEquals($package, $resultpackage);
        $this->assert_single_package_file_exists($packageid);
        $this->assert_single_package_record_exists($package);
    }

    /**
     * Tests that save_uploaded_draft stores the package file when it isn't already, but the DB record already exists.
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws file_exception
     * @throws stored_file_creation_exception
     * @throws moodle_exception
     * @covers \qtype_questionpy\package_service::save_uploaded_draft()
     */
    public function test_save_uploaded_draft_should_store_package_file(): void {
        $package = package_provider1();
        $packageid = $package->store_in_db();

        $draftid = $this->setup_draft_file()->get_itemid();

        $this->api
            ->method("package_extract_info")
            ->willReturn($package);

        [$resultid, $resultpackage, $resultfile] =
            $this->packageservice->save_uploaded_draft($draftid, $this->coursecontext->id);

        $this->assertEquals($packageid, $resultid);
        $this->assertEquals($package, $resultpackage);
        $this->assertEquals($packageid, $resultfile->get_itemid());
        $this->assert_single_package_file_exists($packageid);
        $this->assert_single_package_record_exists($package);
    }

    /**
     * Tests that save_uploaded_draft both stores the package file and inserts the DB record when necessary.
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws file_exception
     * @throws stored_file_creation_exception
     * @throws moodle_exception
     * @covers \qtype_questionpy\package_service::save_uploaded_draft()
     */
    public function test_save_uploaded_draft_should_insert_record_and_store_file(): void {
        $package = package_provider1();

        $draftid = $this->setup_draft_file()->get_itemid();

        $this->api
            ->method("package_extract_info")
            ->willReturn($package);

        [$packageid, $resultpackage, $resultfile] =
            $this->packageservice->save_uploaded_draft($draftid, $this->coursecontext->id);

        $this->assertEquals($package, $resultpackage);
        $this->assertEquals($packageid, $resultfile->get_itemid());
        $this->assert_single_package_file_exists($packageid);
        $this->assert_single_package_record_exists($package);
    }

    /**
     * Tests get_draft_file happy path.
     *
     * @throws file_exception
     * @throws stored_file_creation_exception
     * @throws coding_exception
     * @covers \qtype_questionpy\package_service::get_draft_file()
     */
    public function test_get_draft_file(): void {
        $expected = $this->setup_draft_file();

        $actual = $this->packageservice->get_draft_file($expected->get_itemid());

        $this->assertEquals($expected->get_contenthash(), $actual->get_contenthash());
        $this->assertEquals($expected->get_pathnamehash(), $actual->get_pathnamehash());
    }

    /**
     * Tests that get_draft_file throws a {@see coding_exception} when the draft doesn't exist.
     *
     * @return void
     * @throws coding_exception
     * @covers \qtype_questionpy\package_service::get_draft_file()
     */
    public function test_get_draft_file_should_throw_when_draft_does_not_exist(): void {
        $this->expectException(coding_exception::class);
        $this->expectExceptionMessageMatches("/42/");

        $this->packageservice->get_draft_file(42);
    }

    /**
     * Tests that get_package returns the existing record by hash if it exists.
     *
     * @throws moodle_exception
     * @throws coding_exception
     * @throws dml_exception
     * @covers \qtype_questionpy\package_service::get_package()
     */
    public function test_get_package_should_use_existing_record(): void {
        $package = package_provider1();
        $id = $package->store_in_db();

        $this->api
            ->expects($this->never())
            ->method($this->anything());

        [$resultid, $resultpackage] = $this->packageservice->get_package($package->hash);

        $this->assertEquals($id, $resultid);
        $this->assertEquals($package, $resultpackage);
    }

    /**
     * Tests that get_package gets the record from the API and inserts it when necessary.
     *
     * @throws moodle_exception
     * @throws dml_exception
     * @covers \qtype_questionpy\package_service::get_package()
     */
    public function test_get_package_should_get_from_api_and_insert_record(): void {
        $package = package_provider1();

        $this->api
            ->expects($this->once())
            ->method("get_package")
            ->with($package->hash)
            ->willReturn($package);

        [, $resultpackage] = $this->packageservice->get_package($package->hash);

        $this->assertEquals($resultpackage, $resultpackage);
        $this->assert_single_package_record_exists($package);
    }

    /**
     * Tests that get_package returns null when the package is neither present in the DB nor returned from the API.
     *
     * @throws moodle_exception
     * @covers \qtype_questionpy\package_service::get_package()
     */
    public function test_get_package_should_return_null_when_package_does_not_exist(): void {
        $package = package_provider1();

        $this->api
            ->expects($this->once())
            ->method("get_package")
            ->with($package->hash)
            ->willReturn(null);

        $result = $this->packageservice->get_package($package->hash);

        $this->assertNull($result);
    }

    /**
     * Stores a draft file and returns it.
     *
     * @return stored_file
     * @throws file_exception
     * @throws stored_file_creation_exception
     */
    private function setup_draft_file(): stored_file {
        global $USER;
        $fs = get_file_storage();
        $itemid = rand(1, 1000);
        $usercontext = context_user::instance($USER->id);
        return $fs->create_file_from_string([
            "contextid" => $usercontext->id,
            "component" => "user",
            "filearea" => "draft",
            "filepath" => "/",
            "itemid" => $itemid,
            "filename" => "$itemid",
        ], "dummy content");
    }

    /**
     * Stores a file with the given item id in the package file area.
     *
     * @param int $id
     * @param string $hash
     * @throws file_exception
     * @throws stored_file_creation_exception
     */
    private function setup_package_file(int $id, string $hash): void {
        $fs = get_file_storage();
        $fs->create_file_from_string([
            "contextid" => $this->coursecontext->id,
            "component" => "qtype_questionpy",
            "filearea" => "packages",
            "filepath" => "/",
            "itemid" => $id,
            "filename" => "$hash.qpy",
        ], "dummy content");
    }

    /**
     * Asserts that only a single package record exists and it matches the given package.
     *
     * @param package $package
     * @throws dml_exception
     * @throws moodle_exception
     */
    private function assert_single_package_record_exists(package $package): void {
        $all = package::get_records();
        $this->assertCount(1, $all);
        $this->assertEquals($package, $all[0]);
    }

    /**
     * Asserts that only a single package file exists in the package file area and it has the given item id.
     *
     * @param int $itemid
     * @throws coding_exception
     */
    private function assert_single_package_file_exists(int $itemid): void {
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $this->coursecontext->id, "qtype_questionpy", "package",
            false, "itemid, filepath, filename", false
        );

        $this->assertCount(1, $files);
        $this->assertEquals($itemid, reset($files)->get_itemid());
    }
}
