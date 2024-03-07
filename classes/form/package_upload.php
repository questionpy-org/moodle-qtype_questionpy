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
 * Defines the upload form for the QuestionPy packages.
 *
 * @package    qtype_questionpy
 * @copyright  2022 Alexander Schmitz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_questionpy\form;

defined('MOODLE_INTERNAL') || die;

use context;
use core_form\dynamic_form;
use moodle_exception;
use moodle_url;
use qtype_questionpy\api\api;

require_once($CFG->libdir . "/formslib.php");

/**
 * Dynamic QuestionPy package upload form.
 *
 * @copyright  2022 Alexander Schmitz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class package_upload extends dynamic_form {

    /**
     * Builds the form definition.
     *
     * @throws moodle_exception
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'contextid');
        $mform->setType('contextid', PARAM_INT);

        $maxkb = get_config('qtype_questionpy', 'max_package_size_kb');
        $mform->addElement('filepicker', 'qpy_package', get_string('file'), null,
            ['maxbytes' => $maxkb * 1024, 'accepted_types' => ['.qpy']]);
        $mform->addRule('qpy_package', null, 'required');
    }

    /**
     * Returns context where this form is used.
     *
     * This context is validated in {@see \external_api::validate_context()}
     *
     * @throws moodle_exception
     */
    protected function get_context_for_dynamic_submission(): context {
        $contextid = $this->optional_param('contextid', null, PARAM_INT);
        return context::instance_by_id($contextid);
    }

    /**
     * Checks if current user has access to this form, otherwise throws exception.
     *
     * @throws moodle_exception
     */
    protected function check_access_for_dynamic_submission(): void {
        $context = $this->get_context_for_dynamic_submission();
        require_capability('qtype/questionpy:uploadpackages', $context);
    }

    /**
     * Process the form submission.
     *
     * @throws moodle_exception
     */
    public function process_dynamic_submission(): void {

        // Get context and user context.
        $contextid = $this->optional_param('contextid', null, PARAM_INT);
        $systemcontextid = \context_system::instance()->id;

        // Get file storage.
        $filestorage = get_file_storage();

        // Get filename.
        $filename = $filestorage->get_unused_filename($systemcontextid, 'qtype_questionpy', 'package', 0, '/',
            'tmp_package_name.qpy');

        // Save file inside current file area.
        $file = $this->save_stored_file('qpy_package', $systemcontextid, 'qtype_questionpy', 'package', 0, '/', $filename);
        if (!$file) {
            throw new moodle_exception('cannotuploadfile');
        }

        try {
            // Store the package in the database.
            $path = $filestorage->get_file_system()->get_local_path_from_storedfile($file);
            $rawpackage = api::extract_package_info($path);
            $rawpackage->store_as_user($contextid);

            // Check if package was already uploaded.
            $newfilename = $rawpackage->hash . '.qpy';
            if ($filestorage->file_exists($systemcontextid, 'qtype_questionpy', 'package', 0, '/', $newfilename)) {
                $file->delete();
            } else {
                $file->rename('/', $newfilename);
            }
        } catch (moodle_exception $exception) {
            $file->delete();
            throw $exception;
        }
    }

    /**
     * Load in existing data as form defaults.
     *
     * @throws moodle_exception
     */
    public function set_data_for_dynamic_submission(): void {
        $contextid = $this->optional_param('contextid', null, PARAM_INT);

        // Set the context id to the course context id if the context is part of a course.
        $context = context::instance_by_id($contextid);
        if ($coursecontext = $context->get_course_context(false)) {
            $contextid = $coursecontext->id;
        }

        $this->set_data([
            'contextid' => $contextid,
        ]);
    }

    /**
     * Returns url to set in {@see $PAGE->set_url}.
     *
     * @throws moodle_exception
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return $this->get_context_for_dynamic_submission()->get_url();
    }
}
