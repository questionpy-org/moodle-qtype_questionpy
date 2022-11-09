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
 * Upload view for the QuestionPy packages.
 *
 * @package    qtype_questionpy
 * @copyright  2022 Alexander Schmitz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use qtype_questionpy\package;
use qtype_questionpy\api;

require_once(dirname(__FILE__) . '/../../../config.php');

$courseid = optional_param('courseid', 2, PARAM_INT);

require_login($courseid);
$context = context_course::instance($courseid);

require_capability('qtype/questionpy:uploadpackages', $context);
$PAGE->set_context($context);
$thisurl = new moodle_url('/question/type/questionpy/upload_view.php', ['courseid' => $courseid]);
$PAGE->set_url($thisurl);
$PAGE->set_pagelayout('popup');
$PAGE->set_title(get_string('pluginname', 'qtype_questionpy'));
$output = $PAGE->get_renderer('core');
echo $output->header(get_string('pluginname', 'qtype_questionpy'));

$customdata = [
    'courseid' => $courseid,
    'contextid' => $context->id
];
$mform = new \qtype_questionpy\form\package_upload(null, $customdata);
$fs = get_file_storage();

if ($mform->is_cancelled()) {
    // This redirect shows a warning, but should be ok (see: https://tracker.moodle.org/browse/CONTRIB-5857).
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]), 'Upload form cancelled.');
} else if ($fromform = $mform->get_data()) {
    // File has been submitted in the form. Check if a file with this name already exists in this context.
    $filename = $mform->get_new_filename('qpy_package');
    if ($fs->file_exists($context->id, 'qtype_questionpy', 'package', 0, '/', $filename)) {
         redirect($thisurl, "File with this name already exists in this context.", 500, \core\output\notification::NOTIFY_WARNING);
    }

    // Store file locally with the File API.
    $storedfile = $mform->save_stored_file('qpy_package', $context->id, 'qtype_questionpy',
        'package', 0, '/', $filename);

    // Try to post the file to the server.
    $filesystem = $fs->get_file_system();
    $path = $filesystem->get_local_path_from_storedfile($storedfile, true);
    $response = api::post_package($filename, $path);

    if ($response->code != http_response_code(201)) {
        // If the file could not be saved on the server, also delete it in the file API.
        $storedfile->delete();
        redirect($thisurl, "HTTP Response code: " . $response->code, 500, \core\output\notification::NOTIFY_ERROR);
    }

    // Store package_info in the DB.
    $package = package::from_array($response->get_data());
    $package->store_in_db($context->id);

    redirect($thisurl, "Package saved.", 500, \core\output\notification::NOTIFY_SUCCESS);
} else {
    $mform->display();
}

echo $output->footer();
