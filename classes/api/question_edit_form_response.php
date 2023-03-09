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

namespace qtype_questionpy\api;

use qtype_questionpy\array_converter\array_converter;
use qtype_questionpy\array_converter\converter_config;
use qtype_questionpy\form\qpy_form;

defined('MOODLE_INTERNAL') || die;

/**
 * Response from the server to a request for the question edit form.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2023 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_edit_form_response {
    /** @var qpy_form */
    public qpy_form $definition;

    /** @var array */
    public array $formdata;

    /**
     * Initialize a new question response.
     *
     * @param qpy_form $definition form definition
     * @param array $formdata      current values of the form elements
     */
    public function __construct(qpy_form $definition, array $formdata) {
        $this->definition = $definition;
        $this->formdata = $formdata;
    }
}

array_converter::configure(question_edit_form_response::class, function (converter_config $config) {
    $config
        ->rename("formdata", "form_data");
});


