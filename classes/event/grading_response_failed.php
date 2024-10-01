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

namespace qtype_questionpy\event;

use moodle_exception;
use moodle_url;

/**
 * Grading attempt failed event.
 *
 * @package    qtype_questionpy
 * @author     Jan Britz
 * @copyright  2024 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grading_response_failed extends \core\event\base {
    /**
     * Initialise event parameters.
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Returns localised event name.
     *
     * @return string
     * @throws moodle_exception
     */
    public static function get_name() {
        return get_string('event_grading_response_failed', 'qtype_questionpy');
    }

    /**
     * Returns non-localised event description with id's for admin use only.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' encountered an error when grading a response to the question with id " .
            "'{$this->other['questionid']}': {$this->other['errormessage']}";
    }

    /**
     * Validate our custom data.
     */
    public function validate_data() {
        // The questionid can be null (e.g. in tests).
        if (array_key_exists('questionid', $this->other) && !isset($this->other['errormessage'])) {
            throw new \coding_exception('"questionid" and "errormessage" are required in "other".');
        }
    }
}
