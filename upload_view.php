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
use qtype_questionpy\localizer;

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
        $package = package::from_array([
            'package_hash' => 'dkZZGAOgHTpBOSZMBGNM',
            'short_name' => 'adAqMNxOZNhuSUWflNui',
            'name' => [
                'en' => $name,
                'de' => 'de' . $name
            ],
            'version' => '865.7797993.0--.0',
            'type' => 'questiontype',
            'author' => 'Mario Hunt',
            'url' => 'http://www.kane.com/',
            'languages' => [
                0 => 'en',
                1 => 'de'
            ],
            'description' => [
                'en' => 'en: Activity organization letter. Report alone why center.
                    Real outside glass maintain right hear.
                    Brother develop process work. Build ago north.
                    Develop with defense understand garden recently work.',
                'de' => 'de: Activity few enter medical side position. Safe need no guy price.
                    Source necessary our me series month seven born.
                    Anyone everything interest where accept apply. Expert great significant.'
            ],
            'icon' => 'https://placehold.jp/40e47e/598311/150x150.png',
            'license' => '',
            'tags' => [
                0 => 'fXuprCRqsLnQQYzFZgAt'
            ]
        ]);
        $package->store_in_db($courseid, $context->id);
    }
    redirect(new moodle_url('/question/type/questionpy/upload_view.php', ['courseid' => $courseid]));
} else {
    $languages = localizer::get_preferred_languages();
    $packages = package::get_records(['contextid' => $context->id]);
    foreach ($packages as $package) {
        $packagearray = $package->as_localized_array($languages);
        echo $output->render_from_template('qtype_questionpy/package', $packagearray);
    }
    $mform->set_data(['courseid' => $courseid]);
    $files = $fs->get_area_files($context->id, 'qtype_questionpy', 'package');
    $mform->display();
}

echo $output->footer();
