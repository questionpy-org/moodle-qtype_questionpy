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
 * QuestionPy application server request failed event.
 *
 * @package    qtype_questionpy
 * @author     Jan Britz
 * @copyright  2024 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class request_failed extends \core\event\base {
    /**
     * Initialise event parameters.
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Returns localised event name.
     *
     * @return string
     * @throws moodle_exception
     */
    public static function get_name() {
        return get_string('event_request_failed', 'qtype_questionpy');
    }

    /**
     * Returns non-localised event description with id's for admin use only.
     *
     * @return string
     */
    public function get_description() {
        return "{$this->other['message']}\nThere was an error requesting the application server:\n{$this->other['info']}";
    }

    /**
     * Returns the url to the QuestionPy plugin settings.
     *
     * @throws moodle_exception
     */
    public function get_url() {
        return new moodle_url('/admin/settings.php', ['section' => 'qtypesettingquestionpy']);
    }

    /**
     * Validate our custom data.
     *
     * Require the following fields:
     * - url
     * - payload
     * - message
     *
     * Throw \coding_exception or debugging() notice in case of any problems.
     */
    public function validate_data() {
        if (!isset($this->other['message'], $this->other['info'])) {
            throw new \coding_exception('"message" and "info" are required in "other".');
        }
    }
}
