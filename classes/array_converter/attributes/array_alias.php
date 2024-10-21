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
 * Adds an alias for the given property.
 *
 * Aliases differ from renames in that they only apply to deserialization, and are tried in addition to the original
 * property name (or rename, if any).
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2024 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class array_alias {
    /**
     * Initializes a new attribute instance.
     *
     * @param string $alias
     */
    public function __construct(
        /** @var string $alias */
        public readonly string $alias
    ) {
    }
}
