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

require_once(dirname(__FILE__).'/../../../config.php');
require_once('classes/form/upload_questionpy_form.php');

global $DB;

$courseid = optional_param('courseid', 0,  PARAM_INT);

if ($courseid) {
    require_login($courseid);
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    $context = context_course::instance($courseid);
    require_capability('qtype/questionpy:uploadpackages', $context);
} else {
    require_login();
    $context = context_system::instance();
    require_capability('qtype/questionpy:uploadpackages', $context);
}

$pagetitle = get_string('pluginname', 'qtype_questionpy');
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/question/type/questionpy/upload_view.php', array('courseid' => $courseid)));
$PAGE->set_pagelayout('popup');
$PAGE->set_title($pagetitle);
$output = $PAGE->get_renderer('core');
echo $output->header($pagetitle);

$mform = new qtype_questionpy_upload_form();
$fs = get_file_storage();

$packages = $DB->get_records('question_package_questionpy', ['courseid' => $courseid]);
foreach ($packages as $package) {
    $data = [
        "name" => $package->name,
        "description" => "This describes the package ExamplePackage stored in db",
        "author" => "Author",
        "license" => "MIT",
        "icon" => "https://placeimg.com/48/48/tech/grayscale",
        "version" => "0.0.1"
    ];
    echo $output->render_from_template('qtype_questionpy/package_renderable', $data);
}

if ($mform->is_cancelled()) {
    die();

} else if ($fromform = $mform->get_data()) {
    // If there is a file save it, if it doesn't exist already.
    // TODO: post request to server with the package file.
    $name = $mform->get_new_filename('qpy_package');
    if (!$fs->file_exists($context->id, 'qtype_questionpy', 'package', 0, '/', $name )) {
        $storedfile = $mform->save_stored_file('qpy_package', $context->id, 'qtype_questionpy', 'package', 0, '/', $name);
    }

    // Placeholder.
    $packagedata = [
        "name" => $name,
        "short_name" => "shortname",
        "courseid" => $courseid,
        "package_hash" => "abcde",
        "type" => "testtype",
        "description" => "This describes the package ExamplePackage.",
        "author" => "Author",
        "license" => "MIT",
        "icon" => "https://placeimg.com/48/48/tech/grayscale",
        "version" => "0.0.1"
    ];
    $recordid = $DB->insert_record('question_package_questionpy', $packagedata, $returnid = true, $bulk = false);

    redirect(new moodle_url('/question/type/questionpy/upload_view.php', array('courseid' => $courseid)));
} else {
    $files = $fs->get_area_files($context->id, 'qtype_questionpy', 'package');
    $mform->display();
}

echo $output->footer();
