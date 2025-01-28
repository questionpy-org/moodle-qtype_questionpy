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

namespace qtype_questionpy\exception;

// phpcs:ignore moodle.Commenting.InlineComment.DocBlock
/**
 * Possible request error codes.
 *
 * @package    qtype_questionpy
 * @author     Jan Britz
 * @copyright  2025 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum error_code: string {
    case queue_waiting_timeout = 'QUEUE_WAITING_TIMEOUT';
    case worker_timeout = 'WORKER_TIMEOUT';
    case out_of_memory = 'OUT_OF_MEMORY';
    case invalid_attempt_state = 'INVALID_ATTEMPT_STATE';
    case invalid_question_state = 'INVALID_QUESTION_STATE';
    case invalid_package = 'INVALID_PACKAGE';
    case invalid_request = 'INVALID_REQUEST';
    case invalid_options_form = 'INVALID_OPTIONS_FORM';
    case package_error = 'PACKAGE_ERROR';
    case package_not_found = 'PACKAGE_NOT_FOUND';
    case callback_api_error = 'CALLBACK_API_ERROR';
    case server_error = 'SERVER_ERROR';
}
