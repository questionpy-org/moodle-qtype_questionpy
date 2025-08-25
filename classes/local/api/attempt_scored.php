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
    public ?string $scoringstate = null;

    /** @var scoring_code */
    #[array_key('scoring_code')]
    public scoring_code $scoringcode;

    /** @var float|null */
    public ?float $score;

    /** @var float|null */
    #[array_key('score_adjusted')]
    public ?float $scoreadjusted;

    /** @var array<string, scored_input> */
    #[array_key('scored_inputs')]
    #[array_element_class(scored_input::class)]
    public ?array $scoredinputs = [];

    /** @var array<string, scored_subquestion> */
    #[array_key('scored_subquestions')]
    #[array_element_class(scored_subquestion::class)]
    public ?array $scoredsubquestions;

    /**
     * Initializes a new instance.
     *
     * @param string $lang
     * @param int $variant
     * @param attempt_ui $ui
     * @param scoring_code $scoringcode
     * @param string|null $scoringstate
     * @param package_dependency[] $packagedependencies
     */
    public function __construct(string $lang, int $variant, attempt_ui $ui, scoring_code $scoringcode, ?string $scoringstate,
                                array $packagedependencies) {
        parent::__construct($lang, $variant, $ui, $packagedependencies);

        $this->scoringstate = $scoringstate;
        $this->scoringcode = $scoringcode;
    }
}
