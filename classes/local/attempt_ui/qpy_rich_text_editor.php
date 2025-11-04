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

namespace qtype_questionpy\local\attempt_ui;

use core\context;
use core\di;
use core\exception\coding_exception;
use DOMElement;
use DOMNode;
use file_exception;
use moodle_exception;
use MoodleQuickForm_editor;
use qtype_questionpy\local\files\response_file_service;
use qtype_questionpy\local\files\validatable_upload_limits;
use qtype_questionpy\utils;
use question_attempt;
use stored_file_creation_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/form/editor.php');

/**
 * Represents a `<qpy:rich-text-editor/>` element in the question UI XML.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2025 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qpy_rich_text_editor implements custom_xhtml_element {
    // The default in MoodleQuickForm_editor.
    /** @var int */
    private const RETURN_TYPES = FILE_INTERNAL | FILE_EXTERNAL | FILE_REFERENCE | FILE_CONTROLLED_LINK;

    /**
     * Trivial private constructor. Use {@see from_element()}.
     * @param DOMElement $element
     * @param string $name
     */
    private function __construct(
        /** @var DOMElement */
        private readonly DOMElement $element,
        /** @var string */
        public readonly string $name,
        /** @var bool */
        public readonly bool $required,
    ) {
    }

    /**
     * Gets the limits that should be validated for the current user in the given context when using this upload field.
     *
     * @param context $context
     * @return validatable_upload_limits
     */
    public function get_limits_in(context $context): validatable_upload_limits {
        global $CFG, $PAGE;
        require_once($CFG->libdir . '/formslib.php'); // For EDITOR_UNLIMITED_FILES.

        if ($this->element->hasAttribute('max-files')) {
            debugging('qpy:rich-text-editor does not support max-files, the attribute is ignored.');
        }

        $maxbytes = $this->element->getAttribute('max-bytes-per-file');
        $maxbytes = is_numeric($maxbytes) ? intval($maxbytes) : FILE_AREA_MAX_BYTES_UNLIMITED;
        $coursemaxbytes = 0;
        if (!empty($PAGE->course->maxbytes)) {
            $coursemaxbytes = $PAGE->course->maxbytes;
        }
        $maxbytes = get_user_max_upload_file_size($context, $CFG->maxbytes, $coursemaxbytes, $maxbytes);

        $areamaxbytes = $this->element->getAttribute('max-bytes-total');
        $areamaxbytes = is_numeric($areamaxbytes) ? intval($areamaxbytes) : FILE_AREA_MAX_BYTES_UNLIMITED;

        // Moodle's tinymce media plugin hardcodes maxfiles to -1, so we can't place any restrictions here.
        return new validatable_upload_limits(maxfiles: EDITOR_UNLIMITED_FILES, maxbytes: $maxbytes, areamaxbytes: $areamaxbytes);
    }

    /**
     * Parses the given DOMElement if possible.
     *
     * @param DOMElement $element
     * @return static|null
     */
    public static function from_element(DOMElement $element): ?static {
        $name = $element->getAttribute('name');
        if (!$name) {
            debugging('qpy:file-upload without a name');
            return null;
        }

        $required = $element->hasAttribute('required');

        return new static($element, $name, $required);
    }

    /**
     * Renders this element to a DOMNode.
     *
     * @param question_attempt $qa
     * @param question_ui_renderer $renderer
     * @return DOMNode
     * @throws coding_exception
     * @throws file_exception
     * @throws moodle_exception
     * @throws stored_file_creation_exception
     */
    public function render(question_attempt $qa, question_ui_renderer $renderer): DOMNode {
        $limits = $this->get_limits_in($renderer->options->context);

        $alleditorsdata = utils::get_qpy_editors_data($qa);
        $mydata = $alleditorsdata[$this->name] ?? null;

        $options = [
             'context' => $renderer->options->context,
        ];
        $values = [
             'text' => $mydata !== null ? $mydata->text : '',
             'format' => $mydata !== null ? $mydata->format : FORMAT_HTML,
        ];
        if ($limits->maxfiles === 0) {
            $options['enable_filemanagement'] = false;
        } else {
            $combinedfilearea = $renderer->prepare_combined_draft_area($qa);
            $rfs = di::get(response_file_service::class);
            global $USER;
            $splitdraftitemid = $rfs->prepare_split_draft_area($this->name, $USER->id, $combinedfilearea);

            // This is used to tell the qbehaviour what draft areas to save.
            $renderer->draftareas[$this->name] = $splitdraftitemid;

            $options['subdirs'] = false;
            $options['maxfiles'] = $limits->maxfiles;
            $options['maxbytes'] = $limits->maxbytes;
            $options['areamaxbytes'] = $limits->areamaxbytes;

            $values['itemid'] = $splitdraftitemid;
        }

        // This will be used by the JS code to separately handle the editor data.
        $renderer->editornames[] = $this->name;

        $meditor = new MoodleQuickForm_editor(
            elementName: $this->name,
            elementLabel: null,
            options: $options
        );
        $meditor->_generateId();

        $meditor->setValue($values);

        return dom_utils::html_to_fragment($this->element->ownerDocument, $meditor->toHtml());
    }
}
