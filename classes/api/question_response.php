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

use qtype_questionpy\array_converter\array_converter;
use qtype_questionpy\array_converter\converter_config;

defined('MOODLE_INTERNAL') || die;

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
    public string $state;

    /** @var string */
    public string $statehash;

    /** @var string */
    public string $gradingmethod;

    /** @var float|int */
    public float $grademinfraction = 0;

    /** @var float|int */
    public float $grademaxfraction = 1;

    /** @var float|null */
    public ?float $penalty = null;

    /** @var float|null */
    public ?float $randomguessscore = null;

    /** @var bool */
    public bool $rendereveryview = false;

    /** @var string|null */
    public ?string $generalfeedback = null;

    /**
     * Initialize a new question response.
     *
     * @param string $state     new question state
     * @param string $statehash hash of `$state`
     * @param string $gradingmethod
     */
    public function __construct(string $state, string $statehash, string $gradingmethod) {
        $this->state = $state;
        $this->statehash = $statehash;
        $this->gradingmethod = $gradingmethod;
    }
}

array_converter::configure(question_response::class, function (converter_config $config) {
    $config
        ->rename("state", "question_state")
        ->rename("statehash", "question_state_hash")
        ->rename("gradingmethod", "grading_method")
        ->rename("grademinfraction", "grade_min_fraction")
        ->rename("grademaxfraction", "grade_max_fraction")
        ->rename("randomguessscore", "random_guess_score")
        ->rename("rendereveryview", "render_every_view")
        ->rename("generalfeedback", "general_feedback");
});


