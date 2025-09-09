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

namespace qtype_questionpy\local\bridge;

use qtype_questionpy\question_bridge_base;

/**
 * Bridge class between QuestionPy and mod_quiz.
 *
 * This class is used to retrieve data that is not available through the Question API.
 *
 * @package    qtype_questionpy
 * @author     Martin Gauk
 * @copyright  2025 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_quiz extends question_bridge_base {
    /** @var \stdClass|null quiz attempt data as stored in database. */
    private $quizattempt = null;

    /**
     * Get additional LMS attributes.
     *
     * @param string[] $requestedattributes
     * @return string[]
     */
    protected function get_additional_lms_attributes(array $requestedattributes): array {
        $attributes = [];

        if (in_array('attempt_started_at', $requestedattributes)) {
            $attempt = $this->get_quiz_attempt_data();
            $attributes['attempt_started_at'] = date('c', $attempt->timestart);
        }

        if (in_array('submission_at', $requestedattributes)) {
            $attempt = $this->get_quiz_attempt_data();
            if ($attempt->timefinish) {
                $attributes['submission_at'] = date('c', $attempt->timefinish);
            }
        }

        if (in_array('lms_moodle_component_name', $requestedattributes)) {
            $attributes['lms_moodle_component_name'] = 'mod_quiz';
        }

        if (in_array('lms_moodle_module_instance', $requestedattributes)) {
            $attempt = $this->get_quiz_attempt_data();
            $attributes['lms_moodle_module_instance'] = (int) $attempt->quiz;
        }

        return $attributes;
    }

    /**
     * Get user or group id this attempt belongs to.
     *
     * @return array<'user'|'group', int>
     */
    public function get_user_or_group_id(): array {
        return ['user', $this->get_quiz_attempt_data()->userid];
    }

    /**
     * Getter to lazily load quiz attempt data.
     *
     * @return \stdClass quiz_attempts db record
     */
    private function get_quiz_attempt_data(): \stdClass {
        global $DB;

        if ($this->quizattempt === null) {
            $usageid = $this->attempt->get_usage_id();
            $this->quizattempt = $DB->get_record('quiz_attempts', ['uniqueid' => $usageid], '*', MUST_EXIST);
        }
        return $this->quizattempt;
    }
}
