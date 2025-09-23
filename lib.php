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

/**
 * Serve question type files
 *
 * @package    qtype_questionpy
 * @copyright  2022 Martin Gauk, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\di;
use GuzzleHttp\Exception\GuzzleException;
use qtype_questionpy\local\files\qpy_url_resolver;

/**
 * Checks file access for QuestionPy questions.
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @throws moodle_exception
 * @throws GuzzleException
 * @package  qtype_questionpy
 * @category files
 */
function qtype_questionpy_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []): never {
    global $CFG;
    require_once($CFG->libdir . '/questionlib.php');

    di::get(qpy_url_resolver::class)->serve_pluginfile($context, $filearea, $args);
    die();
}
