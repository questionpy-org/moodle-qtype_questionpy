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
use core\output\core_renderer;
use core\output\html_writer;
use DOMDocument;
use DOMElement;
use DOMNode;
use file_exception;
use form_filemanager;
use moodle_exception;
use qtype_questionpy\constants;
use qtype_questionpy\local\files\response_file_service;
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
     * @param DOMDocument $doc
     * @param string $name
     * @param int $maxfiles
     * @param int $maxbytes
     * @param int $areamaxbytes
     */
    private function __construct(
        /** @var DOMDocument */
        private readonly DOMDocument $doc,
        /** @var string */
        private readonly string $name,
        /** @var int */
        private readonly int $maxfiles,
        /** @var int */
        private readonly int $maxbytes,
        /** @var int */
        private readonly int $areamaxbytes,
    ) {
    }

    /**
     * Creates a new {@see qpy_file_upload} from a given {@see DOMElement}.
     *
     * @param DOMElement $element
     * @param context $context
     * @return self|null
     */
    public static function from_element(DOMElement $element, context $context): ?self {
        global $CFG, $PAGE;

        $name = $element->getAttribute('name');
        if (!$name) {
            debugging('qpy:file-upload without a name');
            return null;
        }

        $maxfiles = $element->getAttribute('max_files');
        $maxfiles = is_numeric($maxfiles) ? intval($maxfiles) : EDITOR_UNLIMITED_FILES;

        $maxbytes = $element->getAttribute('max_bytes_per_file');
        $maxbytes = is_numeric($maxbytes) ? intval($maxbytes) : FILE_AREA_MAX_BYTES_UNLIMITED;
        $coursemaxbytes = 0;
        if (!empty($PAGE->course->maxbytes)) {
            $coursemaxbytes = $PAGE->course->maxbytes;
        }
        $maxbytes = get_user_max_upload_file_size($context, $CFG->maxbytes, $coursemaxbytes, $maxbytes);

        $areamaxbytes = $element->getAttribute('max_bytes_total');
        $areamaxbytes = is_numeric($areamaxbytes) ? intval($areamaxbytes) : FILE_AREA_MAX_BYTES_UNLIMITED;

        return new static(
            $element->ownerDocument,
            $name,
            $maxfiles,
            $maxbytes,
            $areamaxbytes
        );
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
            return $this->render_readonly($qa, $renderer);
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
        $afs = di::get(response_file_service::class);
        $afs->prepare_draft_area($renderer->options->context->id, $qa, $this->name, $USER->id, $draftitemid);

        // TODO: Explain.
        $renderer->draftareas[$this->name] = $draftitemid;

        $fm = new form_filemanager((object)[
            'itemid' => $draftitemid,
            'subdirs' => false,
            'context' => $renderer->options->context,
            'maxfiles' => $this->maxfiles,
            'maxbytes' => $this->maxbytes,
            'areamaxbytes' => $this->areamaxbytes,
        ]);

        // phpcs:disable moodle.PHP.ForbiddenGlobalUse.BadGlobal
        $filesrenderer = $PAGE->get_renderer('core', 'files');
        $html = $filesrenderer->render($fm);
        return dom_utils::html_to_fragment($this->doc, $html);
    }

    /**
     * Renders a read-only list of the uploaded files, with clickable download links.
     *
     * @param question_attempt $qa
     * @param question_ui_renderer $renderer
     * @return DOMNode
     * @throws coding_exception
     */
    private function render_readonly(question_attempt $qa, question_ui_renderer $renderer): DOMNode {
        // Loosely based on qtype_essay_renderer::files_read_only.
        global $OUTPUT;

        $allfiles = $qa->get_last_qt_files(constants::QT_VAR_RESPONSE_FILES, $renderer->options->context->id);

        $result = html_writer::start_tag('ul', [
            'class' => 'list-unstyled m-0',
        ]);
        foreach (response_file_service::filter_combined_files_for_field($allfiles, $this->name) as $filename => $file) {
            $result .= html_writer::tag('li', html_writer::link(
                url: $qa->get_response_file_url($file),
                text: $OUTPUT->pix_icon(
                    file_file_icon($file),
                    get_mimetype_description($file),
                    'moodle',
                    ['class' => 'icon']
                ) . ' ' . s($filename),
            ));
        }
        $result .= html_writer::end_tag('ul');

        return dom_utils::html_to_fragment($this->doc, $result);
    }
}
