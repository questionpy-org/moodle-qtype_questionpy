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
 * Model defining an attempt's UI source and associated data, such as parameters for placeholders.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2023 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attempt_ui {

    /** @var string */
    public string $content;

    /** @var array string to string mapping of placeholder names to the values (to be replaced in the content) */
    public array $placeholders = [];

    /** @var string|null specifics TBD */
    public ?string $includeinlinecss = null;

    /** @var string|null specifics TBD */
    public ?string $includecssfile = null;

    /** @var string specifics TBD */
    public string $cachecontrol = "private";

    /** @var object[] specifics TBD */
    public array $files = [];

    /**
     * Initializes a new instance.
     *
     * @param string $content
     */
    public function __construct(string $content) {
        $this->content = $content;
    }
}

array_converter::configure(attempt_ui::class, function (converter_config $config) {
    $config
        ->rename("includeinlinecss", "include_inline_css")
        ->rename("includecssfile", "include_css_file")
        ->rename("cachecontrol", "cache_control");
});
