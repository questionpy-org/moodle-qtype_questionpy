<?php
// This file is part of Moodle - http://moodle.org/
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

require_once(dirname(__FILE__) . '/../../../config.php');

$courseid = required_param('courseid', PARAM_INT);

require_login($courseid);
$context = context_course::instance($courseid);

require_capability('qtype/questionpy:uploadpackages', $context);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/question/type/questionpy/upload_view.php', ['courseid' => $courseid]));
$PAGE->set_pagelayout('popup');
$PAGE->set_title(get_string('pluginname', 'qtype_questionpy'));
$output = $PAGE->get_renderer('core');
echo $output->header(get_string('pluginname', 'qtype_questionpy'));

$mform = new \qtype_questionpy\form\package_upload();
$fs = get_file_storage();

if ($mform->is_cancelled()) {
    // This redirect shows a warning, but should be ok (see: https://tracker.moodle.org/browse/CONTRIB-5857).
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]), 'Upload form cancelled.');
} else if ($fromform = $mform->get_data()) {
    // If there is a file and it doesn't exist already, save it.
    // TODO: post request to server with the package file.
    $name = $mform->get_new_filename('qpy_package');
    $courseid = $fromform->courseid;
    if (!$fs->file_exists($context->id, 'qtype_questionpy', 'package', 0, '/', $name)) {
        $storedfile = $mform->save_stored_file('qpy_package', $context->id, 'qtype_questionpy',
            'package', 0, '/', $name);

        // Placeholder.
        $packagedata = [
            "name" => $name,
            "short_name" => "shortname",
            "contextid" => $context->id,
            "package_hash" => "abcde",
            "type" => "testtype",
            "description" => "This describes the package ExamplePackage.",
            "author" => "Author",
            "license" => "MIT",
            "icon" => "https://placeimg.com/48/48/tech/grayscale",
            "version" => "0.0.1"
        ];
        $recordid = $DB->insert_record('qtype_questionpy_package', $packagedata, $returnid = true, $bulk = false);
    }
    redirect(new moodle_url('/question/type/questionpy/upload_view.php', ['courseid' => $courseid]));
} else {
    $packages = $DB->get_records('qtype_questionpy_package', ['contextid' => $context->id]);
    foreach ($packages as $package) {
        echo $output->render_from_template('qtype_questionpy/package_renderable', $package);
    }
    $mform->set_data(['courseid' => $courseid]);
    $files = $fs->get_area_files($context->id, 'qtype_questionpy', 'package');
    $mform->display();
}

echo $output->footer();
