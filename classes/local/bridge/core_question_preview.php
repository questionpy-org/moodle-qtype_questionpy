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
 * Bridge class between QuestionPy and core_question_preview.
 *
 * This class is used to retrieve data that is not available through the Question API.
 *
 * @package    qtype_questionpy
 * @author     Martin Gauk
 * @copyright  2025 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_question_preview extends question_bridge_base {
    /** @var \stdClass|null quiz attempt data as stored in database. */
    private $quizattempt;

    /**
     * Get additional LMS attributes.
     *
     * @param string[] $requestedattributes
     * @return string[]
     */
    protected function get_additional_lms_attributes(array $requestedattributes): array {
        $attributes = [];

        if (in_array('attempt_started_at', $requestedattributes)) {
            $firststep = $this->attempt->get_step(0);
            $attributes['attempt_started_at'] = date('c', $firststep->get_timecreated());
        }

        if (in_array('lms_moodle_component_name', $requestedattributes)) {
            $attributes['lms_moodle_component_name'] = 'core_question_preview';
        }

        return $attributes;
    }

    /**
     * Get user or group id this attempt belongs to.
     *
     * @return array<'user'|'group', int>
     */
    public function get_user_or_group_id(): array {
        if ($this->context instanceof \core\context\user) {
            return ['user', $this->context->instanceid];
        }
        throw new \coding_exception('Expected a user context, got ' . get_class($this->context));
    }
}
