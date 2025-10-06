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

namespace qtype_questionpy\local\form\elements;

use core\context;
use core\di;
use core\exception\coding_exception;
use moodle_exception;
use MoodleQuickForm_filemanager;
use qtype_questionpy\local\array_converter\array_converter;
use qtype_questionpy\local\array_converter\attributes\array_key;
use qtype_questionpy\local\files\file_metadata;
use qtype_questionpy\local\files\options_file_service;
use qtype_questionpy\local\form\context\render_context;
use qtype_questionpy\local\form\form_help;
use qtype_questionpy\utils;
use stdClass;

/**
 * File upload.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2025 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_upload_element extends form_element {
    use form_help;

    use file_upload_options_trait;

    /** @var false Whether to allow subdirs. */
    private const SUBDIRS = false;

    /**
     * Initializes the element.
     *
     * @param string $name
     * @param string $label
     */
    public function __construct(
        /** @var string */
        public string $name,
        /** @var string */
        public string $label,
    ) {
    }

    /**
     * Render this item to the given context.
     *
     * @param render_context $context target context
     * @throws moodle_exception
     */
    public function render_to(render_context $context): void {
        global $PAGE, $CFG;

        $coursemaxbytes = 0;
        if (!empty($PAGE->course->maxbytes)) {
            $coursemaxbytes = $PAGE->course->maxbytes;
        }

        /** @var MoodleQuickForm_filemanager $element */
        $element = $context->add_element('filemanager', $this->name, $context->contextualize($this->label), null, [
            // MoodleQuickForm_filemanager doesn't offer a minfiles option, so we leave that to the QPy-side validation.
            'subdirs' => self::SUBDIRS,
            'maxfiles' => $this->maxfiles ?? EDITOR_UNLIMITED_FILES,
            // Moodle applied get_user_max_upload_file_size again, but is inconsistent about it, and more often doesn't hurt.
            'maxbytes' => get_user_max_upload_file_size(
                context::instance_by_id($context->question->contextid),
                $CFG->maxbytes,
                $coursemaxbytes,
                $this->fileuploads->maxbytesperfile ?? FILE_AREA_MAX_BYTES_UNLIMITED
            ),
            'areamaxbytes' => $this->maxbytestotal ?? FILE_AREA_MAX_BYTES_UNLIMITED,
        ]);

        $context->set_type($this->name, PARAM_RAW);

        [$draftitemid, $newdraftarea] = $context->get_draft_area_for_upload($element->getName());

        $context->set_default($this->name, $draftitemid);

        $context->on_export(function (array &$alldata) use ($element, $context, $draftitemid) {
            $exporteddraftid = utils::array_get_nested($alldata, $element->getName());
            if ($draftitemid != $exporteddraftid) {
                debugging("Submitted ($draftitemid) and exported ($exporteddraftid) draft item id differ. Using $draftitemid.");
            }

            global $USER;
            $ofs = di::get(options_file_service::class);
            /** @var file_metadata[] $filemetas */
            $filemetas = $ofs->get_qpy_files_metadata_from_draftitem($USER->id, $draftitemid);
            $ofs->check_upload_restrictions(
                $this,
                $context->question->contextid,
                $context->question->id ?? null,
                $draftitemid,
                $filemetas
            );

            // At this time, we don't know whether the question will be saved or the draft validated etc., and we don't know the
            // question id, so we don't save the draft files ourselves. But we do need to let question_service know which draft
            // items are used so it can save their contents.
            $alldata['qpy_options_draftitems'][] = $draftitemid;

            utils::array_set_nested(
                $alldata,
                $element->getName(),
                array_converter::to_array($filemetas)
            );
        });

        $context->on_import(function (array &$alldata) use ($element, $context, $draftitemid, $newdraftarea) {
            $myrawdata = utils::array_get_nested($alldata, $element->getName());
            if (!$myrawdata) {
                return;
            }

            $files = array_map(fn($value) => array_converter::from_array(file_metadata::class, $value), $myrawdata);

            global $USER;
            if ($files && $newdraftarea) {
                $questionid = $context->question->id ?? null;
                if ($questionid === null) {
                    throw new coding_exception("We're loading a question, but its ID is unset.");
                }

                $ofs = di::get(options_file_service::class);
                $ofs->prepare_draft_area($context->question->contextid, $questionid, $files, $USER->id, $draftitemid);
            }

            utils::array_set_nested($alldata, $element->getName(), $draftitemid);
        });

        $this->render_help($element);
    }
}
