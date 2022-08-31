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
 * Abstracts away the differences in rendering elements in a group (where the element is created, and added as part of
 * the group element) and outside of a group (where the element is added directly) while still allowing for checkbox
 * controllers, which use an entirely different method.
 */
abstract class render_context {
    public \moodleform $moodleform;
    public \MoodleQuickForm $mform;

    public function __construct(\moodleform $moodleform, \MoodleQuickForm $mform) {
        $this->moodleform = $moodleform;
        $this->mform = $mform;
    }

    abstract public function add_element(string $type, string $name, ...$args): object;

    abstract public function set_type(string $name, string $type): void;

    abstract public function next_unique_int(): int;

    abstract public function add_checkbox_controller(int $groupid): void;
}
