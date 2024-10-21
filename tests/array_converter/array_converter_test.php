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

use qtype_questionpy\array_converter\test_classes\polymorphic;
use qtype_questionpy\array_converter\test_classes\simple;
use qtype_questionpy\array_converter\test_classes\uses_element_class;
use qtype_questionpy\array_converter\test_classes\uses_rename_and_alias;
use qtype_questionpy\array_converter\test_classes\variant2;

/**
 * Tests of {@see array_converter}.
 *
 * @covers \qtype_questionpy\array_converter
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2024 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class array_converter_test extends \advanced_testcase {
    public function test_should_deserialize_from_rename(): void {
        require_once(__DIR__ . "/test_classes/uses_rename_and_alias.php");

        $result = array_converter::from_array(uses_rename_and_alias::class, ["my_prop_1" => "value1", "my_prop_2" => "value2"]);

        $this->assertEquals(uses_rename_and_alias::class, get_class($result));
        $this->assertEquals("value1", $result->myprop1);
        $this->assertEquals("value2", $result->myprop2);
    }

    public function test_should_serialize_to_rename(): void {
        require_once(__DIR__ . "/test_classes/uses_rename_and_alias.php");

        $instance = new uses_rename_and_alias("value2");
        $instance->myprop1 = "value1";
        $result = array_converter::to_array($instance);

        $this->assertEquals($result, [
            "my_prop_1" => "value1",
            "my_prop_2" => "value2",
        ]);
    }

    public function test_should_deserialize_from_alias(): void {
        require_once(__DIR__ . "/test_classes/uses_rename_and_alias.php");

        $result = array_converter::from_array(uses_rename_and_alias::class, ["my_alias_1" => "value1", "my_alias_2" => "value2"]);

        $this->assertEquals(uses_rename_and_alias::class, get_class($result));
        $this->assertEquals("value1", $result->myprop1);
        $this->assertEquals("value2", $result->myprop2);
    }

    public function test_should_deserialize_array_elements(): void {
        require_once(__DIR__ . "/test_classes/uses_element_class.php");

        $result = array_converter::from_array(uses_element_class::class, ["myarray" => [
            ["prop" => "value1"],
            ["prop" => "value2"],
        ]]);

        $this->assertEquals(uses_element_class::class, get_class($result));
        $this->assertEquals([new simple("value1"), new simple("value2")], $result->myarray);
    }

    public function test_should_deserialize_polymorphic(): void {
        require_once(__DIR__ . "/test_classes/polymorphic.php");
        require_once(__DIR__ . "/test_classes/variant2.php");

        $result = array_converter::from_array(polymorphic::class, [
            "discriminator" => "var2",
            "prop" => "value1",
        ]);

        $this->assertEquals(variant2::class, get_class($result));
        $this->assertEquals("value1", $result->prop);
    }

    public function test_should_deserialize_polymorphic_fallback(): void {
        require_once(__DIR__ . "/test_classes/polymorphic.php");

        $result = array_converter::from_array(polymorphic::class, [
            "discriminator" => "abcdefg",
            "prop" => "value2",
        ]);

        $this->assertEquals(simple::class, get_class($result));
        $this->assertEquals("value2", $result->prop);

        $this->assertDebuggingCalled("Unknown value for discriminator 'discriminator': 'abcdefg'. Using fallback "
            . "variant 'qtype_questionpy\\array_converter\\test_classes\\simple'.");
    }
}
