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
     * @param bool $includehtml
     */
    public function __construct(
        /** @var string */
        public string $name,
        /** @var string */
        public string $label,
        /** @var bool */
        #[array_key('include_html')]
        public bool $includehtml = false
    ) {
    }

    /**
     * Render this item to the given context.
     *
     * @param render_context $context target context
     * @throws moodle_exception
     */
    public function render_to(render_context $context): void {
        /** @var MoodleQuickForm_editor $element */
        $element = $context->add_element(
            'editor',
            $this->name,
            $context->contextualize($this->label),
            null,
            [
                'maxfiles' => EDITOR_UNLIMITED_FILES,
                'subdirs' => self::SUBDIRS,
                'context' => $context->moodleform->context,
            ]
        );
        $context->set_type($this->name, PARAM_RAW);

        // This is usually done by file_get_submitted_draft_itemid, but that doesn't support the editor itself being nested.
        $draftitemid = $context->moodleform->optional_param($element->getName() . '[itemid]', null, PARAM_INT);
        if ($draftitemid === null) {
            $draftitemid = file_get_unused_draft_itemid();
            $draftareaprepared = false;
        } else {
            // There's already a draft area, which means it must already have been prepared.
            // If we were to try to prepare it again, we would either recreate deleted files, or run into unique key violations.
            $draftareaprepared = true;
        }

        $context->set_default($this->name, [
            'format' => FORMAT_HTML,
            'itemid' => $draftitemid,
        ]);

        $context->on_export(function (array &$alldata) use ($element, $context, $draftitemid) {
            $mydata = utils::array_get_nested($alldata, $element->getName());
            if (!$mydata) {
                return;
            }

            $markup = $mydata['text'];
            $format = $mydata['format'];
            $html = null;

            // If the format isn't HTML but $includehtml is passed, we need to convert the markup to HTML.
            // Although we specify 'noclean' below, $CFG->forceclean may override us. Since cleaning would remove qpy:// URLs, we
            // do this _before_ replacing URLs.
            if ($format != FORMAT_HTML && $this->includehtml) {
                $html = format_text($markup, $format, options: [
                    'context' => $context->moodleform->context,
                    // We leave cleaning to when the content is output. (Placeholder values are cleaned by default, for instance.)
                    'noclean' => true,
                    // If filter is true (default), format_text replaces draftfile URLs with brokenfile.
                    'filter' => false,
                ]);
            }

            // Remove all draft files that aren't referenced in the markup.
            file_remove_editor_orphaned_files($mydata);

            global $USER;
            /** @var array<string, file_metadata> $metadatabyname */
            $metadatabyname = di::get(options_file_service::class)->get_qpy_files_metadata_from_draftitem($USER->id, $draftitemid);

            // Since we had to format_text before URL replacement, we need to do it to both $markup and $html :(.
            $markup = self::replace_draftfile_urls_with_qpy_urls($markup, $metadatabyname, $draftitemid);
            if ($html) {
                $html = self::replace_draftfile_urls_with_qpy_urls($html, $metadatabyname, $draftitemid);
            }

            $mappedformat = self::FORMAT_MAP[$format] ?? null;
            if ($mappedformat === null) {
                throw new coding_exception("Unrecognized editor text format on export: '$format'");
            }

            $resultdata = new wysiwyg_editor_data(
                markup: $markup,
                markupformat: $mappedformat,
                files: $metadatabyname,
                html: $html
            );

            // At this time, we don't know whether the question will be saved or the draft validated etc., and we don't know the
            // question id, so we don't save the draft files ourselves. But we do need to let question_service know which draft
            // items are used so it can save their contents.
            $alldata['qpy_options_draftitems'][] = $draftitemid;

            utils::array_set_nested($alldata, $element->getName(), array_converter::to_array($resultdata));
        });

        $context->on_import(function (array &$alldata) use ($element, $context, $draftitemid, $draftareaprepared) {
            $myrawdata = utils::array_get_nested($alldata, $element->getName());
            if (!$myrawdata) {
                return;
            }

            /** @var wysiwyg_editor_data $mydata */
            $mydata = array_converter::from_array(wysiwyg_editor_data::class, $myrawdata);

            global $USER;
            if ($mydata->files && !$draftareaprepared) {
                $questionid = $context->question->id ?? null;
                if ($questionid === null) {
                    throw new \core\exception\coding_exception("We're loading a question, but its ID is unset.");
                }

                $ofs = di::get(options_file_service::class);
                $ofs->prepare_draft_area($context->moodleform->context->id, $questionid, $mydata->files, $USER->id, $draftitemid);
            }

            $filenamebyfileref = array_flip(array_map(fn($fmeta) => $fmeta->fileref, $mydata->files));

            $replacedtext = preg_replace_callback(
                constants::QPY_OPTIONS_URL_PATTERN,
                function (array $match) use ($draftitemid, $filenamebyfileref) {
                    $filename = $filenamebyfileref[strtolower($match['fileref'])] ?? null;
                    if (!$filename) {
                        debugging("Editor text contains QPy URL for nonexistent options file: '$match[0]'");
                        return $match[0];
                    }
                    return moodle_url::make_draftfile_url($draftitemid, '/', $filename);
                },
                $mydata->markup
            );

            $format = array_search($mydata->markupformat, self::FORMAT_MAP);
            if ($format === false) {
                // TODO: This blocks the entire question edit form, it would be much better to show an error for this element only.
                throw new moodle_exception('wysiwyg_editor_unknown_format', 'qtype_questionpy', a: $mydata->markupformat);
            }

            utils::array_set_nested($alldata, $element->getName(), [
                'text' => $replacedtext,
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
     * @param array $metadatabyname As returned by {@see options_file_service::get_qpy_files_metadata_from_draftitem()}.
     * @param int $draftitemid
     * @return string
     */
    private static function replace_draftfile_urls_with_qpy_urls(string $text, array $metadatabyname, int $draftitemid): string {
        global $USER;

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

            $fileref = $metadatabyname[$draftfileurl['filename']]->fileref ?? null;
            if (!$fileref) {
                debugging("Editor text contains draftfile link to nonexistent file {$draftfileurl['filename']}.", DEBUG_DEVELOPER);
                continue;
            }

            $text = str_replace($draftfileurl[0], options_file_service::create_qpy_url($fileref), $text);
        }

        return $text;
    }
}
