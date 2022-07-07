<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * QuestionPy renderer class
 *
 * @package    qtype_questionpy
 * @copyright  2022 Martin Gauk, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Generates the output for QuestionPy questions.
 *
 * @copyright  2022 Martin Gauk, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_questionpy_renderer extends qtype_renderer {
    /**
     * Generate the display of the formulation part of the question. This is the
     * area that contains the quetsion text, and the controls for students to
     * input their answers. Some question types also embed bits of feedback, for
     * example ticks and crosses, in this area.
     *
     * @param question_attempt $qa the question attempt to display.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return string HTML fragment.
     */
    public function formulation_and_controls(question_attempt $qa, question_display_options $options) {
        $url = get_config('qtype_questionpy', 'server_url', );
        $timeout = get_config('qtype_questionpy', 'server_timeout');

        if (!$url || !$timeout) {
            return 'parameter not found in config';
        }

        $curlhandle = curl_init();
        curl_setopt($curlhandle, CURLOPT_URL, $url);
        curl_setopt($curlhandle, CURLOPT_VERBOSE, false);
        curl_setopt($curlhandle, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($curlhandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlhandle, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($curlhandle, CURLOPT_TIMEOUT, 30);

        $result = curl_exec($curlhandle);
        $statuscode = curl_getinfo($curlhandle, CURLINFO_RESPONSE_CODE);
        curl_close($curlhandle);

        if (!$result || $statuscode != 200) {
            return 'connection error';
        }

        return $result;
    }

    /**
     * Generate the specific feedback. This is feedback that varies according to
     * the response the student gave.
     *
     * @param question_attempt $qa the question attempt to display.
     * @return string HTML fragment.
     */
    public function specific_feedback(question_attempt $qa) {
        return '';
    }

    /**
     * Create an automatic description of the correct response to this question.
     * Not all question types can do this. If it is not possible, this method
     * should just return an empty string.
     *
     * @param question_attempt $qa the question attempt to display.
     * @return string HTML fragment.
     */
    public function correct_response(question_attempt $qa) {
        return '';
    }
}
