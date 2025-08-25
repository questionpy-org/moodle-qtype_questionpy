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

use coding_exception;
use moodle_exception;
use qtype_questionpy\local\array_converter\array_converter;
use qtype_questionpy\local\array_converter\attributes\array_element_class;

/**
 * Container for question-related data like requested lms permissions and more.
 *
 * @package    qtype_questionpy
 * @copyright  2025 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_data {
    /**
     * Constructs {@see question_data}.
     *
     * @param string $lang
     * @param int $numvariants
     * @param float $scoremin
     * @param float $scoremax
     * @param scoring_method $scoringmethod
     * @param float|null $penalty
     * @param float|null $randomguessscore
     * @param bool $responseanalysisbyvariant
     * @param subquestion[] $subquestions
     * @param lms_permissions|null $permissions
     */
    public function __construct(
        /** @var string */
        public string $lang,

        /** @var int */
        public int $numvariants,

        /** @var float */
        public float $scoremin,

        /** @var float */
        public float $scoremax,

        /** @var scoring_method */
        public scoring_method $scoringmethod,

        /** @var float|null */
        public ?float $penalty,

        /** @var float|null */
        public ?float $randomguessscore,

        /** @var bool */
        public bool $responseanalysisbyvariant,

        /** @var subquestion[] */
        #[array_element_class(subquestion::class)]
        public array $subquestions,

        /** @var lms_permissions|null */
        public ?lms_permissions $permissions
    ) {
    }

    /**
     * Creates {@see question_data} from {@see question_response}.
     *
     * @param question_response $response
     * @return self
     */
    public static function from_question_response(question_response $response): self {
        return new self(
            $response->lang,
            $response->numvariants,
            $response->scoremin,
            $response->scoremax,
            $response->scoringmethod,
            $response->penalty,
            $response->randomguessscore,
            $response->responseanalysisbyvariant,
            $response->subquestions,
            $response->lmspermissions
        );
    }

    /**
     * Creates {@see question_data} from JSON.
     *
     * @param string $json
     * @return self
     * @throws moodle_exception
     */
    public static function from_json(string $json): self {
        $data = json_decode($json, associative: true);
        return array_converter::from_array(self::class, $data);
    }

    /**
     * Converts {@see question_data} to JSON.
     *
     * @throws coding_exception
     */
    public function to_json(): string {
        $array = array_converter::to_array($this);
        return json_encode($array);
    }

    /**
     * Checks if this instance is equal to the other instance.
     *
     * @param question_data $other
     * @return bool
     * @throws coding_exception
     */
    public function equals(question_data $other): bool {
        $a = array_converter::to_array($this);
        $b = array_converter::to_array($other);
        return sort($a) == sort($b);
    }
}
