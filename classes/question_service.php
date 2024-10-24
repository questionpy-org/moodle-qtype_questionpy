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
use qtype_questionpy\package\package;
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

    /** @var package_file_service */
    private package_file_service $packagefileservice;

    /**
     * Initializes the instance to use the given {@see api}.
     *
     * @param api $api
     * @param package_file_service $packagefileservice
     */
    public function __construct(api $api, package_file_service $packagefileservice) {
        $this->api = $api;
        $this->packagefileservice = $packagefileservice;
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
            $files = $filestorage->get_area_files(
                $PAGE->context->get_course_context()->id,
                'qtype_questionpy',
                'package',
                $record->id,
                includedirs: false,
                limitnum: 1,
            );
            if (!$files) {
                throw new \coding_exception(
                    "No local package version file with hash '{$record->pkgversionhash}' was found despite being referenced" .
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

        $filestorage = get_file_storage();
        $question->qpy_package_hash ??= $question->qpy_package_file_hash;

        $file = null;
        if ($question->qpy_package_source === 'upload') {
            if (isset($question->qpy_package_path_name_hash)) {
                $file = $filestorage->get_file_by_hash($question->qpy_package_path_name_hash);
            } else {
                $file = $this->packagefileservice->get_draft_file($question->qpy_package_file);
            }
            $rawpackage = api::extract_package_info($file);
            $pkgversionhash = $rawpackage->hash;
            $pkgversionnamespace = $rawpackage->namespace;
            $pkgversionshortname = $rawpackage->shortname;
        } else {
            $pkgversion = package_version::get_by_hash($question->qpy_package_hash) ?? null;
            if ($pkgversion) {
                $package = package::get_by_version($pkgversion->id);
                $pkgversionhash = $pkgversion->hash;
                $pkgversionnamespace = $package->namespace;
                $pkgversionshortname = $package->shortname;
                last_used_service::add($question->context->id, $package->id);
            } else {
                $packageinfo = $this->api->get_package_info($question->qpy_package_hash);
                $pkgversionhash = $packageinfo->hash;
                $pkgversionnamespace = $packageinfo->namespace;
                $pkgversionshortname = $packageinfo->shortname;
            }
        }

        $existingrecord = $DB->get_record(self::QUESTION_TABLE, [
            'questionid' => $question->id,
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
            $update = ['id' => $existingrecord->id];

            if ($existingrecord->pkgversionhash !== $pkgversionhash) {
                $update['pkgversionhash'] = $pkgversionhash;
            }

            if ($existingrecord->state !== $response->state) {
                $update['state'] = $response->state;
            }

            if (count($update) > 1) {
                $DB->update_record(self::QUESTION_TABLE, (object) $update);
            }
        } else {
            $islocal = $question->qpy_package_source === 'upload';
            // Insert a new record with the question state only containing the options.
            $questionid = $DB->insert_record(self::QUESTION_TABLE, [
                'questionid' => $question->id,
                // TODO: retrieve the identifier directly from the package?
                'packageidentifier' => "@{$pkgversionnamespace}/{$pkgversionshortname}",
                'pkgversionhash' => $question->qpy_package_hash,
                'islocal' => $islocal,
                'state' => $response->state,
            ]);
            if ($islocal) {
                if (isset($question->qpy_package_path_name_hash)) {
                    $filestorage->create_file_from_storedfile([
                        'itemid' => $questionid,
                    ], $file);
                } else {
                    // Get draft file and store the file.
                    file_save_draft_area_files(
                        $question->qpy_package_file,
                        $question->context->id,
                        'qtype_questionpy',
                        'package',
                        $questionid
                    );
                }
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
}
