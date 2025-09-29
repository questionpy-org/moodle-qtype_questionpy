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

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Framework\MockObject\Rule\InvocationOrder;

/**
 * Polyfills for breaking changes between PHPUnit versions.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2025 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class phpunit_compat {
    /**
     * Polyfill for the removed `withConsecutive` mocker method removed in PHPUnit 10.
     *
     * Moodle versions we support use PHPUnit versions ranging from 9.6 to 11, during which time `withConsecutive` was removed
     * without replacement and `getInvocationCount` was renamed to `numberOfInvocations`.
     *
     * @param InvocationOrder $matcher
     * @param mixed ...$expected
     * @return Constraint
     */
    public static function consecutively(InvocationOrder $matcher, mixed ...$expected): Constraint {
        return Assert::callback(function ($actual) use ($matcher, $expected) {
            if (method_exists($matcher, 'numberOfInvocations')) {
                $index = $matcher->numberOfInvocations() - 1;
            } else {
                $index = $matcher->getInvocationCount() - 1;
            }

            if ($index >= count($expected)) {
                return false;
            }

            return $expected[$index] == $actual;
        });
    }
}
