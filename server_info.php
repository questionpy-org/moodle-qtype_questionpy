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
 * Server Information View.
 *
 * @package    qtype_questionpy
 * @author     Alexander Schmitz
 * @copyright  2023 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use qtype_questionpy\api\api;

require_once(dirname(__FILE__) . '/../../../config.php');
global $PAGE;

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url('/question/type/questionpy/server_info.php');
$title = new lang_string('server_info_heading', 'qtype_questionpy');
$PAGE->set_title($title);
$output = $PAGE->get_renderer('core');

echo $output->header();
echo $output->heading($title);

// Server information and status.
try {
    $status = api::get_server_status();
    echo $output->render_from_template('qtype_questionpy/settings/server_info', $status);
} catch (moodle_exception $e) {
    notice($e->getMessage());
}

echo $output->footer();
