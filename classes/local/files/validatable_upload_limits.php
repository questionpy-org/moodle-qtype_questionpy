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
use stored_file;

/**
 * Gather the limits on file uploads that can and should be validated.
 *
 * These are as understood by, for example, {@see \form_filemanager}, and are specific to a user and context.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2025 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class validatable_upload_limits {
    /**
     * Trivial constructor.
     *
     * @param int $maxfiles
     * @param int $maxbytes
     * @param int $areamaxbytes
     */
    public function __construct(
        /** @var int */
        public readonly int $maxfiles,
        /** @var int */
        public readonly int $maxbytes,
        /** @var int */
        public readonly int $areamaxbytes,
    ) {
    }

    /**
     * Validates these limits for the given files.
     *
     * @param array<string, stored_file> $files
     * @param string $where String for the error message, describing what sort of files these are.
     * @throws coding_exception
     */
    public function validate_files(array $files, string $where): void {
        if ($this->maxfiles !== -1 && count($files) > $this->maxfiles) {
            throw new coding_exception("Uploads of $where exceed limit of $this->maxfiles files.");
        }

        $totalbytes = 0;
        foreach ($files as $filename => $file) {
            if ($this->maxbytes !== USER_CAN_IGNORE_FILE_SIZE_LIMITS && $file->get_filesize() > $this->maxbytes) {
                throw new coding_exception("File '$filename' of $where exceeds limit of $this->maxbytes bytes.");
            }

            $totalbytes += $file->get_filesize();
        }

        if ($this->areamaxbytes !== FILE_AREA_MAX_BYTES_UNLIMITED && $totalbytes > $this->areamaxbytes) {
            throw new coding_exception("Uploads of $where exceed limit of $this->areamaxbytes total bytes.");
        }
    }
}
