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
 * Response from the server for a created or updated question.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2023 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_response extends localized {
    /** @var string */
    #[array_key('question_state')]
    public string $state;

    /** @var int */
    #[array_key('num_variants')]
    public int $numvariants = 1;

    /** @var float */
    #[array_key('score_min')]
    public float $scoremin = 0;

    /** @var float */
    #[array_key('score_max')]
    public float $scoremax = 1;

    /** @var scoring_method */
    #[array_key('scoring_method')]
    public scoring_method $scoringmethod;

    /** @var float|null */
    public ?float $penalty = null;

    /** @var float|null */
    #[array_key('random_guess_score')]
    public ?float $randomguessscore = null;

    /** @var bool */
    #[array_key('response_analysis_by_variant')]
    public bool $responseanalysisbyvariant = false;

    /** @var subquestion[] */
    #[array_element_class(subquestion::class)]
    public array $subquestions = [];

    /** @var lms_permissions|null */
    #[array_key('lms_permissions')]
    public ?lms_permissions $lmspermissions = null;

    /**
     * Initialize a new question response.
     *
     * @param string $lang
     * @param string $state new question state
     * @param scoring_method $scoringmethod
     */
    public function __construct(string $lang, string $state, scoring_method $scoringmethod) {
        parent::__construct($lang);

        $this->state = $state;
        $this->scoringmethod = $scoringmethod;
    }
}
