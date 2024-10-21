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

namespace qtype_questionpy\api;

use qtype_questionpy\array_converter\attributes\array_key;

/**
 * Response from the server containing server status.
 *
 * @package    qtype_questionpy
 * @author     Alexander Schmitz
 * @copyright  2023 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class status {
    /** @var string */
    public string $name;

    /** @var string */
    public string $version;

    /** @var bool */
    #[array_key("allow_lms_packages")]
    public bool $allowlmspackages = false;

    /** @var string */
    #[array_key("max_package_size")]
    public string $maxpackagesize;

    /** @var usage|null */
    public ?usage $usage = null;


    /**
     * Initialize a new status.
     *
     * @param string $name
     * @param string $version
     * @param int $maxpackagesize
     * @throws \coding_exception
     */
    public function __construct(string $name, string $version, int $maxpackagesize) {
        $this->name = $name;
        $this->version = $version;
        $this->maxpackagesize = display_size($maxpackagesize, 1, 'MB');
    }
}
