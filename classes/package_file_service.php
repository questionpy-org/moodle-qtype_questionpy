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
use dml_exception;
use stored_file;

/**
 * Manages DB entries for QuestionPy packages and, for uploaded packages, their stored files.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2023 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class package_file_service {
    /**
     * Get a draft {@see stored_file} with the given ID.
     *
     * @param int $draftid item id of the draft file, as contained in the form data of a file picker
     * @return stored_file
     * @throws coding_exception if no such draft file exists
     */
    public function get_draft_file(int $draftid): stored_file {
        // Drafts are always stored in the user context.
        global $USER;
        $usercontext = context_user::instance($USER->id);

        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $usercontext->id,
            component: 'user',
            filearea: 'draft',
            itemid: $draftid,
            includedirs: false,
            limitnum: 1
        );
        if (!$files) {
            throw new coding_exception("draft file with id '$draftid' does not exist");
        }
        return reset($files);
    }

    /**
     * Assumes that the question with the given id uses a local package and returns its package file.
     *
     * @param int $qpyid the id of the `qtype_questionpy` record
     * @param int $contextid context id of the question, e.g. {@see \question_definition::$contextid}
     * @return stored_file
     * @throws coding_exception if no package file can be found for the given question, such as if the question isn't
     *                          local after all.
     */
    public function get_file_for_local_question(int $qpyid, int $contextid): stored_file {
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $contextid,
            component: 'qtype_questionpy',
            filearea: 'package',
            itemid: $qpyid,
            includedirs: false,
            limitnum: 1
        );
        if (!$files) {
            throw new coding_exception("Package file with qpy id '$qpyid' does not exist.");
        }
        return reset($files);
    }

    /**
     * If any question uses a manually uploaded package with the given hash, return the file. Otherwise, return null.
     *
     * @param string $packagehash
     * @param int $contextid context id of the question, e.g. {@see \question_definition::$contextid}
     * @return stored_file|null
     * @throws dml_exception
     * @throws coding_exception
     */
    public function get_file_by_package_hash(string $packagehash, int $contextid): ?stored_file {
        global $DB;
        $qpyid = $DB->get_field("qtype_questionpy", "id", [
            "islocal" => true,
            "pkgversionhash" => $packagehash,
        ], IGNORE_MULTIPLE);

        if ($qpyid === false) {
            // No question uses an uploaded (aka local) package with that hash.
            return null;
        } else {
            return $this->get_file_for_local_question($qpyid, $contextid);
        }
    }
}
