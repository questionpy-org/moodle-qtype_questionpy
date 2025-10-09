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
use file_exception;
use Generator;
use qtype_questionpy\constants;
use qtype_questionpy\local\attempt_ui\qpy_file_upload;
use question_attempt;
use stored_file;
use stored_file_creation_exception;

/**
 * Handles files uploaded by students in attempts.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2025 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class response_file_service {
    /** @var string Chosen for being allowed by {@see PARAM_FILE} while being encoded away by {@see urlencode}. */
    private const MANGLE_SEPARATOR = '!';

    /**
     * Combines the given draft areas into a single draft area, using subdirs derived from the upload field names.
     *
     * @param array $draftareas Array of upload field names to their draft item ids.
     * @param int $targetdraftarea
     * @param int $userid
     * @return int The item id of the resulting combined draft area.
     * @throws coding_exception
     * @throws file_exception
     * @throws stored_file_creation_exception
     */
    public function combine_response_file_draft_areas(array $draftareas, int $targetdraftarea, int $userid): int {
        // TODO: Check file size & count restrictions.

        if (!$draftareas) {
            return $targetdraftarea;
        }

        $fs = get_file_storage();
        $usercontext = context_user::instance($userid);

        // We get both the input and the existing target draft files at once, for performance.
        $allfiles = $fs->get_area_files(
            $usercontext->id,
            'user',
            'draft',
            [...array_values($draftareas), $targetdraftarea],
            includedirs: false
        );

        // Split into existing and input files. In most cases, the page will be reloaded between saves, so we won't use the same
        // combined draft area twice. When saving via AJAX (such as autosaves), though, we do, so it might not be empty.
        $inputfiles = [];
        $existingfiles = [];
        foreach ($allfiles as $file) {
            if ($file->get_itemid() == $targetdraftarea) {
                $existingfiles[$file->get_filename()] = $file;
            } else {
                $inputfiles[$file->get_filename()] = $file;
            }
        }

        foreach ($inputfiles as $draftfile) {
            $fieldname = array_search($draftfile->get_itemid(), $draftareas);
            $mangledname = self::mangle_filename($fieldname, $draftfile->get_filename());

            $existingfile = $existingfiles[$mangledname] ?? null;
            if ($existingfile) {
                // We could implement smarter merging here, but for now we just delete the existing file.
                $existingfile->delete();
                unset($existingfiles[$mangledname]);
            }

            $fs->create_file_from_storedfile([
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $targetdraftarea,
                'contextid' => $usercontext->id,
                'filename' => $mangledname,
            ], $draftfile);
        }

        // Any files remaining now have been removed since the last save.
        foreach ($existingfiles as $removedfile) {
            $removedfile->delete();
        }

        return $targetdraftarea;
    }


    /**
     * Validates that the combined draft area follows the limits imposed by the given {@see qpy_file_upload}s.
     *
     * @param int $draftareaid
     * @param qpy_file_upload[] $uploadfields
     * @param int $userid
     * @param context $attemptcontext
     * @return void
     * @throws coding_exception
     */
    public function validate_combined_draft_area(
        int $draftareaid,
        array $uploadfields,
        int $userid,
        context $attemptcontext
    ): void {
        $fs = get_file_storage();
        $usercontext = context_user::instance($userid);

        $allfiles = $fs->get_area_files(
            $usercontext->id,
            'user',
            'draft',
            $draftareaid,
            includedirs: false
        );

        /** @var array<string, array<string, stored_file>> $filesbyfield */
        $filesbyfield = [];
        foreach ($allfiles as $file) {
            [$fieldname, $filename] = self::unmangle_filename($file->get_filename());
            $filesbyfield[$fieldname][$filename] = $file;
        }

        foreach ($filesbyfield as $fieldname => $files) {
            $uploadfield = $uploadfields[$fieldname] ?? null;
            if (!$uploadfield) {
                throw new coding_exception("There were files uploaded for field '$fieldname', but no corresponding upload field "
                    . 'was found.');
            }

            $uploadfield->get_limits_in($attemptcontext)
                ->validate_files($files, "upload field '$fieldname'");
        }
    }

    /**
     * Given a {@see combine_response_file_draft_areas combined file area}, yields the files belonging to the given fieldname.
     *
     * The yielded files are keyed by their original, unmangled, filename.
     *
     * @param array $files
     * @param string $fieldname
     * @return Generator<string, stored_file>
     * @throws coding_exception
     */
    public static function filter_combined_files_for_field(array $files, string $fieldname): Generator {
        foreach ($files as $file) {
            [$filefieldname, $filename] = self::unmangle_filename($file->get_filename());
            if ($filefieldname === $fieldname) {
                yield $filename => $file;
            }
        }
    }

    /**
     * Out of the files belonging to a question attempt, copies the ones that belong to the given fieldname to the given draft area.
     *
     * @param int $contextid The attempt's context (not necessarily the question's). See {@see question_display_options::$context}.
     * @param question_attempt $qa
     * @param string $fieldname
     * @param int $userid
     * @param int $draftitemid
     * @throws coding_exception
     * @throws file_exception
     * @throws stored_file_creation_exception
     */
    public function prepare_draft_area(
        int $contextid,
        question_attempt $qa,
        string $fieldname,
        int $userid,
        int $draftitemid
    ): void {
        $fs = get_file_storage();
        $allfiles = $qa->get_last_qt_files(constants::QT_VAR_RESPONSE_FILES, $contextid);

        foreach (self::filter_combined_files_for_field($allfiles, $fieldname) as $filename => $file) {
            $fs->create_file_from_storedfile([
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $draftitemid,
                'contextid' => context_user::instance($userid)->id,
                'filename' => $filename,
            ], $file);
        }
    }

    /**
     * Mangles a filename for storage in the combined draft area.
     *
     * @param string $fieldname The upload field name the file belongs to.
     * @param string $filename The original filename.
     * @return string
     */
    public static function mangle_filename(string $fieldname, string $filename): string {
        // URL-encoding the fieldname ensures that our separator is the first occurrence of the separator.
        return urlencode($fieldname) . static::MANGLE_SEPARATOR . $filename;
    }

    /**
     * Unmangles a filename from the combined draft area.
     *
     * @param string $filename
     * @return array{0: string, 1: string} The upload field the file belongs to and the original filename.
     * @throws coding_exception
     */
    public static function unmangle_filename(string $filename): array {
        if (!str_contains($filename, static::MANGLE_SEPARATOR)) {
            throw new coding_exception("Filename '$filename' is not mangled.");
        }
        [$urlencfieldname, $filename] = explode(static::MANGLE_SEPARATOR, $filename, 2);
        return [urldecode($urlencfieldname), $filename];
    }
}
