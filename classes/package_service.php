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
use context_user;
use moodle_exception;
use qtype_questionpy\api\api;
use stored_file;

/**
 * Manages DB entries for QuestionPy packages and, for uploaded packages, their stored files.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2023 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class package_service {

    /** @var api */
    private api $api;

    /**
     * Initializes the instance.
     *
     * @param api $api
     */
    public function __construct(api $api) {
        $this->api = $api;
    }

    /** @var string file area containing packages */
    private const FILE_AREA = "package";

    /**
     * Stores the uploaded draft package file if necessary.
     *
     * * Extracts the package's info
     * * Ensures that a database record exists for it
     * * Copies the draft with the given ID to the packages file area of the given context
     *
     * @param int $draftid   itemid of the draft file, as contained in the form data of a filepicker
     * @param int $contextid target context for storing the package file (the draft is always retrieved from the user
     *                       context)
     * @return array [id of database record, {@see package} instance, {@see stored_file}]
     * @throws moodle_exception
     */
    public function save_uploaded_draft(int $draftid, int $contextid): array {
        $draftfile = $this->get_draft_file($draftid);
        $package = $this->api->package_extract_info($draftfile);

        global $DB;
        // Use a transaction to ensure that we don't insert the package record if storing the file fails.
        $transaction = $DB->start_delegated_transaction();

        [$packageid] = package::get_record_by_hash($package->hash);
        if ($packageid === null) {
            $packageid = $package->store_in_db($contextid);
        }

        $file = $this->ensure_package_file($draftid, $packageid, $contextid);

        $transaction->allow_commit();
        return [$packageid, $package, $file];
    }

    /**
     * Get the draft {@see stored_file} with the given ID.
     *
     * @param int $draftid itemid of the draft file, as contained in the form data of a filepicker
     * @return stored_file
     * @throws coding_exception if no such draft file exists
     */
    public function get_draft_file(int $draftid): stored_file {
        // Drafts are always stored in the user context.
        global $USER;
        $usercontext = context_user::instance($USER->id);

        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $usercontext->id, "user", "draft", $draftid,
            "itemid, filepath, filename", false
        );
        if (!$files) {
            throw new coding_exception("draft file with id '$draftid' does not exist");
        }
        return reset($files);
    }

    /**
     * Get the package with the given hash from the DB or the QuestionPy server API.
     *
     * If the package isn't found in the DB, then it is retrieved from the API and stored in the DB. If it isn't found
     * by the API either, `null` is returned.
     *
     * @param string $hash hash of the package to look for
     * @return ?array [id of database record, package instance] or null if package not found in DB or API
     * @throws moodle_exception
     */
    public function get_package(string $hash): ?array {
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

    /**
     * If the package with the given ID isn't yet stored in the packages file area, copy the given draft file there.
     *
     * @param int $draftid   itemid of the draft file, as contained in the form data of a filepicker
     * @param int $packageid package record id, which is used as the file item id
     * @param int $contextid target context to look for and store the package file in
     * @return stored_file the already or newly stored file in the package file area
     * @throws coding_exception
     */
    private function ensure_package_file(int $draftid, int $packageid, int $contextid): stored_file {
        $fs = get_file_storage();
        file_save_draft_area_files($draftid, $contextid, "qtype_questionpy", self::FILE_AREA, $packageid);
        $files = $fs->get_area_files($contextid, "qtype_questionpy", self::FILE_AREA, $packageid, "", false);
        $filecount = count($files);
        if ($filecount != 1) {
            throw new coding_exception(
                "$filecount files exist where there should only be one after saving package $packageid"
            );
        }

        return reset($files);
    }
}
