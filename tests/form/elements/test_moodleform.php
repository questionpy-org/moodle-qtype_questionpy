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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . "/formslib.php");

use qtype_questionpy\form\renderable;
use qtype_questionpy\form\root_render_context;

/**
 * Stub {@see \moodleform} implementation for tests.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_moodleform extends \moodleform {
    private renderable $element;

    public function __construct(renderable $element) {
        $this->element = $element;
        parent::__construct(null, null, "post", "", ["id" => "my_form"]);
    }

    protected function definition() {
        $context = new root_render_context($this, $this->_form);
        $this->element->render_to($context);
    }
}
