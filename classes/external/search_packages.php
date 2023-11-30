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

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use moodle_exception;
use qtype_questionpy\localizer;

/**
 * This service can be used to search and filter for packages in the database.
 *
 * @package    qtype_questionpy
 * @copyright  2023 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_packages extends external_api {

    /** @var string[] Valid categories. */
    const CATEGORIES = ['all', 'recentlyused', 'favourites', 'mine'];
    /** @var string[] Valid kinds of sorting. */
    const SORT = ['alpha', 'date'];
    /** @var string[] Valid sorting direction. */
    const ORDER = ['asc', 'desc'];

    /**
     * Used to verify the parameters passed to the service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'query' => new external_value(PARAM_RAW, 'Search query'),
            'tags' => new external_multiple_structure(new external_value(PARAM_INT), 'Package tag ids'),
            'category' => new external_value(PARAM_ALPHA),
            'sort' => new external_value(PARAM_ALPHA),
            'order' => new external_value(PARAM_ALPHA),
            'limit' => new external_value(PARAM_INT),
            'page' => new external_value(PARAM_INT),
            'contextid' => new external_value(PARAM_INT),
        ]);
    }

    /**
     * In addition to {@see self::validate_parameters} this method validates the values of the parameters and therefore
     * should be called after {@see self::validate_parameters}.
     *
     * @param mixed $params The parameters.
     * @return void
     * @throws invalid_parameter_exception
     */
    private static function validate_parameter_values($params): void {
        if (!in_array($params['category'], self::CATEGORIES)) {
            $validparameters = implode(', ', self::CATEGORIES);
            throw new invalid_parameter_exception("Unknown category. Valid parameters are: $validparameters");
        }
        if ($params['category'] !== 'all') {
            // TODO: change if more categories are available.
            throw new invalid_parameter_exception("Only the category 'all' is currently supported.");
        }
        if (!in_array($params['sort'], self::SORT)) {
            $validparameters = implode(', ', self::SORT);
            throw new invalid_parameter_exception("Unknown sort. Valid parameters are: $validparameters");
        }
        if (!in_array($params['order'], self::ORDER)) {
            $validparameters = implode(', ', self::ORDER);
            throw new invalid_parameter_exception("Unknown order. Valid parameters are: $validparameters");
        }
        if ($params['limit'] <= 0 || $params['limit'] > 100) {
            throw new invalid_parameter_exception("The limit can only be a value from 1 to 100.");
        }
        if ($params['page'] < 0) {
            throw new invalid_parameter_exception("The page can not be negative.");
        }
    }

    /**
     * Constructs sql fragment for performing safe text search on given fields.
     *
     * Creates a DNF.
     *
     * @param string[] $fieldnames The names of the table columns.
     * @param string $query The query containing the words to be looked for.
     * @return array A list containing the constructed sql fragment and an array of parameters.
     */
    private static function create_text_search_sql(array $fieldnames, string $query): array {
        global $DB;

        $query = trim($query);

        if ($query == '' || strlen($query) == 1) {
            return ['', []];
        }

        $segments = [];
        $params = [];
        $words = explode(' ', $query);

        foreach ($words as $i => $word) {
            $word = trim($word);
            // Discard words of length 1.
            if (strlen($word) <= 1) {
                continue;
            }

            $escapedword = $DB->sql_like_escape($word);
            foreach ($fieldnames as $j => $fieldname) {
                // Create case-insensitive sql like fragment.
                $param = "word{$i}field$j";
                $params[$param] = '%' . $escapedword . '%';
                $segments[$fieldname][] = $DB->sql_like($fieldname, ":$param", false);
            }
        }

        $parts = [];
        foreach ($fieldnames as $fieldname) {
            $parts[] = '(' . implode(' AND ', $segments[$fieldname]) . ')';
        }
        $sql = implode(' OR ', $parts);
        return [$sql, $params];
    }

    /**
     * Retrieves the tags and package versions of multiple packages given their ids.
     *
     * @param int[] $ids Package ids.
     * @return array[] A list containing the tags and versions.
     * @throws moodle_exception
     */
    private static function get_tags_and_versions(array $ids): array {
        global $DB, $USER;

        if (empty($ids)) {
            return [[], []];
        }

        // Create sql fragment and parameters.
        [$insql, $inparams] = $DB->get_in_or_equal($ids);

        // Get tags.
        $tagsraw = $DB->get_records_sql("
            SELECT id, packageid, tag
            FROM {qtype_questionpy_tags}
            WHERE packageid {$insql}
        ", $inparams);

        $tags = [];
        foreach ($tagsraw as $tag) {
            $tags[$tag->packageid][] = [
                'id' => $tag->id,
                'tag' => $tag->tag,
            ];
        }

        // Get package versions.
        $versionsraw = $DB->get_records_sql("
            SELECT id, packageid, hash, version, userid
            FROM {qtype_questionpy_pkgversion}
            WHERE packageid {$insql}
        ", $inparams);

        $versions = [];
        foreach ($versionsraw as $version) {
            $versions[$version->packageid][] = [
                'id' => $version->id,
                'hash' => $version->hash,
                'version' => $version->version,
                'ismine' => $version->userid === $USER->id,
            ];
        }

        return [$tags, $versions];
    }

    /**
     * Constructs 'ORDER BY' sql fragment.
     *
     * @param string $sort Kind of ordering {@see search_packages::SORT}.
     * @param string $order Direction of the ordering {@see search_packages::ORDER}.
     * @return string The sql fragment.
     */
    private static function create_order_by_sql(string $sort, string $order): string {
        $sql = 'ORDER BY';
        if ($sort === 'alpha') {
            $sql .= ' name';
        } else {
            $sql .= ' timecreated';
        }
        return $sql . ' ' . $order;
    }

    /**
     * Constructs sql fragment for filtering by tags.
     *
     * TODO: change the logic when tags are localized.
     *
     * @param array $tags
     * @return array A list containing the constructed sql fragment and an array of parameters.
     */
    private static function create_tag_filter_sql(array $tags): array {
        $joinsql = '';
        $params = [];
        foreach ($tags as $i => $tag) {
            $jointagsparam = "tag$i";
            $params[$jointagsparam] = $tag;
            $joinsql .= "
                JOIN {qtype_questionpy_tags} t$i
                ON t$i.packageid = p.id AND t$i.id = :$jointagsparam
            ";
        }
        return [$joinsql, $params];
    }

    /**
     * Constructs sql fragment used to retrieve the best name and description translation.
     *
     * @return array A list containing the sql main fragment, parameter, coalesce sql fragment for name and description.
     */
    private static function create_best_language_sql(): array {
        $languages = localizer::get_preferred_languages();

        $joinlangssql = '';
        $joinlangsparams = [];

        $coalescename = [];
        $coalescedesc = [];
        foreach ($languages as $i => $language) {
            $joinparam = "language$i";
            $joinlangsparams[$joinparam] = $language;
            $joinlangssql .= "
                LEFT JOIN {qtype_questionpy_language} l$i
                ON l$i.packageid = p.id AND l$i.language = :$joinparam
            ";
            $coalescename[] = "l$i.name";
            $coalescedesc[] = "l$i.description";
        }
        $coalescenamesql = 'COALESCE(' . implode(',', $coalescename) . ')';
        $coalescedescsql = 'COALESCE(' . implode(',', $coalescedesc) . ')';

        return [$joinlangssql, $joinlangsparams, $coalescenamesql, $coalescedescsql];
    }

    /**
     * Returns a valid WHERE-clause if a condition is given else ''.
     *
     * @param string $condition
     * @return string
     */
    private static function sql_where(string $condition): string {
        if (trim($condition) === '') {
            return '';
        }
        return "WHERE $condition";
    }

    /**
     * This method is called when the service is executed.
     *
     * @param string $query
     * @param array $tags
     * @param string $category
     * @param string $sort
     * @param string $order
     * @param int $limit
     * @param int $page
     * @param int $contextid
     * @return array
     * @throws moodle_exception
     */
    public static function execute(string $query, array $tags, string $category, string $sort, string $order,
                                   int $limit, int $page, int $contextid): array {
        global $DB;

        // Basic parameter validation.
        $params = self::validate_parameters(self::execute_parameters(), [
            'query' => $query,
            'tags' => $tags,
            'category' => $category,
            'sort' => $sort,
            'order' => $order,
            'limit' => $limit,
            'page' => $page,
            'contextid' => $contextid,
        ]);

        // Validate given context id.
        $context = \context::instance_by_id($contextid, IGNORE_MISSING);
        self::validate_context($context);

        // In addition to the basic parameter validation we also want to validate the values.
        self::validate_parameter_values($params);

        // Create the 'ORDER BY' sql fragment.
        $orderbysql = self::create_order_by_sql($params['sort'], $params['order']);

        // Search only in best package translation.
        [$joinlangssql, $joinlangsparams, $coalescenamesql, $coalescedescsql] = self::create_best_language_sql();

        // Get only packages with specified tags.
        [$jointagssql, $jointagsparams] = self::create_tag_filter_sql($params['tags']);

        // Prepare query.
        [$likesql, $likeparams] = self::create_text_search_sql(['name', 'description'], $params['query']);
        $wherelikesql = self::sql_where($likesql);

        // Assemble the final sql.
        // TODO: assemble the sql in a separate function and take the categories into account.
        $finalsql = "
            SELECT id, short_name, namespace, author, url, icon, license, name, description
            FROM (
                SELECT p.id, p.shortname AS short_name, p.namespace, p.author, p.url, p.icon, p.license,
                       $coalescenamesql AS name, $coalescedescsql AS description, p.timecreated
                FROM {qtype_questionpy_package} p
                $joinlangssql
                $jointagssql
            ) subq
            $wherelikesql
            $orderbysql
        ";

        // Merge every parameter array.
        $finalparams = array_merge($joinlangsparams, $jointagsparams, $likeparams);

        // Execute the assembled sql query and set the limit and offset.
        $packagesraw = $DB->get_records_sql($finalsql, $finalparams, $params['limit'] * $params['page'], $params['limit']);

        // Get package tag and versions.
        $ids = array_column($packagesraw, 'id');
        [$tags, $versions] = self::get_tags_and_versions($ids);

        // Set the tags and package versions.
        foreach ($packagesraw as $package) {
            $package->tags = $tags[$package->id] ?? [];
            // There should always be at least one version of a package therefore this check is obsolete.
            $package->versions = $versions[$package->id] ?? [];
            // TODO: Favourites API.
            $package->isfavourite = false;
        }

        // Calculate total packages (without pagination).
        $totalpackagessql = "
            SELECT COUNT('x')
            FROM ($finalsql) subqcount
        ";
        $totalpackagessql = $DB->count_records_sql($totalpackagessql, $finalparams);

        return ['packages' => array_values($packagesraw), 'count' => count($packagesraw), 'total' => $totalpackagessql];
    }


    /**
     * This method is used to specify the return value of the service.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'packages' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT),
                'short_name' => new external_value(PARAM_ALPHANUMEXT),
                'namespace' => new external_value(PARAM_ALPHANUMEXT),
                'versions' => new external_multiple_structure(new external_single_structure([
                    'id' => new external_value(PARAM_INT),
                    'hash' => new external_value(PARAM_ALPHANUM),
                    'version' => new external_value(PARAM_TEXT),
                    'ismine' => new external_value(PARAM_BOOL),
                ])),
                'author' => new external_value(PARAM_RAW),
                'name' => new external_value(PARAM_TEXT),
                'url' => new external_value(PARAM_URL, '', VALUE_OPTIONAL),
                'description' => new external_value(PARAM_RAW, '', VALUE_OPTIONAL),
                'icon' => new external_value(PARAM_URL, '', VALUE_OPTIONAL),
                'license' => new external_value(PARAM_TEXT),
                'tags' => new external_multiple_structure(new external_single_structure([
                    'id' => new external_value(PARAM_INT),
                    'tag' => new external_value(PARAM_ALPHANUMEXT),
                ])),
                'isfavourite' => new external_value(PARAM_BOOL),
            ])),
            'count' => new external_value(PARAM_INT),
            'total' => new external_value(PARAM_INT),
        ]);
    }
}
