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

namespace qtype_questionpy\form;

/**
 * Regular {@see render_context} which delegates to {@see \moodleform} and {@see \MoodleQuickForm}.
 *
 * @see        group_render_context
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class root_render_context extends render_context {
    private int $nextuniqueint = 1;

    public function add_element(string $type, string $name, ...$args): object {
        return $this->mform->addElement($type, $name, ...$args);
    }

    public function set_type(string $name, string $type): void {
        $this->mform->setType($name, $type);
    }

    public function set_default(string $name, $default): void {
        $this->mform->setDefault($name, $default);
    }

    public function add_rule(string  $name, ?string $message, string $type, ?string $format = null,
                             ?string $validation = "server", bool $reset = false, bool $force = false): void {
        $this->mform->addRule($name, $message, $type, $format, $validation, $reset, $force);
    }

    public function next_unique_int(): int {
        return $this->nextuniqueint++;
    }

    public function add_checkbox_controller(int $groupid): void {
        $this->moodleform->add_checkbox_controller($groupid);
    }
}
