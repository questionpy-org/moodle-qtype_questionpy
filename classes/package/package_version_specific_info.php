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

defined('MOODLE_INTERNAL') || die;

use qtype_questionpy\array_converter\array_converter;
use qtype_questionpy\array_converter\converter_config;

/**
 * Represents a package version of an available QuestionPy package on the application server.
 *
 * @package    qtype_questionpy
 * @copyright  2024 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class package_version_specific_info {
    /**
     * @var string $hash
     */
    public readonly string $hash;

    /**
     * @var string $version
     */
    public readonly string $version;
}

array_converter::configure(package_version_specific_info::class, function (converter_config $config) {
    $config
        ->rename("hash", "package_hash")
        // The DB rows are also read using array_converter, but their columns are named differently to the json fields.
        ->alias("hash", "hash");
});
