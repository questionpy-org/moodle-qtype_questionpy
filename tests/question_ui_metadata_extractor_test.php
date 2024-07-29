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
 * Unit tests for {@see question_ui_metadata_extractor}.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2023 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class question_ui_metadata_extractor_test extends \advanced_testcase {
    /**
     * Tests that metadata is correctly extracted from the UI's input elements.
     *
     * @covers \qtype_questionpy\question_ui_metadata_extractor
     * @covers \qtype_questionpy\question_metadata
     */
    public function test_should_extract_correct_metadata(): void {
        $input = file_get_contents(__DIR__ . "/question_uis/metadata.xhtml");

        $metadata = new question_ui_metadata_extractor($input);

        $this->assertEquals(new question_metadata([
            "my_number" => "42",
            "my_select" => "1",
            "my_radio" => "2",
            "my_text" => "Lorem ipsum dolor sit amet.",
        ], [
            "my_number" => PARAM_RAW,
            "my_select" => PARAM_RAW,
            "my_radio" => PARAM_RAW,
            "my_text" => PARAM_RAW,
            "my_button" => PARAM_RAW,
            "only_lowercase_letters" => PARAM_RAW,
            "between_5_and_10_chars" => PARAM_RAW,
        ], ["my_number"]), $metadata->extract());
    }
}
