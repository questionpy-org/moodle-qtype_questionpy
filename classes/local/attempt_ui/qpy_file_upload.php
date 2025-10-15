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
use DOMDocument;
use DOMElement;
use DOMNode;
use file_exception;
use form_filemanager;
use moodle_exception;
use qtype_questionpy\local\files\response_file_service;
use qtype_questionpy\local\files\validatable_upload_limits;
use qtype_questionpy_renderer;
use question_attempt;
use stored_file_creation_exception;

/**
 * Represents a `<qpy:file-upload/>` element in the question UI XML.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2025 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qpy_file_upload {
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

        $maxfiles = $this->element->getAttribute('max-files');
        $maxfiles = is_numeric($maxfiles) ? intval($maxfiles) : EDITOR_UNLIMITED_FILES;

        $maxbytes = $this->element->getAttribute('max-bytes-per-file');
        $maxbytes = is_numeric($maxbytes) ? intval($maxbytes) : FILE_AREA_MAX_BYTES_UNLIMITED;
        $coursemaxbytes = 0;
        if (!empty($PAGE->course->maxbytes)) {
            $coursemaxbytes = $PAGE->course->maxbytes;
        }
        $maxbytes = get_user_max_upload_file_size($context, $CFG->maxbytes, $coursemaxbytes, $maxbytes);

        $areamaxbytes = $this->element->getAttribute('max-bytes-total');
        $areamaxbytes = is_numeric($areamaxbytes) ? intval($areamaxbytes) : FILE_AREA_MAX_BYTES_UNLIMITED;

        return new validatable_upload_limits($maxfiles, $maxbytes, $areamaxbytes);
    }

    /**
     * Creates a new {@see qpy_file_upload} from a given {@see DOMElement}.
     *
     * @param DOMElement $element
     * @return self|null
     */
    public static function from_element(DOMElement $element): ?self {
        $name = $element->getAttribute('name');
        if (!$name) {
            debugging('qpy:file-upload without a name');
            return null;
        }

        return new static($element, $name);
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
        if ($renderer->options->readonly) {
            global $PAGE;
            /** @var qtype_questionpy_renderer $qpyrenderer */
            $qpyrenderer = $PAGE->get_renderer('qtype_questionpy');
            $html = $qpyrenderer->render_readonly_file_view($qa, $this->name, $renderer->options);
            return dom_utils::html_to_fragment($this->element->ownerDocument, $html);
        } else {
            return $this->render_writable($qa, $renderer);
        }
    }

    /**
     * Renders a Moodle file manager from this `<qpy:file-upload/>`, preparing it with the last submitted files.
     *
     * @param question_attempt $qa
     * @param question_ui_renderer $renderer
     * @return DOMNode
     * @throws coding_exception
     * @throws file_exception
     * @throws moodle_exception
     * @throws stored_file_creation_exception
     */
    private function render_writable(question_attempt $qa, question_ui_renderer $renderer): DOMNode {
        // Re: "global $PAGE cannot be used in renderers" - We're not _that_ kind of a renderer.
        // phpcs:disable moodle.PHP.ForbiddenGlobalUse.BadGlobal
        global $CFG, $PAGE, $USER;
        require_once($CFG->libdir . '/form/filemanager.php');

        $draftitemid = file_get_unused_draft_itemid();
        $rfs = di::get(response_file_service::class);
        $rfs->prepare_draft_area($renderer->options->context->id, $qa, $this->name, $USER->id, $draftitemid);

        // This is used to tell the qbehaviour what draft areas to save.
        $renderer->draftareas[$this->name] = $draftitemid;

        $limits = $this->get_limits_in($renderer->options->context);

        $fm = new form_filemanager((object)[
            'itemid' => $draftitemid,
            'subdirs' => false,
            'context' => $renderer->options->context,
            'maxfiles' => $limits->maxfiles,
            'maxbytes' => $limits->maxbytes,
            'areamaxbytes' => $limits->areamaxbytes,
        ]);

        // phpcs:disable moodle.PHP.ForbiddenGlobalUse.BadGlobal
        $filesrenderer = $PAGE->get_renderer('core', 'files');
        $html = $filesrenderer->render($fm);
        return dom_utils::html_to_fragment($this->element->ownerDocument, $html);
    }
}
