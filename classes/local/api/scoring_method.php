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

// phpcs:ignore moodle.Commenting.InlineComment.DocBlock
/**
 * Possible scoring methods.
 *
 * @package    qtype_questionpy
 * @author     Jan Britz
 * @copyright  2025 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum scoring_method: string {
    case always_manual_scoring_required = 'ALWAYS_MANUAL_SCORING_REQUIRED';
    case automatically_scorable = 'AUTOMATICALLY_SCORABLE';
    case automatically_scorable_with_score_adjustment = 'AUTOMATICALLY_SCORABLE_WITH_SCORE_ADJUSTMENT';
}
