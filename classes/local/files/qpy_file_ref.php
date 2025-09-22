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


use core\exception\coding_exception;
use stored_file;
use Stringable;
use function pack;

/**
 * Represents a file reference, which is a combination of the content and metadata hashes.
 *
 * File refs should be viewed as opaque, but have the following format:
 * The existing {@see stored_file::get_contenthash()} method is used to generate the content hash. That method uses SHA-1, which
 * generates 20-byte hashes, so 40 hexadecimal characters. We generate the metadata hash ourselves using the fast
 * non-cryptographic hash function XXH3. It generates 8-byte hashes, so 16 hexadecimal characters. In string format, both hashes
 * are separated by a dash `-`, leading to 57-characters.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2025 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qpy_file_ref implements Stringable {
    /** @var string */
    public readonly string $contenthash;
    /** @var string */
    public readonly string $metadatahash;

    /**
     * Ensures that the given hash is in hexadecimal format.
     *
     * @param string $hash The hash to validate or convert, `$bytes` or `$bytes * 2` (if hexadecimal) characters long.
     * @param int $bytes number of bytes in the expected hash
     * @return string The hash in hexadecimal format.
     * @throws coding_exception If the provided hash is not in the expected format.
     */
    private static function ensure_hex_hash(string $hash, int $bytes): string {
        if (strlen($hash) === $bytes) {
            // In binary format, convert.
            return bin2hex($hash);
        } else if (strlen($hash) === $bytes * 2) {
            // Already in hex format.
            return strtolower($hash);
        } else {
            // Garbage.
            throw new coding_exception("Unexpected hash format: '$hash'");
        }
    }

    /**
     * Initializes a new instance of the class with the provided content and metadata hashes.
     *
     * The given hashes may be 20-byte binary strings or 40-byte hexadecimal strings.
     *
     * @param string $contenthash
     * @param string $metadatahash
     * @throws coding_exception
     */
    private function __construct(
        string $contenthash,
        string $metadatahash
    ) {
        $this->contenthash = self::ensure_hex_hash($contenthash, 20);
        $this->metadatahash = self::ensure_hex_hash($metadatahash, 8);
    }

    /**
     * Generates the file ref for a given stored_file object.
     *
     * @param stored_file $file
     * @return self
     * @throws coding_exception
     */
    public static function from_stored_file(stored_file $file): self {
        // Generate a hash by packing the relevant pieces of metadata separated by null bytes and hashing that.
        // XXH3 is a fast non-cryptographic hash function, which is fine here since it doesn't have security implications.
        $metadatahash = hash('xxh3', pack(
            'LxLxa*xa*xa*',
            $file->get_contextid(),
            $file->get_userid(),
            $file->get_author(),
            $file->get_license(),
            $file->get_source(),
        ));

        return new self($file->get_contenthash(), $metadatahash);
    }

    /**
     * Converts the file ref to its string representation.
     *
     * @return string
     */
    public function __toString(): string {
        return "{$this->contenthash}-{$this->metadatahash}";
    }
}
