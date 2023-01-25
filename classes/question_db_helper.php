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

use dml_exception;
use moodle_exception;
use qtype_questionpy\form\form_name_mangler;
use stdClass;

/**
 * Utility class for managing QuestionPy-specific question options in the DB.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_db_helper {

    /** @var api */
    private api $api;

    /**
     * Initializes the instance to use the given {@see api}.
     *
     * @param api $api
     */
    public function __construct(api $api) {
        $this->api = $api;
    }

    /** @var string table containing our question data, 0-1 record per question */
    private const QUESTION_TABLE = "qtype_questionpy";

    /**
     * Retrieves the QuestionPy-specific question fields from the database and returns them in an associative array.
     *
     * @param int $questionid ID of the question (`question.id`, not `qtype_questionpy.id`)
     * @return object QuestionPy-specific question fields in an associative array
     * @throws dml_exception
     * @throws \coding_exception
     */
    public function get_question(int $questionid): object {
        global $DB;

        $result = new stdClass();
        $record = $DB->get_record(self::QUESTION_TABLE, ["questionid" => $questionid]);
        if ($record) {
            $package = package::get_records(["id" => $record->packageid])[0] ?? null;
            if (!$package) {
                throw new \coding_exception(
                    "No package record with ID '{$record->packageid}' was found despite being referenced by" .
                    " question {$questionid}"
                );
            }
            $result->qpy_package_hash = $package->hash;

            $result->qpy_state = $record->state;
            $state = json_decode($record->state, true);
            foreach ($state as $name => $value) {
                $result->{form_name_mangler::mangle($name)} = $value;
            }
        }

        return $result;
    }

    /**
     * Inserts or updates the QuestionPy-specific data for this question in the database.
     *
     * @param object $question question data, NOT an instance of {@see \question_definition}
     * @return void
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function upsert_question(object $question): void {
        global $DB;

        [$packageid] = $this->get_package($question->qpy_package_hash);
        if (!$packageid) {
            throw new moodle_exception(
                "package_not_found", "qtype_questionpy", "", (object)[
                    "packagehash" => $question->qpy_package_hash
                ]
            );
        }

        $existingrecord = $DB->get_record(self::QUESTION_TABLE, [
            "questionid" => $question->id,
        ]);
        if ($existingrecord) {
            // Question record already exists, update it if necessary.
            $update = ["id" => $existingrecord->id];
            $oldstate = json_decode($existingrecord->state, true);
            $newstate = $this->options_to_state($question, $oldstate);

            if ($oldstate !== $newstate) {
                $update["state"] = json_encode($newstate);
            }
            if ($packageid !== $existingrecord->packageid) {
                $update["packageid"] = $packageid;
            }

            if (count($update) > 1) {
                $DB->update_record(self::QUESTION_TABLE, (object)$update);
            }
        } else {
            // Insert a new record with the question state only containing the options.
            $state = $this->options_to_state($question, []);
            $DB->insert_record(self::QUESTION_TABLE, [
                "questionid" => $question->id,
                "feedback" => "",
                "packageid" => $packageid,
                "state" => json_encode($state),
            ]);
        }
    }

    /**
     * Deletes all QuestionPy-specific data for the given question.
     *
     * @param int $questionid
     * @throws dml_exception
     */
    public function delete_question(int $questionid) {
        global $DB;
        $DB->delete_records(self::QUESTION_TABLE, ['questionid' => $questionid]);
        // TODO: Also delete packages when they are no longer used by any question.
    }

    /**
     * Updates the state to contain all package-specific form options in the given question or creates a new state.
     *
     * TODO: Eventually, the question state will be an opaque string or JSON string whose content we don't directly
     * access, but which we send to the package and which it uses to generate the new form definition for us. For now,
     * however, we assume that the state contains the options and access them directly.
     *
     * @param object $question question data, NOT an instance of {@see \question_definition}
     * @param array $state     previous state of the question
     * @return array new state of the question, with the form options added or updated
     */
    private function options_to_state(object $question, array $state): array {
        foreach ($question as $key => $value) {
            $unmangled = form_name_mangler::unmangle($key);
            if (!$unmangled) {
                // Not a package-specific form option.
                continue;
            }

            $state[$unmangled] = $value;
        }

        return $state;
    }

    /**
     * Get the package with the given hash from the DB or the QuestionPy server API.
     *
     * If the package isn't found in the DB, then it is retrieved from the API and stored in the DB. If it isn't found
     * by the API either, `null` is returned.
     *
     * @param string $hash hash of the package to look for
     * @return ?array [id of database record, package instance] or null if package not found in DB or API
     * @throws dml_exception
     * @throws moodle_exception
     */
    private function get_package(string $hash): ?array {
        $result = package::get_record_by_hash($hash);
        if ($result) {
            return $result;
        }

        $package = $this->api->get_package($hash);
        if (!$package) {
            return null;
        }
        return [$package->store_in_db(), $package];
    }
}
