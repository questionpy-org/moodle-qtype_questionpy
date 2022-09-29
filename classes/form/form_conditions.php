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

use qtype_questionpy\deserializable;
use qtype_questionpy\form\conditions\condition;
use qtype_questionpy\form\elements\form_element;

/**
 * Trait for elements which can have conditions on other elements.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait form_conditions {
    /** @var condition[] */
    public array $disableif = [];
    /** @var condition[] */
    public array $hideif = [];

    /**
     * Renders the conditions. To be called by the {@see renderable::render_to() render_to} of elements.
     *
     * @param render_context $context target context
     * @param string $name            name of this element
     */
    private function render_conditions(render_context $context, string $name) {
        foreach ($this->disableif as $disableif) {
            $context->disable_if($name, $disableif);
        }

        foreach ($this->hideif as $hideif) {
            $context->hide_if($name, $hideif);
        }
    }

    /**
     * Deserializes the conditions from an array, mutating this instance and returning it for chaining.
     *
     * @param array $array source array, probably parsed from JSON
     * @see deserializable::from_array()
     */
    private function deserialize_conditions(array $array): self {
        $this->disableif = array_map([condition::class, "from_array_any"], $array["disable_if"]) ?? [];
        $this->hideif = array_map([condition::class, "from_array_any"], $array["hide_if"]) ?? [];
        return $this;
    }

    /**
     * Adds the conditions of this element to the given array and return it.
     *
     * @param array $array incomplete array representation of this element
     * @see form_element::to_array()
     */
    private function serialize_conditions(array $array): array {
        unset($array["disableif"], $array["hideif"]);
        $array["disable_if"] = $this->disableif;
        $array["hide_if"] = $this->hideif;
        return $array;
    }

    /**
     * Adds the given condition to {@see disableif} and returns this instance for chaining.
     *
     * @param condition $condition the condition to add
     * @return self $this
     */
    public function disable_if(condition $condition): self {
        $this->disableif[] = $condition;
        return $this;
    }

    /**
     * Adds the given condition to {@see hideif} and returns this instance for chaining.
     *
     * @param condition $condition the condition to add
     * @return self $this
     */
    public function hide_if(condition $condition): self {
        $this->hideif[] = $condition;
        return $this;
    }
}
