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

namespace qtype_questionpy\local\files;

use coding_exception;
use context_user;
use core\context;
use DateTimeImmutable;
use file_exception;
use moodle_exception;
use stored_file;
use stored_file_creation_exception;

/**
 * Handles files uploaded by trainers in question options.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2025 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class options_file_service {
    // TODO: Support subdirectories.

    // TODO: When a file is deleted while editing a question, we do not currently delete it from the permanent file area (it just
    // gets hidden because it's removed from the metadata). We probably should do so in the future, but we must make sure that
    // previous question versions remain functional _if_ they still exist.

    /** @var string */
    private const FILEAREA_UPLOADS = 'options';

    /**
     * Saves all the files in the given draft item to the permanent file area for the given question.
     *
     * @param int $contextid Context id of the question (NOT the draft area).
     * @param int $questionid
     * @param int $userid User whose draft area should be used, which is most likely the current user.
     * @param int $draftitemid
     * @throws file_exception
     * @throws stored_file_creation_exception
     * @throws coding_exception
     */
    public function save_draft_area_files(int $contextid, int $questionid, int $userid, int $draftitemid): void {
        $fs = get_file_storage();

        $existingfiles = $fs->get_area_files(
            $contextid,
            'qtype_questionpy',
            self::FILEAREA_UPLOADS,
            $questionid,
            includedirs: false
        );
        $existingfilerefs = array_map(fn($file) => $file->get_filename(), $existingfiles);

        // Copy all draft files to the "permanent" file area and collect their metadata.
        $draftfiles = $fs->get_area_files(context_user::instance($userid)->id, 'user', 'draft', $draftitemid, includedirs: false);
        foreach ($draftfiles as $draftfile) {
            $fileref = qpy_file_ref::from_stored_file($draftfile);
            // If the user didn't modify/remove the file, it would already be saved and the file ref not changed.
            if (!in_array(strval($fileref), $existingfilerefs)) {
                $fs->create_file_from_storedfile([
                    'component' => 'qtype_questionpy',
                    'filearea' => 'options',
                    'itemid' => $questionid,
                    'contextid' => $contextid,
                    'filepath' => '/',
                    'filename' => $fileref,
                ], $draftfile);
            }
        }
    }

    /**
     * Populates the given draft area with files listed in `$metadata` and stored in the permanent question file area.
     *
     * (The inverse of {@see save_draft_area_files}.)
     *
     * @param int $contextid Context id of the question (NOT the draft area).
     * @param int $questionid
     * @param array<string, file_metadata> $metadata
     * @param int $userid
     * @param int $draftitemid
     * @throws file_exception
     * @throws stored_file_creation_exception
     * @throws coding_exception
     */
    public function prepare_draft_area(int $contextid, int $questionid, array $metadata, int $userid, int $draftitemid): void {
        $fs = get_file_storage();
        $files = $fs->get_area_files($contextid, 'qtype_questionpy', self::FILEAREA_UPLOADS, $questionid, includedirs: false);

        foreach ($metadata as $mfilename => $filemetadata) {
            $matchingfiles = array_filter($files, fn($file) => $file->get_filename() === $filemetadata->fileref);
            if (!$matchingfiles) {
                debugging("Options file '$mfilename' with file_ref '$filemetadata->fileref' could be found in storage.");
                continue;
            }

            $count = count($matchingfiles);
            if ($count > 1) {
                // This would mean that the file area contains two files with the same filename, which I'm not sure is possible.
                debugging("Options file '$mfilename' has ambiguous file_ref '$filemetadata->fileref' which matches $count files.");
            }

            $file = reset($matchingfiles);

            $fs->create_file_from_storedfile([
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $draftitemid,
                'contextid' => context_user::instance($userid)->id,
                'filepath' => '/',
                'filename' => $mfilename,
            ], $file);
        }
    }

    /**
     * Get the {@see file_metadata} for all the files stored in the given draft area item.
     *
     * @param int $userid
     * @param int $draftitemid
     * @return array<string, file_metadata>
     * @throws moodle_exception
     * @throws coding_exception
     */
    public function get_qpy_files_metadata_from_draftitem(int $userid, int $draftitemid): array {
        $fs = get_file_storage();
        $files = $fs->get_area_files(context_user::instance($userid)->id, 'user', 'draft', $draftitemid, includedirs: false);

        $metadata = [];
        foreach ($files as $file) {
            $fileref = qpy_file_ref::from_stored_file($file);
            $metadata[$file->get_filename()] = new file_metadata(
                fileref: $fileref,
                uploadedat: DateTimeImmutable::createFromFormat('U', $file->get_timemodified()),
                mimetype: $file->get_mimetype()
            );
        }
        return $metadata;
    }

    /**
     * Retrieves a saved file from the file area belonging to the given question.
     *
     * @param int $contextid
     * @param int $questionid
     * @param string $fileref
     * @return stored_file|null
     * @throws coding_exception
     */
    public function get_saved_file(int $contextid, int $questionid, string $fileref): ?stored_file {
        $fs = get_file_storage();
        $files = $fs->get_area_files($contextid, 'qtype_questionpy', self::FILEAREA_UPLOADS, $questionid, includedirs: false);
        foreach ($files as $file) {
            if ($file->get_filename() === $fileref) {
                return $file;
            }
        }
        return null;
    }

    /**
     * Create a `qpy://` url for the given file ref.
     *
     * @param string $fileref
     * @return string
     */
    public static function create_qpy_url(string $fileref): string {
        return "qpy://options/$fileref";
    }
}
