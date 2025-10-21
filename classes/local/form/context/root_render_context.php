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

namespace qtype_questionpy\local\form\context;

use Closure;
use core\uuid;
use moodle_exception;
use qtype_questionpy\constants;

/**
 * Uppermost render context.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class root_render_context extends mform_render_context {
    /** @var int the next int which will be returned by {@see next_unique_int} */
    private int $nextuniqueint = 1;

    /** @var callable can be set from tests to supply mock UUIDs */
    public $uuidgen = [uuid::class, 'generate'];

    /** @var (Closure(array&): void)[] $onexportcallbacks */
    public array $onexportcallbacks = [];

    /** @var (Closure(array&): void)[] $onimportcallbacks */
    public array $onimportcallbacks = [];

    /** @var int */
    public int $combineddraftitemid = 0;

    /**
     * Get a unique and deterministic integer for use in generated element names and IDs.
     *
     * @return int a unique and deterministic integer for use in generated element names and IDs.
     */
    public function next_unique_int(): int {
        return $this->nextuniqueint++;
    }

    /**
     * Replaces occurrences of `{ qpy:... }` with the appropriate contextual variable, if any.
     *
     * This render context returns the string unchanged.
     *
     * @param string|null $text string possibly containing `{ qpy:... }` format specifiers
     * @return string|null input string with format specifiers replaced
     */
    public function contextualize(?string $text): ?string {
        return $text;
    }

    /**
     * Generate a new UUID. Probably uses {@see uuid}, but may be overridden for tests.
     *
     * @return string
     */
    public function generate_uuid(): string {
        return ($this->uuidgen)();
    }

    /**
     * Mutate data before it is exported from the form.
     *
     * This is called by {@see question_edit_form::get_data()} and {@see question_edit_form::get_submitted_data()}. The resulting
     * data might be saved by {@see question_service::upsert_question()} or validated as a draft.
     *
     * The callback is given the entire question data and should mutate the parts relevant to it.
     *
     * @param Closure $onexport Callback that receives form data by reference for export conversion
     * @return void
     */
    public function on_export(Closure $onexport): void {
        $this->onexportcallbacks[] = $onexport;
    }

    /**
     * Mutate data from the QPy server before it is added to the mform in {@see question_edit_form::set_data()}.
     *
     * The callback is given the entire question data and should mutate the parts relevant to it.
     *
     * @param Closure $onimport Callback that receives form data by reference for import conversion
     * @return void
     */
    public function on_import(Closure $onimport): void {
        $this->onimportcallbacks[] = $onimport;
    }

    /**
     * Uses {@see file_prepare_draft_area} to copy all options files to a new draft area.
     *
     * We do this because {@see file_prepare_draft_area} does some possibly important and hard-to-rewrite magic concerning the
     * file source.
     *
     * @return int
     * @throws moodle_exception
     */
    public function prepare_combined_draft_area(): int {
        if ($this->combineddraftitemid) {
            return $this->combineddraftitemid;
        }
        if (!$this->question->id) {
            // New question -> no files yet.
            $this->combineddraftitemid = file_get_unused_draft_itemid();
            return $this->combineddraftitemid;
        }

        file_prepare_draft_area(
            draftitemid: $this->combineddraftitemid,
            contextid: $this->question->contextid,
            component: 'qtype_questionpy',
            filearea: constants::FILEAREA_OPTIONS,
            itemid: $this->question->id
        );

        return $this->combineddraftitemid;
    }
}
