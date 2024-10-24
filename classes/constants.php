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
 * Constants used in multiple places.
 *
 * @package    qtype_questionpy
 * @author     Jan Britz
 * @copyright  2024 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class constants {
    /** @var string XML namespace for XHTML */
    public const NAMESPACE_XHTML = 'http://www.w3.org/1999/xhtml';
    /** @var string XML namespace for our custom things */
    public const NAMESPACE_QPY = 'http://questionpy.org/ns/question';

    /** @var string */
    public const QT_VAR_ATTEMPT_STATE = "_attemptstate";
    /** @var string */
    public const QT_VAR_SCORING_STATE = "_scoringstate";
}
