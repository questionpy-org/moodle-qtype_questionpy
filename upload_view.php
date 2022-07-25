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
if ($mform->is_cancelled()) {
    die();

} else if ($fromform = $mform->get_data()) {
    redirect(new moodle_url('/question/type/questionpy/upload_view.php', array('courseid' => $courseid)));
} else {
    $mform->display();
}

echo $output->footer();
