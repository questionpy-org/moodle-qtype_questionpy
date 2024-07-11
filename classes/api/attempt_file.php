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

use qtype_questionpy\array_converter\array_converter;
use qtype_questionpy\array_converter\converter_config;

defined('MOODLE_INTERNAL') || die;

/**
 * A file used in an attempt at a QuestionPy question.
 *
 * @package    qtype_questionpy
 * @author     Jan Britz
 * @copyright  2024 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attempt_file {
    /** @var string */
    public string $name;

    /** @var string|null */
    public ?string $mimetype = null;

    /** @var string $data */
    public string $data;

    /**
     * Initializes a new instance.
     *
     * @param string $name
     * @param string $data
     * @param string|null $mimetype
     */
    public function __construct(string $name, string $data, ?string $mimetype = null) {
        $this->name = $name;
        $this->data = $data;
        $this->mimetype = $mimetype;
    }
}

array_converter::configure(attempt_file::class, function (converter_config $config) {
    $config->rename("mimetype", "mime_type");
});
