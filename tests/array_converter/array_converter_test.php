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

namespace qtype_questionpy\array_converter;

use qtype_questionpy\array_converter\attributes\array_key;

class uses_rename {

    #[array_key("my_prop_1")]
    public string $myprop1;

    public function __construct(
        #[array_key("my_prop_2")]
        public string $myprop2
    ) {
    }
}

/**
 *
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2024 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class array_converter_test extends \advanced_testcase {

    public function test_deserialize_rename(): void {
        $result = array_converter::from_array(uses_rename::class, ["my_prop_1" => "value1", "my_prop_2" => "value2"]);

        $this->assertInstanceOf(uses_rename::class, $result);
        $this->assertEquals("value1", $result->myprop1);
        $this->assertEquals("value2", $result->myprop2);
    }
}
