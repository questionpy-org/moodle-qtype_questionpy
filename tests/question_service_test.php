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

use coding_exception;
use dml_exception;
use moodle_exception;
use qtype_questionpy\api\api;
use qtype_questionpy\api\package_api;
use qtype_questionpy\api\question_response;
use stdClass;

/**
 * Unit tests for {@see question_service}.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_service_test extends \advanced_testcase {

    /** @var api */
    private api $api;

    /** @var package_api */
    private package_api $packageapi;

    /** @var question_service */
    private question_service $questionservice;

    protected function setUp(): void {
        $this->api = $this->createMock(api::class);
        $this->api->method("get_package")
            ->willReturn(null);
        $this->packageapi = $this->createMock(package_api::class);
        $this->api->method("package")
            ->willReturn($this->packageapi);

        $packageservice = new package_service($this->api);
        $this->questionservice = new question_service($this->api, $packageservice);
    }

    /**
     * Tests {@see question_service::get_question()} happy path.
     *
     * @throws dml_exception
     * @throws coding_exception
     * @covers \qtype_questionpy\question_service::get_question
     */
    public function test_get_question_should_load_package_and_state() {
        [$packageid, $package] = $this->setup_package();
        $statestr = $this->setup_question($packageid);

        $result = $this->questionservice->get_question(1);

        $this->assertEquals(
            (object)[
                "qpy_package_hash" => $package->hash,
                "qpy_state" => $statestr,
            ], $result
        );
    }

    /**
     * Tests {@see question_service::get_question()} for a question which doesn't have a record in
     * <code>qtype_questionpy</code> yet.
     *
     * @throws dml_exception
     * @throws coding_exception
     * @covers \qtype_questionpy\question_service::get_question
     */
    public function test_get_question_should_return_empty_object_when_no_record() {
        $this->assertEquals(new stdClass(), $this->questionservice->get_question(42));
    }

    /**
     * Tests {@see question_service::upsert_question()} for update.
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @covers \qtype_questionpy\question_service::upsert_question
     */
    public function test_upsert_question_should_update_existing_record_if_changed() {
        [$oldpackageid] = $this->setup_package();
        $oldstate = $this->setup_question($oldpackageid);
        [$newpackageid, $newpackage] = $this->setup_package();

        $newstate = json_encode(["this is" => "new state"]);
        $formdata = ["this is" => "form data"];

        $this->packageapi
            ->expects($this->once())
            ->method("create_question")
            ->with($oldstate, (object)$formdata)
            ->willReturn(new question_response($newstate, "", ""));

        $this->questionservice->upsert_question(
            (object)[
                "id" => 1,
                "qpy_package_source" => "search",
                "qpy_package_hash" => $newpackage->hash,
                "qpy_form" => $formdata,
            ]
        );

        $this->assert_single_question(1, $newpackageid, $newstate);
    }

    /**
     * Tests {@see question_service::upsert_question()} for no change.
     *
     * @throws moodle_exception
     * @throws coding_exception
     * @throws dml_exception
     * @covers \qtype_questionpy\question_service::upsert_question
     */
    public function test_upsert_question_should_do_nothing_if_unchanged() {
        [$packageid, $package] = $this->setup_package();
        $oldstate = $this->setup_question($packageid);

        $formdata = ["this is" => "form data"];

        $this->packageapi
            ->expects($this->once())
            ->method("create_question")
            ->with($oldstate, (object)$formdata)
            ->willReturn(new question_response($oldstate, "", ""));

        $this->questionservice->upsert_question(
            (object)[
                "id" => 1,
                "qpy_package_source" => "search",
                "qpy_package_hash" => $package->hash,
                "qpy_form" => $formdata,
            ]
        );

        $this->assert_single_question(1, $packageid, $oldstate);
    }

    /**
     * Tests {@see question_service::upsert_question()} for a question which doesn't have a record in
     * <code>qtype_questionpy</code> yet.
     *
     * @throws moodle_exception
     * @throws coding_exception
     * @throws dml_exception
     * @covers \qtype_questionpy\question_service::upsert_question
     */
    public function test_upsert_question_should_insert_record() {
        [$packageid, $package] = $this->setup_package();

        $newstate = json_encode(["this is" => "new state"]);
        $formdata = ["this is" => "form data"];

        $this->packageapi
            ->expects($this->once())
            ->method("create_question")
            ->with(null, (object)$formdata)
            ->willReturn(new question_response($newstate, "", ""));

        $this->questionservice->upsert_question(
            (object)[
                "id" => 42, // Does not exist in the qtype_questionpy table yet.
                "qpy_package_source" => "search",
                "qpy_package_hash" => $package->hash,
                "qpy_form" => $formdata,
            ]
        );

        $this->assert_single_question(42, $packageid, $newstate);
    }

    /**
     * Tests {@see question_service::upsert_question()} with a nonexistent package hash.
     *
     * @throws dml_exception
     * @covers \qtype_questionpy\question_service::upsert_question
     */
    public function test_upsert_question_should_throw_when_package_does_not_exist() {
        $hash = hash("sha256", rand());

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessageMatches("/package $hash does not exist/");

        $this->questionservice->upsert_question(
            (object)[
                "id" => 1,
                "qpy_package_source" => "search",
                "qpy_package_hash" => $hash,
            ]
        );
    }

    /**
     * Tests that {@see question_service::delete_question()} does what it says on the tin.
     *
     * @throws dml_exception
     * @throws coding_exception
     * @covers \qtype_questionpy\question_service::upsert_question
     */
    public function test_delete_question() {
        [$packageid] = $this->setup_package();
        $this->setup_question($packageid);

        global $DB;
        $this->assertEquals(1, $DB->count_records("qtype_questionpy"));

        $this->questionservice->delete_question(1);

        $this->assertEquals(0, $DB->count_records("qtype_questionpy"));
    }

    /**
     * Inserts a question using the given package into the DB and returns the state string.
     *
     * @param int $packageid database ID (not hash) of a package
     * @throws dml_exception
     */
    private function setup_question(int $packageid): string {
        $this->resetAfterTest();

        $statestr = '
        {
          "opt1": "opt 1 value"
        }
        ';

        global $DB;
        $DB->insert_record("qtype_questionpy", [
            "id" => 1,
            "questionid" => 1,
            "feedback" => "",
            "packageid" => $packageid,
            "state" => $statestr,
        ]);

        return $statestr;
    }

    /**
     * Inserts a package with a random hash into the DB, returns its ID and the {@see package} instance.
     *
     * @throws coding_exception
     * @throws dml_exception
     */
    private function setup_package(): array {
        $this->resetAfterTest();

        $hash = hash("sha256", rand());
        $package = new package(
            $hash, "test", "default", ["en" => "Test"],
            "0.1.0", "QUESTION_TYPE"
        );
        $packageid = $package->store_in_db();
        return [$packageid, $package];
    }

    /**
     * Asserts that a single question exists in `qtype_questionpy` and it matches the given arguments.
     *
     * @param int $id        expected question id
     * @param int $packageid expected package id
     * @param string $state  expected state
     * @return void
     * @throws dml_exception
     */
    private function assert_single_question(int $id, int $packageid, string $state) {
        global $DB;
        $records = $DB->get_records("qtype_questionpy");
        $this->assertCount(1, $records);
        $record = current($records);

        $this->assertEquals((string)$id, $record->questionid);
        $this->assertEquals((string)$packageid, $record->packageid);
        $this->assertEquals($state, $record->state);
    }
}
