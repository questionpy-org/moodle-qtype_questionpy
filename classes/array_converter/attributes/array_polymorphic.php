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

namespace qtype_questionpy\array_converter\attributes;

use Attribute;

/**
 * Enables polymorphic deserialization for this class, using the given key as a discriminator.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2024 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[Attribute(Attribute::TARGET_CLASS)]
class array_polymorphic {
    /**
     * Initializes a new attribute instance.
     *
     * @param string $discriminator The array key whose value decides the concrete class used for deserialization.
     * @param array $variants An array of discriminator values to concrete class-strings.
     * @param string|null $fallbackvariant The fallback variant for polymorphic deserialization.
     */
    public function __construct(
        /** @var string $discriminator The array key whose value decides the concrete class used for deserialization. */
        public readonly string $discriminator,
        /** @var array<mixed, class-string> $variants An array of discriminator values to concrete class-strings. */
        public readonly array $variants,
        /**
         * @var class-string|null $fallbackvariant The fallback variant for polymorphic deserialization.
         *
         * When a discriminator is encountered which isn't registered, the default behaviour is to throw an exception.
         * Instead, you can register a fallback class to be used. A debugging message will still be emitted.
         */
        public readonly ?string $fallbackvariant = null
    ) {
    }
}
