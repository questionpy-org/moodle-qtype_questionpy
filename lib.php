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

/**
 * Serve files from the QuestionPy file areas.
 *
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether to force download
 * @param array $options additional options affecting the file serving
 * @return bool
 * @throws moodle_exception
 */
function qtype_questionpy_pluginfile($course, $cm, $context, string $filearea, array $args,
                                     bool $forcedownload, array $options=[]): bool {
    global $USER;

    // We currently only store files inside the package file area.
    if ($filearea !== 'package') {
        return false;
    }

    require_login($course, true, $cm);

    // Extract the item id.
    $itemid = array_shift($args);

    // Extract the filename and filepath.
    $filename = array_pop($args);
    $filepath = '/';
    if (!empty($args)) {
        $filepath .= implode('/', $args) . '/';
    }

    // Get the file.
    $filestorage = get_file_storage();
    $file = $filestorage->get_file($context->id, 'qtype_questionpy', $filearea, $itemid, $filepath, $filename);

    // Check if package was found and uploaded by the current user.
    if (!$file || $file->get_userid() !== $USER->id) {
        return false;
    }

    // Package was found and is accessible by the current user - send it.
    send_stored_file($file, DAYSECS, 0, $forcedownload, $options);
    return true;
}
