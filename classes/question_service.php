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
use qtype_questionpy\api\api;
use qtype_questionpy\package\package_version;
use stdClass;

/**
 * Manages QuestionPy-specific question options in the DB.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_service {

    /** @var api */
    private api $api;

    /** @var package_service */
    private package_service $packageservice;

    /**
     * Initializes the instance to use the given {@see api}.
     *
     * @param api $api
     * @param package_service $packageservice
     */
    public function __construct(api $api, package_service $packageservice) {
        $this->api = $api;
        $this->packageservice = $packageservice;
    }

    /** @var string table containing our question data, 0-1 record per question */
    private const QUESTION_TABLE = "qtype_questionpy";

    /**
     * Retrieves the QuestionPy-specific question fields from the database and returns them in an associative array.
     *
     * @param int $questionid ID of the question (`question.id`, not `qtype_questionpy.id`)
     * @return object QuestionPy-specific question fields in an associative array
     * @throws moodle_exception
     */
    public function get_question(int $questionid): object {
        global $DB, $PAGE;

        $record = $DB->get_record(self::QUESTION_TABLE, ['questionid' => $questionid]);
        $result = new stdClass();

        if ($record === false) {
            return $result;
        }

        if ($record->islocal) {
            // Package was uploaded.
            $filestorage = get_file_storage();
            $files = $filestorage->get_area_files($PAGE->context->get_course_context()->id, 'qtype_questionpy',
                'package', $record->id, 'itemid, filepath, filename', false);
            if (count($files) === 0) {
                throw new \coding_exception(
                    "No local package version file with hash '{$record->pkgversionhash}' was found despite being referenced" .
                    " by question {$questionid}"
                );
            }
        } else {
            // Package was selected.
            $package = package_version::get_by_id($record->pkgversionid);
            if (is_null($package)) {
                throw new \coding_exception(
                    "No package versiotsetn record with ID '{$record->pkgversionid}' was found despite being referenced" .
                    " by question {$questionid}"
                );
            }
        }
        $result->qpy_id = $record->id;
        $result->qpy_package_hash = $record->pkgversionhash;
        $result->qpy_state = $record->state;
        $result->qpy_is_local = $record->islocal;
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

        if (!isset($question->qpy_form)) {
            // This happens when the package defines an empty options form, which we do want to support.
            $question->qpy_form = [];
        }

        $question->qpy_package_hash ??= $question->qpy_package_file_hash;

        $file = null;
        $pkgversionid = null;
        if ($question->qpy_package_source === 'upload') {
            $file = $this->packageservice->get_draft_file($question->qpy_package_file);
        } else if ($question->qpy_package_source === 'local') {
            $existingrecord = $DB->get_record(self::QUESTION_TABLE, [
                'questionid' => $question->oldparent,
            ]);
            $file = $this->packageservice->get_file($existingrecord->id, $question->context->id);
        } else {
            $pkgversionid = $this->get_package($question->qpy_package_hash);
            if (!$pkgversionid) {
                throw new moodle_exception(
                    'package_not_found', 'qtype_questionpy', '',
                    (object)['packagehash' => $question->qpy_package_hash]
                );
            }
        }

        $existingrecord = $DB->get_record(self::QUESTION_TABLE, [
            'questionid' => $question->oldparent,
        ]);

        // Repetition_elements may produce numeric arrays with gaps. We want them to become JSON arrays, so we reindex.
        // Form element names may not begin with a digit, so this won't accidentally change them.
        utils::reindex_integer_arrays($question->qpy_form);

        $response = $this->api->package($question->qpy_package_hash, $file)->create_question(
            $existingrecord ? $existingrecord->state : null,
            (object)$question->qpy_form
        );

        if ($existingrecord) {
            // Question record already exists, update it if necessary.
            $update = ['id' => $existingrecord->id, 'questionid' => $question->id];

            if ($existingrecord->state !== $response->state) {
                $update['state'] = $response->state;
            }
            if ($pkgversionid !== $existingrecord->pkgversionid) {
                $update['pkgversionid'] = $pkgversionid;
            }

            if (count($update) > 2) {
                $DB->update_record(self::QUESTION_TABLE, (object) $update);
            }
        } else {
            // Insert a new record with the question state only containing the options.
            $questionid = $DB->insert_record(self::QUESTION_TABLE, [
                'questionid' => $question->id,
                'feedback' => '',
                'pkgversionhash' => $question->qpy_package_hash,
                'pkgversionid' => $pkgversionid,
                'islocal' => $question->qpy_package_source !== 'search',
                'state' => $response->state,
            ]);
            if ($question->qpy_package_source === 'upload') {
                // Get draft file and store the file.
                file_save_draft_area_files($question->qpy_package_file, $question->context->id,
                    'qtype_questionpy', 'package', $questionid);
            }
        }
    }

    /**
     * Deletes all QuestionPy-specific data for the given question.
     *
     * @param int $questionid
     * @throws dml_exception
     */
    public static function delete_question(int $questionid) {
        global $DB;
        $DB->delete_records(self::QUESTION_TABLE, ['questionid' => $questionid]);
        // TODO: Also delete packages when they are no longer used by any question.
    }

    /**
     * Get the package id with the given hash from the DB or the QuestionPy server API.
     *
     * If the package isn't found in the DB, then it is retrieved from the API and stored in the DB. If it isn't found
     * by the API either, `null` is returned.
     *
     * @param string $hash hash of the package to look for
     * @return int|null
     * @throws dml_exception
     * @throws moodle_exception
     */
    private function get_package(string $hash): ?int {
        $result = package_version::get_by_hash($hash)->id ?? null;
        if ($result) {
            return $result;
        }

        $package = $this->api->get_package($hash);
        if (!$package) {
            return null;
        }
        return $package->store();
    }
}
