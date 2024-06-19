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

namespace qtype_questionpy;

/**
 * Metadata about a question attempt, extracted by {@see question_ui_renderer} from the XML.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2023 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_metadata {
    /**
     * @var array|null if known, an array of `name => correct_value` entries for the expected response fields
     * @see \qtype_renderer::correct_response()
     */
    public ?array $correctresponse = null;

    /**
     * @var array an array of `name => PARAM_X` entries for the expected response fields
     * @see \question_definition::get_expected_data()
     */
    public array $expecteddata = [];

    /**
     * @var string[] an array of required field names
     * @see \question_manually_gradable::is_complete_response()
     * @see \question_manually_gradable::is_gradable_response()
     */
    public array $requiredfields = [];

    /**
     * Initializes a new instance.
     *
     * @param array|null $correctresponse if known, an array of `name => correct_value` entries for the expected
     *                                    response fields
     * @param array $expecteddata an array of `name => PARAM_X` entries for the expected response fields
     * @param string[] $requiredfields an array of required field names
     */
    public function __construct(?array $correctresponse = null, array $expecteddata = [],
                                array $requiredfields = []) {
        $this->correctresponse = $correctresponse;
        $this->expecteddata = $expecteddata;
        $this->requiredfields = $requiredfields;
    }
}
