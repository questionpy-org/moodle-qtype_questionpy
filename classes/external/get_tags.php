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

namespace qtype_questionpy\external;

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once($CFG->libdir . "/externallib.php");

use context_system;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use moodle_exception;

/**
 * This service returns localised package tags.
 *
 * @package    qtype_questionpy
 * @copyright  2024 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_tags extends external_api {
    /**
     * Used to verify the parameters passed to the service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'query' => new external_value(PARAM_TEXT, 'The search query', VALUE_REQUIRED),
        ]);
    }

    /**
     * Returns package tags.
     *
     * @param string $query
     * @throws moodle_exception
     */
    public static function execute($query) {
        global $DB;

        $params = external_api::validate_parameters(self::execute_parameters(), [
            'query' => $query,
        ]);

        // Validate context.
        $context = context_system::instance();
        self::validate_context($context);

        $wheresql = '';
        $casesql = '';
        $sqlparams = [];

        $query = trim($params['query']);
        if ($query !== '') {
            $query = $DB->sql_like_escape($query);

            $likesql = $DB->sql_like('t.tag', ':tag', false);
            $sqlparams['tag'] = "%{$query}%";
            $wheresql = "WHERE {$likesql}";

            // Place tags starting with the query before the other results.
            $likestartsql = $DB->sql_like('t.tag', ':starttag', false);
            $sqlparams['starttag'] = "{$query}%";
            $casesql = "
                CASE
                    WHEN {$likestartsql} THEN 0
                    ELSE 1
                END,
            ";
        }

        // TODO: localize tags.
        return $DB->get_records_sql("
            SELECT t.*, COUNT(pt.tagid) AS usage_count
            FROM {qtype_questionpy_tag} t
            LEFT JOIN {qtype_questionpy_pkgtag} pt ON t.id = pt.tagid
            $wheresql
            GROUP BY t.id
            ORDER BY
                $casesql
                usage_count DESC,
                t.tag
        ", $sqlparams, 0, 25);
    }

    /**
     * Parameter description.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure(
            [
                'id' => new external_value(PARAM_INT, 'ID of the tag'),
                'tag' => new external_value(PARAM_TEXT, 'The tag'),
                'usage_count' => new external_value(PARAM_INT, 'Number of usages'),
            ]
        ));
    }
}
