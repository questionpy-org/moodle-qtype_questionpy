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
            $usercontext->id, 'user', 'draft', $draftid,
            'itemid, filepath, filename', false
        );
        if (!$files) {
            throw new coding_exception("draft file with id '$draftid' does not exist");
        }
        return reset($files);
    }

    /**
     * Get a {@see stored_file} with the given ID.
     *
     * @param int $qpyid the id of the `qtype_questionpy` record
     * @param int $contextid
     * @return stored_file
     * @throws coding_exception if no such draft file exists
     */
    public function get_file(int $qpyid, int $contextid): stored_file {
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $contextid, 'qtype_questionpy', 'package', $qpyid,
            'itemid, filepath, filename', false
        );
        if (!$files) {
            throw new coding_exception("package file with qpy id '$qpyid' does not exist");
        }
        return reset($files);
    }
}
