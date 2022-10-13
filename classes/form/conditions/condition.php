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

namespace qtype_questionpy\form\conditions;

use qtype_questionpy\array_converter\array_converter;
use qtype_questionpy\array_converter\converter_config;

defined('MOODLE_INTERNAL') || die;

/**
 * Base class for QuestionPy form element conditions.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class condition {

    /** @var string $name name of the target element */
    public string $name;

    /**
     * Initializes the condition.
     *
     * @param string $name name of the target element
     */
    public function __construct(string $name) {
        $this->name = $name;
    }

    /**
     * Return the `[$condition]` or `[$condition, $value]` tuple  to pass to {@see \MoodleQuickForm::disabledIf()} or
     * {@see \MoodleQuickForm::hideIf()} after the depended on element's name.
     *
     * @return array
     */
    abstract public function to_mform_args(): array;
}

array_converter::configure(condition::class, function (converter_config $config) {
    $config
        ->discriminate_by("kind")
        ->variant("is_checked", is_checked::class)
        ->variant("is_not_checked", is_not_checked::class)
        ->variant("equals", equals::class)
        ->variant("does_not_equal", does_not_equal::class)
        ->variant("in", in::class);
});
