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

use qtype_questionpy\package\package;
use qtype_questionpy\package\package_version;

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

    // Get package.
    $packagehash = explode('.', $filename)[0] ?? '';
    $pkgversion = package_version::get_by_hash($packagehash);

    // Only allow package uploader to download file.
    // TODO: decide if every one who can access the package should upload the file.
    if (is_null($pkgversion) || !$pkgversion->ismine) {
        return false;
    }

    // Get the file.
    $filestorage = get_file_storage();
    $systemcontextid = context_system::instance()->id;
    $file = $filestorage->get_file($systemcontextid, 'qtype_questionpy', $filearea, $itemid, $filepath, $filename);

    // Check if package was found.
    if (!$file) {
        return false;
    }

    // Create human-readable filename.
    // TODO: optimize database access?
    $package = package::get_by_version($pkgversion->id);
    $filename = implode('_', [$package->namespace, $package->shortname, $pkgversion->version]);
    $filename = clean_param("$filename.qpy", PARAM_FILE);
    $options = array_merge(['filename' => $filename], $options);

    // Send the file.
    send_stored_file($file, DAYSECS, 0, $forcedownload, $options);
    return true;
}
