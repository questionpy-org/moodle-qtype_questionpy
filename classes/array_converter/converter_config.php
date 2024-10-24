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

namespace qtype_questionpy\array_converter;

/**
 * Holds customization of {@see array_converter}.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class converter_config {
    /** @var string[] mapping from property names to array keys */
    public array $renames = [];
    /** @var string[] mapping from property names to arrays of their aliases */
    public array $aliases = [];

    /** @var ?string discriminator array key, if any */
    public ?string $discriminator = null;
    /** @var string[] mapping from discriminator values to concrete classes */
    public array $variants = [];
    /** @var string|null if an unknown discriminator is given, warn and use this class */
    public ?string $fallbackvariant;

    /** @var string[] mapping from property names to the classes of their array elements */
    public array $elementclasses = [];
}
