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
 * See {@see \question_type::get_possible_responses()} and {@see \question_with_responses::classify_response()}.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2023 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class classified_response {

    /** @var string */
    public string $subquestionid;

    /** @var string */
    public string $responseclass;

    /** @var string */
    public string $response;

    /** @var float */
    public float $score;

    /**
     * Initializes a new instance.
     *
     * @param string $subquestionid
     * @param string $responseclass
     * @param string $response
     * @param float $score
     */
    public function __construct(string $subquestionid, string $responseclass, string $response, float $score) {
        $this->subquestionid = $subquestionid;
        $this->responseclass = $responseclass;
        $this->response = $response;
        $this->score = $score;
    }
}

array_converter::configure(attempt_scored::class, function (converter_config $config) {
    $config->rename("subquestionid", "subquestion_id")
        ->rename("responseclass", "response_class");
});
