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
use dml_exception;
use moodle_exception;
use qtype_questionpy\api\api;
use qtype_questionpy\api\package_api;
use qtype_questionpy\api\question_response;
use qtype_questionpy\package\package;
use stdClass;

/**
 * Unit tests for {@see question_service}.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class question_service_test extends \advanced_testcase {
    /** @var api */
    private api $api;

    /** @var package_api */
    private package_api $packageapi;

    /** @var question_service */
    private question_service $questionservice;

    protected function setUp(): void {
        $this->api = $this->createMock(api::class);
        $this->packageapi = $this->createMock(package_api::class);
        $this->api->method("package")
            ->willReturn($this->packageapi);

        $packagefileservice = new package_file_service();
        $this->questionservice = new question_service($this->api, $packagefileservice);
    }

    /**
     * Tests {@see question_service::get_question()} happy path.
     *
     * @throws moodle_exception
     * @covers \qtype_questionpy\question_service::get_question
     */
    public function test_get_question_should_load_package_and_state(): void {
        $this->resetAfterTest();

        $pvi = package_versions_info_provider();
        [, [$pkgversionid]] = $pvi->upsert();
        [$statestr, $qpyid] = $this->setup_question($pvi->versions[0]->hash, $pkgversionid);

        $result = $this->questionservice->get_question(1);

        $this->assertEquals(
            (object)[
                "qpy_id" => $qpyid,
                "qpy_package_hash" => $pvi->versions[0]->hash,
                "qpy_state" => $statestr,
                "qpy_is_local" => "0",
            ],
            $result
        );
    }

    /**
     * Tests {@see question_service::get_question()} for a question which doesn't have a record in
     * <code>qtype_questionpy</code> yet.
     *
     * @throws moodle_exception
     * @covers \qtype_questionpy\question_service::get_question
     */
    public function test_get_question_should_return_empty_object_when_no_record(): void {
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
    public function test_upsert_question_should_update_existing_record_if_changed(): void {
        global $PAGE;
        $this->resetAfterTest();

        $pvi = package_versions_info_provider(null, [["version" => "0.2.0"], ["version" => "0.1.0"]]);
        [, [$newpackageid, $oldpackageid]] = $pvi->upsert();

        $oldstate = $this->setup_question($pvi->versions[1]->hash, $oldpackageid)[0];

        $newstate = json_encode(["this is" => "new state"]);
        $formdata = ["this is" => "form data"];

        $this->packageapi
            ->expects($this->once())
            ->method("create_question")
            ->with($oldstate, (object) $formdata)
            ->willReturn(new question_response($newstate, ""));

        $this->questionservice->upsert_question(
            (object)[
                "id" => 1,
                "qpy_package_hash" => $pvi->versions[0]->hash,
                "qpy_form" => $formdata,
                "qpy_package_source" => "search",
                "oldparent" => 1,
                "context" => $PAGE->context,
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
    public function test_upsert_question_should_do_nothing_if_unchanged(): void {
        global $PAGE;
        $this->resetAfterTest();

        $pvi = package_versions_info_provider();
        [, [$pkgversionid]] = $pvi->upsert();

        $oldstate = $this->setup_question($pvi->versions[0]->hash, $pkgversionid)[0];

        $formdata = ["this is" => "form data"];

        $this->packageapi
            ->expects($this->once())
            ->method("create_question")
            ->with($oldstate, (object) $formdata)
            ->willReturn(new question_response($oldstate, "", ""));

        $this->questionservice->upsert_question(
            (object)[
                "id" => 1,
                "qpy_package_hash" => $pvi->versions[0]->hash,
                "qpy_form" => $formdata,
                "qpy_package_source" => "search",
                "oldparent" => 1,
                "context" => $PAGE->context,
            ]
        );

        $this->assert_single_question(1, $pkgversionid, $oldstate);
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
    public function test_upsert_question_should_insert_record(): void {
        global $PAGE;
        $this->resetAfterTest();

        $pvi = package_versions_info_provider();
        [, [$pkgversionid]] = $pvi->upsert();

        $newstate = json_encode(["this is" => "new state"]);
        $formdata = ["this is" => "form data"];

        $this->packageapi
            ->expects($this->once())
            ->method("create_question")
            ->with(null, (object) $formdata)
            ->willReturn(new question_response($newstate, "", ""));

        $this->questionservice->upsert_question(
            (object)[
                "id" => 42, // Does not exist in the qtype_questionpy table yet.
                "qpy_package_hash" => $pvi->versions[0]->hash,
                "qpy_form" => $formdata,
                "qpy_package_source" => "search",
                "oldparent" => 1,
                "context" => $PAGE->context,
            ]
        );

        $this->assert_single_question(42, $pkgversionid, $newstate);
    }

    /**
     * Tests {@see question_service::upsert_question()} with a nonexistent package hash.
     *
     * @throws dml_exception
     * @covers \qtype_questionpy\question_service::upsert_question
     */
    public function test_upsert_question_should_throw_when_package_does_not_exist(): void {
        $hash = hash("sha256", rand());

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessageMatches("/package $hash does not exist/");

        $this->questionservice->upsert_question(
            (object)[
                "id" => 1,
                "qpy_package_hash" => $hash,
                "qpy_package_source" => "search",
            ]
        );
    }

    /**
     * Tests {@see question_service::upsert_question()} adds current package to the
     * <code>qtype_questionpy_lastused</code> table.
     *
     * @throws moodle_exception
     * @throws coding_exception
     * @throws dml_exception
     * @covers \qtype_questionpy\question_service::upsert_question
     */
    public function test_upsert_question_should_add_package_to_last_used_table(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();

        $pvi = package_versions_info_provider();
        [, [$pkgversionid]] = $pvi->upsert();

        $package = package::get_by_version($pkgversionid);

        $newstate = json_encode(["this is" => "new state"]);
        $formdata = ["this is" => "form data"];

        $this->packageapi
            ->expects($this->once())
            ->method("create_question")
            ->with(null, (object) $formdata)
            ->willReturn(new question_response($newstate, ""));

        $this->questionservice->upsert_question(
            (object)[
                "id" => 42, // Does not exist in the qtype_questionpy table yet.
                "qpy_package_hash" => $pvi->versions[0]->hash,
                "qpy_form" => $formdata,
                "qpy_package_source" => "search",
                "oldparent" => 1,
                "context" => $PAGE->context,
            ]
        );

        $record = $DB->get_record('qtype_questionpy_lastused', ['contextid' => $PAGE->context->id, 'packageid' => $package->id]);
        $this->assertNotFalse($record);
        $this->assertTimeCurrent($record->timeused);
    }

    /**
     * Tests {@see question_service::upsert_question()} adds current package to the
     * <code>qtype_questionpy_lastused</code> table.
     *
     * @throws moodle_exception
     * @throws coding_exception
     * @throws dml_exception
     * @covers \qtype_questionpy\question_service::upsert_question
     */
    public function test_upsert_question_in_same_context_with_same_package_should_only_update_time_used_in_last_used_table(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();

        $pvi = package_versions_info_provider();
        [, [$pkgversionid]] = $pvi->upsert();

        $package = package::get_by_version($pkgversionid);

        $newstate = json_encode(["this is" => "new state"]);
        $formdata = ["this is" => "form data"];

        $this->packageapi
            ->expects($this->exactly(2))
            ->method("create_question")
            ->with(null, (object) $formdata)
            ->willReturn(new question_response($newstate, ""));

        $this->questionservice->upsert_question(
            (object)[
                "id" => 42, // Does not exist in the qtype_questionpy table yet.
                "qpy_package_hash" => $pvi->versions[0]->hash,
                "qpy_form" => $formdata,
                "qpy_package_source" => "search",
                "oldparent" => 1,
                "context" => $PAGE->context,
            ]
        );

        $oldrecord = $DB->get_record(
            'qtype_questionpy_lastused',
            ['contextid' => $PAGE->context->id, 'packageid' => $package->id]
        );
        $this->waitForSecond();

        $this->questionservice->upsert_question(
            (object)[
                "id" => 43, // Does not exist in the qtype_questionpy table yet.
                "qpy_package_hash" => $pvi->versions[0]->hash,
                "qpy_form" => $formdata,
                "qpy_package_source" => "search",
                "oldparent" => 1,
                "context" => $PAGE->context,
            ]
        );

        $total = $DB->count_records('qtype_questionpy_lastused');
        $this->assertEquals(1, $total);

        $newrecord = $DB->get_record(
            'qtype_questionpy_lastused',
            ['contextid' => $PAGE->context->id, 'packageid' => $package->id]
        );
        $this->assertTimeCurrent($newrecord->timeused);
        $this->assertGreaterThan($oldrecord->timeused, $newrecord->timeused);
    }

    /**
     * Tests that {@see question_service::delete_question()} does what it says on the tin.
     *
     * @throws moodle_exception
     * @covers \qtype_questionpy\question_service::upsert_question
     */
    public function test_delete_question(): void {
        $this->resetAfterTest();

        $pvi = package_versions_info_provider();
        [, [$pkgversionid]] = $pvi->upsert();
        $this->setup_question($pvi->versions[0]->hash, $pkgversionid);

        global $DB;
        $this->assertEquals(1, $DB->count_records("qtype_questionpy"));

        question_service::delete_question(1);

        $this->assertEquals(0, $DB->count_records("qtype_questionpy"));
    }

    /**
     * Inserts a question using the given package into the DB and returns the state string and question id.
     *
     * @param string $pkgversionhash package version hash
     * @param int|null $pkgversionid database ID (not hash) of a package version
     * @return array[string, int]
     * @throws dml_exception
     */
    private function setup_question(string $pkgversionhash, ?int $pkgversionid): array {
        $this->resetAfterTest();

        $statestr = '
        {
          "opt1": "opt 1 value"
        }
        ';

        global $DB;
        $qpyid = $DB->insert_record("qtype_questionpy", [
            "id" => 1,
            "questionid" => 1,
            "feedback" => "",
            "pkgversionhash" => $pkgversionhash,
            "pkgversionid" => $pkgversionid,
            "islocal" => is_null($pkgversionid),
            "state" => $statestr,
        ]);

        return [$statestr, $qpyid];
    }

    /**
     * Asserts that a single question exists in `qtype_questionpy` and it matches the given arguments.
     *
     * @param int $id expected question id
     * @param int $pkgversionid expected package id
     * @param string $state expected state
     * @return void
     * @throws dml_exception
     */
    private function assert_single_question(int $id, int $pkgversionid, string $state) {
        global $DB;
        $records = $DB->get_records("qtype_questionpy");
        $this->assertCount(1, $records);
        $record = current($records);

        $this->assertEquals((string) $id, $record->questionid);
        $this->assertEquals((string) $pkgversionid, $record->pkgversionid);
        $this->assertEquals($state, $record->state);
    }
}
