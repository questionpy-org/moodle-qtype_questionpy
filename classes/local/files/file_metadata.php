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

use DateTimeImmutable;
use qtype_questionpy\local\array_converter\attributes\array_key;

/**
 * Metadata of a file in the form data sent to and expected from the QPy server.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2025 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_metadata {
    /**
     * Trivial constructor.
     *
     * @param string $path Starting and ending in `/`.
     * @param string $filename
     * @param string $fileref
     * @param DateTimeImmutable $uploadedat
     * @param string $mimetype
     * @param int $size In bytes.
     */
    public function __construct(
        /** @var string $path Starting and ending in `/`. */
        public string $path,
        /** @var string $filename */
        public string $filename,
        /** @var string $fileref */
        #[array_key('file_ref')]
        public string $fileref,
        /** @var DateTimeImmutable $uploadedat */
        #[array_key('uploaded_at')]
        public DateTimeImmutable $uploadedat,
        /** @var string $mimetype */
        #[array_key('mime_type')]
        public string $mimetype,
        /** @var int $size In bytes. */
        public int $size,
    ) {
    }
}
