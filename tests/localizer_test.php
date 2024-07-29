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
 * Unit tests for the questionpy question type class.
 *
 * @package    qtype_questionpy
 * @copyright  2022 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class localizer_test extends \advanced_testcase {
    /**
     * Test the default of the function get_preferred_language.
     *
     * @covers \localizer::get_preferred_language
     * @return void
     */
    public function test_contains_default_language(): void {
        $result = localizer::get_preferred_languages();
        self::assertIsArray($result);
        self::assertContains('en', $result);
    }

    /**
     * Test if get_preferred_language returns current language.
     *
     * @covers \localizer::get_preferred_language
     * @return void
     */
    public function test_contains_current_language(): void {
        $result = localizer::get_preferred_languages();
        self::assertIsArray($result);
        self::assertContains(current_language(), $result);
    }
}
