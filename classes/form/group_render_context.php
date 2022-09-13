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

use qtype_questionpy\form\elements\group_element;

/**
 * {@see render_context} for groups of elements ({@see group_element}s). Instead of adding elements to the
 * {@see \MoodleQuickForm}, they are added to an array.
 *
 * @see        root_render_context
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group_render_context extends render_context {
    public array $elements = [];
    public array $types = [];
    public array $defaults = [];
    public array $rules = [];

    private render_context $root;

    public function __construct(render_context $root) {
        parent::__construct($root->moodleform, $root->mform);
        $this->root = $root;
    }

    public function add_element(string $type, string $name, ...$args): object {
        $element = $this->root->mform->createElement($type, $name, ...$args);
        $this->elements[] = $element;
        return $element;
    }

    public function set_type(string $name, string $type): void {
        $this->types[$name] = $type;
    }

    public function set_default(string $name, $default): void {
        $this->defaults[$name] = $default;
    }

    public function add_rule(string  $name, ?string $message, string $type, ?string $format = null,
                             ?string $validation = "server", bool $reset = false, bool $force = false): void {
        if (!isset($this->rules[$name])) {
            $this->rules[$name] = [];
        }

        $this->rules[$name][] = [$message, $type, $format, $validation, $reset];
    }

    public function next_unique_int(): int {
        return $this->root->next_unique_int();
    }

    public function add_checkbox_controller(int $groupid): void {
        $this->root->add_checkbox_controller($groupid);
    }
}
