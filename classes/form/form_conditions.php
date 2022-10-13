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

use qtype_questionpy\array_converter\array_converter;
use qtype_questionpy\array_converter\converter_config;
use qtype_questionpy\form\conditions\condition;

defined('MOODLE_INTERNAL') || die;

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

array_converter::configure(form_conditions::class, function (converter_config $config) {
    $config
        ->rename("disableif", "disable_if")
        ->array_elements("disableif", condition::class)
        ->rename("hideif", "hide_if")
        ->array_elements("hideif", condition::class);
});
