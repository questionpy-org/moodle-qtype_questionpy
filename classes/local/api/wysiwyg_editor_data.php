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

namespace qtype_questionpy\local\api;

use qtype_questionpy\local\array_converter\attributes\array_element_class;
use qtype_questionpy\local\array_converter\attributes\array_key;
use qtype_questionpy\local\files\file_metadata;

/**
 * Data class for WYSIWYG editor data.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2025 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wysiwyg_editor_data {
    /**
     * Initialize a new WYSIWYG editor data instance.
     *
     * @param string $markup The markup content
     * @param string $markupformat The format of the markup
     * @param file_metadata[] $files Associated files
     * @param string|null $html The HTML content. Can be null but may be required by the package.
     */
    public function __construct(
        /** @var string $markup */
        #[array_key('markup')]
        public string $markup,
        /** @var string $markupformat */
        #[array_key('markup_format')]
        public string $markupformat,
        /** @var file_metadata[] $files */
        #[array_key('files')]
        #[array_element_class(file_metadata::class)]
        public array $files = [],
        /** @var string|null $html */
        #[array_key('html')]
        public ?string $html = null,
    ) {
    }
}
