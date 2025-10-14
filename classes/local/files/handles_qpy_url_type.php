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

namespace qtype_questionpy\local\files;

use qtype_questionpy_question;

/**
 * A class implementing this interface supports
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2025 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface handles_qpy_url_type {
    /**
     * Converts a QPy-URL to a functioning pluginfile URL.
     *
     * This method isn't passed the entire URL, but everything after the `qpy://<type>` prefix. The slash between type and path
     * isn't included in `$path`. See also {@see qpy_url_resolver::QPY_URL_PATTERN}.
     *
     * @param string $path
     * @param qtype_questionpy_question $question
     * @return string
     */
    public function resolve_qpy_url(string $path, qtype_questionpy_question $question): string;

    /**
     * Serves a plugin file belonging to this implementation.
     *
     * The arguments are passed directly from {@see qtype_questionpy_pluginfile}.
     *
     * This method never returns.
     *
     * @param object $context
     * @param array $args
     * @return never
     */
    public function serve_pluginfile(object $context, array $args): never;
}
