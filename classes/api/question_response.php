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
 * Response from the server for a created or updated question.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2023 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_response {
    /** @var string */
    #[array_key("question_state")]
    public string $state;

    /** @var string */
    #[array_key("scoring_method")]
    public string $scoringmethod;

    /** @var float|int */
    #[array_key("score_min")]
    public float $scoremin = 0;

    /** @var float|int */
    #[array_key("score_max")]
    public float $scoremax = 1;

    /** @var float|null */
    public ?float $penalty = null;

    /** @var float|null */
    #[array_key("random_guess_score")]
    public ?float $randomguessscore = null;

    /** @var bool */
    #[array_key("render_every_view")]
    public bool $rendereveryview = false;

    /** @var string|null */
    #[array_key("general_feedback")]
    public ?string $generalfeedback = null;

    /**
     * Initialize a new question response.
     *
     * @param string $state new question state
     * @param string $scoringmethod
     */
    public function __construct(string $state, string $scoringmethod) {
        $this->state = $state;
        $this->scoringmethod = $scoringmethod;
    }
}
