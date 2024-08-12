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
use qtype_questionpy\static_file_service;

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
 * @package  qtype_questionpy
 * @category files
 */
function qtype_questionpy_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []): never {
    global $CFG;
    require_once($CFG->libdir . '/questionlib.php');

    if ($filearea !== "static") {
        // TODO: Support static-private files.
        send_file_not_found();
    }

    $staticfileservice = di::get(static_file_service::class);

    [$packagehash, $namespace, $shortname] = $args;
    $path = implode("/", array_slice($args, 3));

    [$filepath, $mimetype] = $staticfileservice->download_public_static_file($packagehash, $namespace, $shortname, $path);
    if (is_null($filepath)) {
        send_file_not_found();
    }

    /* Set a lifetime of 1 year, i.e. effectively never expire. Since the package hash is part of the URL, cache busting
       is automatic. */
    send_file(
        $filepath,
        basename($path),
        lifetime: 31536000,
        mimetype: $mimetype,
        options: ["immutable" => true, "cacheability" => "public"]
    );
}
