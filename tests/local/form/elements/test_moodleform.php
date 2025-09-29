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

namespace qtype_questionpy\local\form\elements;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

use qtype_questionpy\local\form\context\root_render_context;
use qtype_questionpy\local\form\qpy_renderable;
use stdClass;

/**
 * Stub {@see \moodleform} implementation for tests.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2022 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_moodleform extends \moodleform {
    /**
     * @var qpy_renderable element to render
     */
    private qpy_renderable $element;

    /**
     * Initializes a new {@see test_moodleform}.
     *
     * @param qpy_renderable $element element to render
     */
    public function __construct(qpy_renderable $element) {
        $this->element = $element;
        parent::__construct(null, null, 'post', '', ['id' => 'my_form']);
    }

    /**
     * Renders {@see $element}.
     *
     * Output can be retrieved using {@see render}, which calls this method.
     */
    protected function definition() {
        global $PAGE;
        $question = (object) [
            'contextid' => $PAGE->context->id,
        ];
        $context = new root_render_context($this, $this->_form, $question, 'qpy_form', []);
        $context->uuidgen = fn() => '24daab97-7eeb-422d-a2f3-f4e770fb11f6';
        $this->element->render_to($context);
    }
}
