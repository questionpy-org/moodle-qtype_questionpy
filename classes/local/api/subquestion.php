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
 * Subquestion.
 *
 * @package    qtype_questionpy
 * @author     Jan Britz
 * @copyright  2025 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class subquestion {
    /** @var string */
    #[array_key('subquestion_id')]
    public string $subquestionid;

    /** @var float|null */
    #[array_key('score_max')]
    public ?float $scoremax;

    /** @var response_class[]|null */
    #[array_key('response_classes')]
    #[array_element_class(response_class::class)]
    public ?array $responseclasses;
}
