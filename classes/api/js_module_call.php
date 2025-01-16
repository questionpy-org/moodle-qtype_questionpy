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

use qtype_questionpy\array_converter\attributes\array_key;

/**
 * Model defining what JavaScript functions need to be called.
 *
 * @package    qtype_questionpy
 * @author     Martin Gauk
 * @copyright  2024 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class js_module_call {
    /** @var string */
    public string $module;

    /** @var string */
    public string $function;

    /** @var string|null */
    public ?string $data;

    /** @var display_role|null */
    #[array_key('if_role')]
    public ?display_role $ifrole;

    /** @var feedback_type|null */
    #[array_key('if_feedback_type')]
    public ?feedback_type $iffeedbacktype;
}
