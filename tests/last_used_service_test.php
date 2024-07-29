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

use moodle_exception;

/**
 * Unit tests for {@see last_used_service}.
 *
 * @package    qtype_questionpy
 * @copyright  2024 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class last_used_service_test extends \advanced_testcase {
    /**
     * Tests {@see last_used_service::add()} create correct timestamp.
     *
     * @throws moodle_exception
     * @covers \qtype_questionpy\last_used_service::add
     */
    public function test_add_creates_entry(): void {
        global $DB;
        $this->resetAfterTest();

        $contextid = 0;
        $packageid = 0;

        last_used_service::add($contextid, $packageid);

        $record = $DB->get_record('qtype_questionpy_lastused', ['contextid' => $contextid, 'packageid' => $packageid]);
        $this->assertNotFalse($record);
        $this->assertTimeCurrent($record->timeused);
    }

    /**
     * Tests {@see last_used_service::add()} only updates the timestamp if the context-package-combination is already in
     * the table.
     *
     * @throws moodle_exception
     * @covers \qtype_questionpy\last_used_service::add
     */
    public function test_add_updates_timestamp_if_package_was_already_used(): void {
        global $DB;
        $this->resetAfterTest();

        $contextid = 0;
        $packageid = 0;

        last_used_service::add($contextid, $packageid);
        $oldrecord = $DB->get_record('qtype_questionpy_lastused', ['contextid' => $contextid, 'packageid' => $packageid]);

        $this->waitForSecond();

        last_used_service::add($contextid, $packageid);
        $newrecord = $DB->get_record('qtype_questionpy_lastused', ['contextid' => $contextid, 'packageid' => $packageid]);

        $this->assertTimeCurrent($newrecord->timeused);
        $this->assertGreaterThan($oldrecord->timeused, $newrecord->timeused);

        $count = $DB->count_records('qtype_questionpy_lastused');
        $this->assertEquals(1, $count);
    }

    /**
     * Tests {@see last_used_service::add()} inserts multiple entries when the packages differ.
     *
     * @throws moodle_exception
     * @covers \qtype_questionpy\last_used_service::add
     */
    public function test_add_inserts_entries_if_packages_differ(): void {
        global $DB;
        $this->resetAfterTest();

        $contextid = 0;
        $package1id = 0;
        $package2id = 1;

        last_used_service::add($contextid, $package1id);
        last_used_service::add($contextid, $package2id);

        $entry1 = $DB->record_exists('qtype_questionpy_lastused', ['contextid' => $contextid, 'packageid' => $package1id]);
        $this->assertTrue($entry1);
        $entry2 = $DB->record_exists('qtype_questionpy_lastused', ['contextid' => $contextid, 'packageid' => $package2id]);
        $this->assertTrue($entry2);

        $count = $DB->count_records('qtype_questionpy_lastused');
        $this->assertEquals(2, $count);
    }

    /**
     * Tests {@see last_used_service::add()} inserts multiple entries when the contexts differ.
     *
     * @throws moodle_exception
     * @covers \qtype_questionpy\last_used_service::add
     */
    public function test_add_inserts_entries_if_contexts_differ(): void {
        global $DB;
        $this->resetAfterTest();

        $context1id = 0;
        $context2id = 1;
        $packageid = 0;

        last_used_service::add($context1id, $packageid);
        last_used_service::add($context2id, $packageid);

        $entry1 = $DB->record_exists('qtype_questionpy_lastused', ['contextid' => $context1id, 'packageid' => $packageid]);
        $this->assertTrue($entry1);
        $entry2 = $DB->record_exists('qtype_questionpy_lastused', ['contextid' => $context2id, 'packageid' => $packageid]);
        $this->assertTrue($entry2);

        $count = $DB->count_records('qtype_questionpy_lastused');
        $this->assertEquals(2, $count);
    }

    /**
     * Tests {@see last_used_service::add()} inserts multiple entries when the packages and contexts differ.
     *
     * @throws moodle_exception
     * @covers \qtype_questionpy\last_used_service::add
     */
    public function test_add_inserts_entries_if_packages_and_contexts_differ(): void {
        global $DB;
        $this->resetAfterTest();

        $context1id = 0;
        $context2id = 1;
        $package1id = 0;
        $package2id = 1;

        last_used_service::add($context1id, $package1id);
        last_used_service::add($context2id, $package2id);

        $entry1 = $DB->record_exists('qtype_questionpy_lastused', ['contextid' => $context1id, 'packageid' => $package1id]);
        $this->assertTrue($entry1);
        $entry2 = $DB->record_exists('qtype_questionpy_lastused', ['contextid' => $context2id, 'packageid' => $package2id]);
        $this->assertTrue($entry2);

        $count = $DB->count_records('qtype_questionpy_lastused');
        $this->assertEquals(2, $count);
    }

    /**
     * Provides context counts.
     *
     * @return array[]
     */
    public static function context_count_provider(): array {
        return [
            [0],
            [1],
            [5],
        ];
    }

    /**
     * Tests {@see last_used_service::remove_by_package()} removes every entry with the given package.
     *
     * @param int $contexts
     * @throws moodle_exception
     * @dataProvider context_count_provider
     * @covers \qtype_questionpy\last_used_service::remove_by_package
     */
    public function test_remove_by_package_removes_the_entries(int $contexts): void {
        global $DB;
        $this->resetAfterTest();

        $packageid = 0;

        for ($contextid = 0; $contextid < $contexts; $contextid++) {
            last_used_service::add($contextid, $packageid);
        }

        last_used_service::remove_by_package($packageid);

        $count = $DB->count_records('qtype_questionpy_lastused');
        $this->assertEquals(0, $count);
    }

    /**
     * Tests {@see last_used_service::remove_by_package()} only removes the given package.
     *
     * @throws moodle_exception
     * @covers \qtype_questionpy\last_used_service::remove_by_package
     */
    public function test_remove_by_package_only_removes_the_given_package(): void {
        global $DB;
        $this->resetAfterTest();

        $contextid = 0;
        $package1id = 0;
        $package2id = 1;

        last_used_service::add($contextid, $package1id);
        last_used_service::add($contextid, $package2id);

        last_used_service::remove_by_package($package1id);

        $count = $DB->count_records('qtype_questionpy_lastused');
        $this->assertEquals(1, $count);

        $entry = $DB->record_exists('qtype_questionpy_lastused', ['contextid' => $contextid, 'packageid' => $package2id]);
        $this->assertTrue($entry);
    }
}
