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

namespace qtype_questionpy\package;

use qtype_questionpy\array_converter\attributes\array_alias;
use qtype_questionpy\array_converter\attributes\array_key;

/**
 * Represents a QuestionPy package from a server.
 *
 * It contains metadata about a package version and its package.
 *
 * @package    qtype_questionpy
 * @copyright  2023 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class package_raw extends package_base {
    /**
     * @var string package hash
     */
    #[array_key("package_hash")]
    #[array_alias("hash")]
    public string $hash;

    /**
     * @var string package version
     */
    public string $version;
}
