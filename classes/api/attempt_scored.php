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

namespace qtype_questionpy\api;

use qtype_questionpy\array_converter\attributes\array_key;

/**
 * A scored attempt at a QuestionPy question.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2023 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attempt_scored extends attempt {
    /** @var string|null */
    #[array_key('scoring_state')]
    public ?string $scoringstate;

    /** @var scoring_code */
    #[array_key('scoring_code')]
    public scoring_code $scoringcode;

    /** @var float|null */
    public ?float $score = null;

    /**
     * Initializes a new instance.
     *
     * @param int $variant
     * @param attempt_ui $ui
     * @param scoring_code $scoringcode
     * @param string|null $scoringstate
     * @param package_dependency[] $packagedependencies
     */
    public function __construct(int $variant, attempt_ui $ui, scoring_code $scoringcode, ?string $scoringstate,
                                array $packagedependencies) {
        parent::__construct($variant, $ui, $packagedependencies);

        $this->scoringstate = $scoringstate;
        $this->scoringcode = $scoringcode;
    }
}
