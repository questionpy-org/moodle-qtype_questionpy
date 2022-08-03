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
 * Defines the upload form for the QuestionPy packages.
 *
 * @package    qtype_questionpy
 * @copyright  2022 Alexander Schmitz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_questionpy\form;

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once($CFG->libdir. "/formslib.php");

/**
 * QuestionPy package upload form definition.
 *
 * @copyright  2022 Alexander Schmitz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class package_upload extends \moodleform {


    /**
     * Build the form definition.
     */
    protected function definition() {
        global $CFG;
        $mform = $this->_form;

        $maxbytes = get_config('qtype_questionpy', 'applicationserver_maxquestionsize');
        $mform->addElement('filepicker', 'qpy_package', get_string('file'), null,
            array('maxbytes' => $maxbytes, 'accepted_types' => array('.qpy')));

        $this->add_action_buttons();

    }

    /**
     * Load in existing data as form defaults. Usually new entry defaults are stored directly in
     * form definition (new entry form); this function is used to load in data where values
     * already exist and data is being edited (edit entry form).
     *
     * note: $slashed param removed
     *
     * @param array $data
     * @param array $files
     * @return array $errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!self::file_uploaded($data['qpy_package'])) {
            $errors["qpy_package"] = get_string('formerror_noqpy_package', 'qtype_questionpy');
        }
        return $errors;
    }

    /**
     * Checks to see if a file has been uploaded.
     *
     * @param string $draftitemid The draft id
     * @return bool True if files exist, false if not.
     */
    public static function file_uploaded($draftitemid) {
        $draftareafiles = file_get_drafarea_files($draftitemid);
        do {
            $draftareafile = array_shift($draftareafiles->list);
        } while ($draftareafile !== null && $draftareafile->filename == '.');
        if ($draftareafile === null) {
            return false;
        }
        return true;
    }
}