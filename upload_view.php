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

require_once('/../../../config.php');
require_once($CFG->libdir . '/questionlib.php');

use \core\notification;
use qbank_previewquestion\form\preview_options_form;
use qbank_previewquestion\question_preview_options;
use qbank_previewquestion\helper;
$id = required_param('id', PARAM_INT);
$question = question_bank::load_question($id);

if ($cmid = optional_param('cmid', 0, PARAM_INT)) {
    $cm = get_coursemodule_from_id(false, $cmid);
    require_login($cm->course, false, $cm);
    $context = context_module::instance($cmid);

} else if ($courseid = optional_param('courseid', 0, PARAM_INT)) {
    require_login($courseid);
    $context = context_course::instance($courseid);

} else {
    require_login();
    $category = $DB->get_record('question_categories', ['id' => $question->category], '*', MUST_EXIST);
    $context = context::instance_by_id($category->contextid);
    $PAGE->set_context($context);
    // Note that in the other cases, require_login will set the correct page context.
}

$PAGE->set_pagelayout('popup');

$PAGE->set_url(new moodle_url('/question/type/questionpy/upload_view.php', array('key' => 'value', 'id' => 1)));
$PAGE->set_context($context);$PAGE->set_title('My modules page title');
$PAGE->set_heading('My modules page heading');
