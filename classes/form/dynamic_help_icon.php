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

use coding_exception;
use core\output\named_templatable;
use renderable;
use renderer_base;
use stdClass;

/**
 * Like {@see \help_icon}, but with text provided as properties instead of requiring the use of lang strings.
 *
 * Uses the same template as the original {@see \help_icon}. Render using {@see renderer_base::render()} as set an
 * element's `_helpbutton` property to the resulting HTML.
 *
 * @see        \MoodleQuickForm::addHelpButton()
 * @see        form_help
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2023 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dynamic_help_icon implements renderable, named_templatable {

    /** @var string|null */
    private ?string $title;
    /** @var string */
    private string $text;

    /**
     * Initializes a new instance.
     *
     * @param string $text       the main help text shown as a popover when the help icon is clicked.
     * @param string|null $title if given, the icon's alt text (which is shown when hovering over it) will be set to
     *                           "Help with $title" (`helpprefix2`). Otherwise, "Help with this" (`helpwiththis`) is
     *                           used.
     */
    public function __construct(string $text, ?string $title = null) {
        $this->text = $text;
        $this->title = $title;
    }

    /**
     * Get the name of the template to use for this templatable.
     *
     * @param \renderer_base $renderer The renderer requesting the template name
     * @return string
     */
    public function get_template_name(\renderer_base $renderer): string {
        return "core/help_icon";
    }

    /**
     * Function to export the renderer data in a format that is suitable for a
     * mustache template. This means:
     * 1. No complex types - only stdClass, array, int, string, float, bool
     * 2. Any additional info that is required for the template is pre-calculated (e.g. capability checks).
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return stdClass
     * @throws coding_exception
     */
    public function export_for_template(renderer_base $output): object {
        $data = new stdClass();

        $data->text = $this->text;
        $data->ltr = !right_to_left();

        if ($this->title) {
            $data->alt = get_string('helpprefix2', '', trim($this->title, ". \t"));
        } else {
            $data->alt = get_string('helpwiththis');
        }

        return $data;
    }
}
