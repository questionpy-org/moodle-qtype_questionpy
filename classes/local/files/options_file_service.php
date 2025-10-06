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
use context;
use context_user;
use DateTimeImmutable;
use file_exception;
use moodle_exception;
use moodle_url;
use qtype_questionpy\constants;
use qtype_questionpy\local\form\elements\file_upload_element;
use qtype_questionpy\local\form\elements\file_upload_options;
use qtype_questionpy_question;
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
class options_file_service implements handles_qpy_url_type {
    // TODO: Support subdirectories.

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
            constants::FILEAREA_OPTIONS,
            $questionid,
            includedirs: false
        );
        $existingfilerefs = array_map(fn($file) => $file->get_filename(), $existingfiles);

        // Copy all draft files to the "permanent" file area and collect their metadata.
        $draftfiles = $fs->get_area_files(context_user::instance($userid)->id, 'user', 'draft', $draftitemid, includedirs: false);
        foreach ($draftfiles as $draftfile) {
            $fileref = qpy_file_ref::from_stored_file($draftfile);
            // If the file ref already exists, the user just didn't modify/remove the file.
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
     * Populates the given draft area with files listed in `$filemetas` and stored in the permanent question file area.
     *
     * (The inverse of {@see save_draft_area_files}.)
     *
     * @param int $contextid Context id of the question (NOT the draft area).
     * @param int $questionid
     * @param file_metadata[] $filemetas
     * @param int $userid
     * @param int $draftitemid
     * @throws file_exception
     * @throws stored_file_creation_exception
     * @throws coding_exception
     */
    public function prepare_draft_area(int $contextid, int $questionid, array $filemetas, int $userid, int $draftitemid): void {
        $fs = get_file_storage();
        $files = $fs->get_area_files($contextid, 'qtype_questionpy', constants::FILEAREA_OPTIONS, $questionid, includedirs: false);

        foreach ($filemetas as $filemetadata) {
            $matchingfiles = array_filter($files, fn($file) => $file->get_filename() === $filemetadata->fileref);
            if (!$matchingfiles) {
                debugging("Options file '$filemetadata->filename' with file_ref '$filemetadata->fileref' could not be found in "
                    . 'storage.');
                continue;
            }

            $count = count($matchingfiles);
            if ($count > 1) {
                // This would mean that the file area contains two files with the same filename, which I'm not sure is possible.
                debugging("Options file '$filemetadata->filename' has ambiguous file_ref '$filemetadata->fileref' which matches "
                    . "$count files.");
            }

            $file = reset($matchingfiles);

            $fs->create_file_from_storedfile([
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $draftitemid,
                'contextid' => context_user::instance($userid)->id,
                'filepath' => '/',
                'filename' => $filemetadata->filename,
            ], $file);
        }
    }

    /**
     * Get the {@see file_metadata} for all the files stored in the given draft area item.
     *
     * @param int $userid
     * @param int $draftitemid
     * @return file_metadata[]
     * @throws moodle_exception
     * @throws coding_exception
     */
    public function get_qpy_files_metadata_from_draftitem(int $userid, int $draftitemid): array {
        $fs = get_file_storage();
        $files = $fs->get_area_files(context_user::instance($userid)->id, 'user', 'draft', $draftitemid, includedirs: false);

        $metadata = [];
        foreach ($files as $file) {
            $fileref = qpy_file_ref::from_stored_file($file);
            $metadata[] = new file_metadata(
                path: $file->get_filepath(),
                filename: $file->get_filename(),
                fileref: $fileref,
                uploadedat: DateTimeImmutable::createFromFormat('U', $file->get_timemodified()),
                mimetype: $file->get_mimetype(),
                size: $file->get_filesize(),
            );
        }

        return $metadata;
    }

    /**
     * Checks that `maxfiles`, `maxbytestotal` and `maxbytesperfile` (or whatever the LMS overrides it with) are fulfilled.
     *
     * Breaking any of the three by accident should be impossible through UI design or checked by earlier parts of the Moodle code.
     * It might be possible if intentional, so we also check it here, but just throw unhelpful {@see coding_exception}s.
     *
     * `minfiles` is _not_ checked. Since that option isn't really security-relevant, it's left to the package/SDK.
     *
     * @param file_upload_options|file_upload_element $options
     * @param int $contextid
     * @param int|null $questionid Question being edited, or `null` if a new question is being created.
     * @param int $draftitemid
     * @param file_metadata[] $draftfilemetas
     * @return void An exception is thrown if the restrictions are not fulfilled.
     * @throws coding_exception
     */
    public function check_upload_restrictions(
        file_upload_options|file_upload_element $options,
        int $contextid, ?int $questionid, int $draftitemid, array $draftfilemetas
    ): void {
        global $CFG;
        $fs = get_file_storage();

        if ($options->maxfiles !== null && count($draftfilemetas) > $options->maxfiles) {
            throw new coding_exception("Draft area $draftitemid exceeds limit of $options->maxfiles files.");
        }

        if (file_is_draft_area_limit_reached($draftitemid, $options->maxbytestotal ?? FILE_AREA_MAX_BYTES_UNLIMITED)) {
            throw new coding_exception("Draft area $draftitemid exceeds limit of $options->maxbytestotal bytes.");
        }

        $coursemaxbytes = 0;
        if (!empty($PAGE->course->maxbytes)) {
            $coursemaxbytes = $PAGE->course->maxbytes;
        }

        $effectivemaxbytes = get_user_max_upload_file_size(
            context::instance_by_id($contextid),
            $CFG->maxbytes,
            $coursemaxbytes,
            $options->maxbytesperfile ?? FILE_AREA_MAX_BYTES_UNLIMITED
        );

        if ($effectivemaxbytes === USER_CAN_IGNORE_FILE_SIZE_LIMITS) {
            // No need to check then.
            return;
        }

        $existingfiles = $questionid ? $fs->get_area_files(
            $contextid,
            'qtype_questionpy',
            constants::FILEAREA_OPTIONS,
            $questionid,
            includedirs: false
        ) : [];
        $existingfilerefs = array_map(fn($file) => $file->get_filename(), $existingfiles);

        foreach ($draftfilemetas as $draftfilemeta) {
            if (in_array($draftfilemeta->fileref, $existingfilerefs)) {
                // The file (a file with the same content and metadata) had already been successfully uploaded.
                // The current user might have changed its path or filename or even moved it to a different upload element, but
                // since the original uploader must've been allowed to upload it, we don't check whether the current user would be
                // allowed to upload it again.
                // TODO: Catch when a file is copied/moved by the package to a different element with a lower file size limit.
                // That would probably need to happen at import (when loading the form data from the package), not here.
                continue;
            }

            if ($draftfilemeta->size > $effectivemaxbytes) {
                throw new coding_exception("File '{$draftfilemeta->path}{$draftfilemeta->filename}' exceeds limit of"
                    . " $effectivemaxbytes bytes.");
            }
        }
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
        $files = $fs->get_area_files($contextid, 'qtype_questionpy', constants::FILEAREA_OPTIONS, $questionid, includedirs: false);
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

    /**
     * Converts a QPy-URL to a functioning pluginfile URL.
     *
     * This method isn't passed the entire URL, but everything after the `qpy://<type>/` prefix. The slash between type and path
     * isn't included in `$path`. See also {@see qpy_url_resolver::QPY_URL_PATTERN}.
     *
     * @param string $path
     * @param qtype_questionpy_question $question
     * @return string
     */
    public function resolve_qpy_url(string $path, qtype_questionpy_question $question): string {
        if (!$question->id) {
            debugging("Can't generate URL for file belonging to a non-saved question.");
            return new moodle_url('/brokenfile.php');
        }

        return moodle_url::make_pluginfile_url(
            $question->contextid,
            'qtype_questionpy',
            constants::FILEAREA_OPTIONS,
            $question->id,
            '/',
            $path
        )->out();
    }

    /**
     * Serves a plugin file belonging to this implementation.
     *
     * The arguments are passed directly from {@see qtype_questionpy_pluginfile}.
     *
     * This method never returns.
     *
     * @param object $context
     * @param array $args
     * @return never
     * @throws coding_exception
     */
    public function serve_pluginfile(object $context, array $args): never {
        [$questionid, $fileref] = $args;
        $file = $this->get_saved_file($context->id, $questionid, $fileref);
        send_stored_file($file);
        die();
    }
}
