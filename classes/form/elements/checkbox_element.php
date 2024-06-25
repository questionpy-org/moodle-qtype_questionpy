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

namespace qtype_questionpy\form\elements;

use coding_exception;
use qtype_questionpy\array_converter\array_converter;
use qtype_questionpy\array_converter\converter_config;
use qtype_questionpy\form\context\render_context;
use qtype_questionpy\form\form_conditions;
use qtype_questionpy\form\form_help;

defined('MOODLE_INTERNAL') || die;

/**
 * Element displaying a labelled checkbox.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class checkbox_element extends form_element {
    use form_conditions;
    use form_help;

    /** @var string */
    public string $name;
    /** @var string|null */
    public ?string $leftlabel = null;
    /** @var string|null */
    public ?string $rightlabel = null;
    /** @var bool */
    public bool $required = false;
    /** @var bool */
    public bool $selected = false;

    /**
     * Initializes the element.
     *
     * @param string $name
     * @param string|null $leftlabel
     * @param string|null $rightlabel
     * @param bool $required
     * @param bool $selected
     */
    public function __construct(string $name, ?string $leftlabel = null, ?string $rightlabel = null,
                                bool $required = false, bool $selected = false) {
        $this->name = $name;
        $this->leftlabel = $leftlabel;
        $this->rightlabel = $rightlabel;
        $this->required = $required;
        $this->selected = $selected;
    }

    /**
     * Render this item to the given context.
     *
     * @param render_context $context target context
     * @param int|null $group         passed by {@see checkbox_group_element::render_to} to the checkboxes belonging to
     *                                it
     * @throws coding_exception
     */
    public function render_to(render_context $context, ?int $group = null): void {
        $element = $context->add_element(
            "advcheckbox",
            $this->name,
            $context->contextualize($this->leftlabel),
            $context->contextualize($this->rightlabel),
            $group ? ["group" => $group] : null
        );

        if ($this->selected) {
            $context->set_default($this->name, "1");
        }
        if ($this->required) {
            $context->add_rule($this->name, get_string("required"), "required");
        }

        $this->render_conditions($context, $this->name);
        $this->render_help($element);
    }
}

array_converter::configure(checkbox_element::class, function (converter_config $config) {
    $config->rename("leftlabel", "left_label")
        ->rename("rightlabel", "right_label");
});
