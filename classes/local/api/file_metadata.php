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

namespace qtype_questionpy\local\api;

use core\exception\coding_exception;
use DateTimeImmutable;
use JsonSerializable;
use qtype_questionpy\local\array_converter\array_converter;
use qtype_questionpy\local\array_converter\attributes\array_key;
use qtype_questionpy\local\array_converter\conversion_exception;
use qtype_questionpy\local\files\qpy_file_ref;
use stored_file;

/**
 * Metadata of a file in the form data sent to and expected from the QPy server.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2025 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_metadata implements JsonSerializable {
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

    /**
     * Build from the given {@see stored_file}.
     *
     * @param stored_file $file
     * @param string|null $overridename Use a different filename than the one of the stored file.
     * @return file_metadata
     * @throws coding_exception
     */
    public static function from_stored_file(stored_file $file, ?string $overridename = null): static {
        $fileref = qpy_file_ref::from_stored_file($file);
        return new static(
            path: $file->get_filepath(),
            filename: $overridename ?? $file->get_filename(),
            fileref: $fileref,
            uploadedat: DateTimeImmutable::createFromFormat('U', $file->get_timemodified()),
            mimetype: $file->get_mimetype(),
            size: $file->get_filesize(),
        );
    }
    /**
     * Specify data which should be serialized to JSON
     * @link https://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @throws coding_exception
     * @throws conversion_exception
     */
    public function jsonSerialize(): mixed {
        return array_converter::to_array($this);
    }
}
