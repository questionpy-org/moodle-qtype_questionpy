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

use coding_exception;
use context_user;
use core\context;
use core\di;
use moodle_exception;
use moodle_url;
use MoodleQuickForm_editor;
use qtype_questionpy\constants;
use qtype_questionpy\local\api\wysiwyg_editor_data;
use qtype_questionpy\local\array_converter\array_converter;
use qtype_questionpy\local\array_converter\attributes\array_key;
use qtype_questionpy\local\files\file_metadata;
use qtype_questionpy\local\files\options_file_service;
use qtype_questionpy\local\form\context\render_context;
use qtype_questionpy\local\form\form_help;
use qtype_questionpy\utils;

/**
 * WYSIWYG editor.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2025 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wysiwyg_editor_element extends form_element {
    use form_help;

    /** @var false Whether to allow subdirs in the media manager. A constant for now. */
    private const SUBDIRS = false;

    /** @var string[] Maps Moodle's format constants to what {@see wysiwyg_editor_data} expects. */
    private const FORMAT_MAP = [
        FORMAT_HTML => 'html',
        FORMAT_MARKDOWN => 'markdown',
        FORMAT_PLAIN => 'plain',
        FORMAT_MOODLE => 'moodle',
    ];

    /**
     * Initializes the element.
     *
     * @param string $name
     * @param string $label
     * @param file_upload_options|null $fileuploads (`null` disables uploads altogether)
     */
    public function __construct(
        /** @var string */
        public string $name,
        /** @var string */
        public string $label,
        /** @var file_upload_options|null (`null` disables uploads altogether) */
        #[array_key('file_uploads')]
        public file_upload_options|null $fileuploads = new file_upload_options(),
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

        $uploadsoptions = match ($this->fileuploads) {
            null => [
                'enable_filemanagement' => false,
            ],
            default => [
                // MoodleQuickForm_editor doesn't offer a minfiles option, so we leave that to the QPy-side validation.
                'subdirs' => self::SUBDIRS,
                'maxfiles' => $this->fileuploads->maxfiles ?? EDITOR_UNLIMITED_FILES,
                'maxbytes' => $this->fileuploads->maxbytesperfile ?? FILE_AREA_MAX_BYTES_UNLIMITED,
                'areamaxbytes' => $this->fileuploads->maxbytestotal ?? FILE_AREA_MAX_BYTES_UNLIMITED,
            ]
        };

        /** @var MoodleQuickForm_editor $element */
        $element = $context->add_element(
            'editor',
            $this->name,
            $context->contextualize($this->label),
            null,
            [
                'context' => context::instance_by_id($context->question->contextid),
                ...$uploadsoptions,
            ]
        );
        $context->set_type($this->name, PARAM_RAW);

        [$draftitemid, $newdraftarea] = $context->get_draft_area_for_upload($element->getName() . '[itemid]');

        $context->set_default($this->name, [
            'format' => FORMAT_HTML,
            'itemid' => $draftitemid,
        ]);

        $context->on_export(function (array &$alldata) use ($element, $context, $draftitemid) {
            $mydata = utils::array_get_nested($alldata, $element->getName());
            if (!$mydata) {
                return;
            }

            $text = $mydata['text'];
            $format = $mydata['format'];

            if (!$this->fileuploads) {
                // Uploads are disabled.
                $filemetas = [];
            } else {
                // Remove all draft files that aren't referenced in the markup.
                file_remove_editor_orphaned_files($mydata);

                global $USER;
                $ofs = di::get(options_file_service::class);
                /** @var file_metadata[] $filemetas */
                $filemetas = $ofs->get_qpy_files_metadata_from_draftitem($USER->id, $draftitemid);
                $ofs->check_upload_restrictions(
                    $this->fileuploads,
                    $context->question->contextid,
                    $context->question->id ?? null,
                    $draftitemid,
                    $filemetas
                );

                $text = self::replace_draftfile_urls_with_qpy_urls($text, $filemetas, $draftitemid);

                // At this time, we don't know whether the question will be saved or the draft validated etc., and we don't know the
                // question id, so we don't save the draft files ourselves. But we do need to let question_service know which draft
                // items are used so it can save their contents.
                $alldata['qpy_options_draftitems'][] = $draftitemid;
            }

            $mappedformat = self::FORMAT_MAP[$format] ?? null;
            if ($mappedformat === null) {
                throw new coding_exception("Unrecognized editor text format on export: '$format'");
            }

            $resultdata = new wysiwyg_editor_data(
                text: $text,
                textformat: $mappedformat,
                files: $filemetas,
            );

            utils::array_set_nested($alldata, $element->getName(), array_converter::to_array($resultdata));
        });

        $context->on_import(function (array &$alldata) use ($element, $context, $draftitemid, $newdraftarea) {
            $myrawdata = utils::array_get_nested($alldata, $element->getName());
            if (!$myrawdata) {
                return;
            }

            /** @var wysiwyg_editor_data $mydata */
            $mydata = array_converter::from_array(wysiwyg_editor_data::class, $myrawdata);
            $text = $mydata->text;

            if ($this->fileuploads && $mydata->files) {
                global $USER;
                if ($newdraftarea) {
                    $questionid = $context->question->id ?? null;
                    if ($questionid === null) {
                        throw new \core\exception\coding_exception("We're loading a question, but its ID is unset.");
                    }

                    $ofs = di::get(options_file_service::class);
                    $ofs->prepare_draft_area($context->question->contextid, $questionid, $mydata->files, $USER->id, $draftitemid);
                }

                $filenamebyfileref = array_column($mydata->files, 'filename', 'fileref');

                $text = preg_replace_callback(
                    constants::QPY_OPTIONS_URL_PATTERN,
                    function (array $match) use ($draftitemid, $filenamebyfileref) {
                        $filename = $filenamebyfileref[strtolower($match['fileref'])] ?? null;
                        if ($filename === null) {
                            debugging("Editor text contains QPy URL for nonexistent options file: '$match[0]'");
                            return $match[0];
                        }
                        return moodle_url::make_draftfile_url($draftitemid, '/', $filename);
                    },
                    $text
                );
            }

            $format = array_search($mydata->textformat, self::FORMAT_MAP);
            if ($format === false) {
                // TODO: This blocks the entire question edit form, it would be much better to show an error for this element only.
                throw new moodle_exception('wysiwyg_editor_unknown_format', 'qtype_questionpy', a: $mydata->textformat);
            }

            utils::array_set_nested($alldata, $element->getName(), [
                'text' => $text,
                'format' => $format,
                'itemid' => $draftitemid,
            ]);
        });

        $this->render_help($element);
    }

    /**
     * In a similar manner to {@see file_rewrite_urls_to_pluginfile()}, this method rewrites draftfile URLs to `qpy://options/...`.
     *
     * @param string $text
     * @param array $files As returned by {@see options_file_service::get_qpy_files_metadata_from_draftitem()}.
     * @param int $draftitemid
     * @return string
     */
    private static function replace_draftfile_urls_with_qpy_urls(string $text, array $files, int $draftitemid): string {
        global $USER;

        $filesbyname = array_column($files, null, 'filename');

        $draftfileurls = extract_draft_file_urls_from_text($text);
        $expectedparts = [
            'contextid' => context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
        ];
        foreach ($draftfileurls as $draftfileurl) {
            // Ensure that the draftfile link points to the correct draft item of the correct user.
            foreach ($expectedparts as $name => $expectedvalue) {
                if ($draftfileurl[$name] != $expectedvalue) {
                    debugging("Editor text contains unexpected draftfile link. Expected $name == $expectedvalue, "
                        . "got $draftfileurl[$name].", DEBUG_DEVELOPER);
                    continue 2;
                }
            }

            $fileref = $filesbyname[$draftfileurl['filename']]->fileref ?? null;
            if (!$fileref) {
                debugging("Editor text contains draftfile link to nonexistent file {$draftfileurl['filename']}.", DEBUG_DEVELOPER);
                continue;
            }

            $text = str_replace($draftfileurl[0], options_file_service::create_qpy_url($fileref), $text);
        }

        return $text;
    }
}
