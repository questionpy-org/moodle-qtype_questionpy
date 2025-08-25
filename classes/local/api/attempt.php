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

/**
 * An attempt at a QuestionPy question.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2023 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attempt extends localized {
    /** @var int */
    public int $variant;

    /** @var attempt_ui */
    public attempt_ui $ui;

    /** @var package_dependency[] */
    #[array_key('package_dependencies')]
    #[array_element_class(package_dependency::class)]
    public array $packagedependencies;

    /**
     * Initializes a new instance.
     *
     * @param string $lang
     * @param int $variant
     * @param attempt_ui $ui
     * @param package_dependency[] $packagedependencies
     */
    public function __construct(string $lang, int $variant, attempt_ui $ui, array $packagedependencies) {
        parent::__construct($lang);

        $this->variant = $variant;
        $this->ui = $ui;
        $this->packagedependencies = $packagedependencies;
    }
}
