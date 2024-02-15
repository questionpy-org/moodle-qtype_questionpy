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

use context;
use context_user;
use external_api;
use external_function_parameters;
use external_value;
use moodle_exception;
use qtype_questionpy\utils;

/**
 * This service can be used to mark a package as favourite.
 *
 * @package    qtype_questionpy
 * @copyright  2024 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class favourite_package extends external_api {

    /**
     * Used to verify the parameters passed to the service.
     *
     * @return external_function_parameters
     */
    public static function favourite_package_parameters(): external_function_parameters {
        return new external_function_parameters([
            'packageid' => new external_value(PARAM_INT),
            'contextid' => new external_value(PARAM_INT),
        ]);
    }

    /**
     * Checks if a package should be accessible by the user in the given context.
     *
     * @param int $packageid
     * @param context $context
     * @return bool
     * @throws moodle_exception
     */
    private static function package_is_accessible(int $packageid, context $context): bool {
        global $DB, $USER;

        // Path of the current context.
        $paths['path'] = $context->path;

        // If the current context if part of a course, add 'child context path'-pattern.
        $childpathsql = "";
        if ($coursecontext = $context->get_course_context(false)) {
            $childpathsql = 'OR c.path LIKE :childpath';
            $paths['path'] = $coursecontext->path;
            $paths['childpath'] = $coursecontext->path . '/%';
        }

        // Check if at least one package version of the given package is accessible for the user.
        return $DB->record_exists_sql("
            SELECT pv.id
            FROM {qtype_questionpy_pkgversion} pv
            LEFT JOIN {context} c
            ON c.id = pv.contextid
            WHERE (pv.packageid = :packageid) AND (pv.userid IS NULL OR pv.userid = :userid OR c.path LIKE :path $childpathsql)
        ", array_merge($paths, ['packageid' => $packageid, 'userid' => $USER->id]));
    }

    /**
     * This method is called when the service is executed.
     *
     * Only packages with relevant context ids, packages from the current user or packages
     * from the application server can be marked as favourite.
     *
     * @param int $packageid
     * @param int $contextid
     * @return bool
     * @throws moodle_exception
     */
    public static function favourite_package_execute(int $packageid, int $contextid): bool {
        global $USER;

        // Basic parameter validation.
        $params = self::validate_parameters(self::favourite_package_parameters(), [
            'packageid' => $packageid,
            'contextid' => $contextid,
        ]);

        // Validate given context id.
        $context = context::instance_by_id($params['contextid'], IGNORE_MISSING);
        self::validate_context($context);

        // Get user favourite service.
        $usercontext = context_user::instance($USER->id);
        $ufservice = \core_favourites\service_factory::get_service_for_user_context($usercontext);

        // Check if the package is already marked as favourite.
        if ($ufservice->favourite_exists('qtype_questionpy', 'package', $params['packageid'], $usercontext)) {
            return true;
        }

        // Mark package as favourite if it is accessible by the user.
        if ($accessible = self::package_is_accessible($params['packageid'], $context)) {
            $ufservice->create_favourite('qtype_questionpy', 'package', $params['packageid'], $usercontext);
        }

        return $accessible;
    }

    /**
     * This method is used to specify the return value of the service.
     *
     * @return external_value
     */
    public static function favourite_package_returns(): external_value {
        return new external_value(PARAM_BOOL, 'successfully marked package as favourite');
    }

    /**
     * Used to verify the parameters passed to the service.
     *
     * @return external_function_parameters
     */
    public static function unfavourite_package_parameters(): external_function_parameters {
        return new external_function_parameters([
            'packageid' => new external_value(PARAM_INT),
            'contextid' => new external_value(PARAM_INT),
        ]);
    }

    /**
     * This method is called when the service is executed.
     *
     * @param int $packageid
     * @param int $contextid
     * @return bool
     * @throws moodle_exception
     */
    public static function unfavourite_package_execute(int $packageid, int $contextid): bool {
        global $USER;

        // Basic parameter validation.
        $params = self::validate_parameters(self::unfavourite_package_parameters(), [
            'packageid' => $packageid,
            'contextid' => $contextid,
        ]);

        // Validate given context id.
        $context = context::instance_by_id($params['contextid'], IGNORE_MISSING);
        self::validate_context($context);

        // Get user favourite service.
        $usercontext = context_user::instance($USER->id);
        $ufservice = \core_favourites\service_factory::get_service_for_user_context($usercontext);

        // Unmark package as favourite if it is marked as such.
        if ($ufservice->favourite_exists('qtype_questionpy', 'package', $params['packageid'], $usercontext)) {
            $ufservice->delete_favourite('qtype_questionpy', 'package', $params['packageid'], $usercontext);
        }
        return true;
    }

    /**
     * This method is used to specify the return value of the service.
     *
     * @return external_value
     */
    public static function unfavourite_package_returns(): external_value {
        return new external_value(PARAM_BOOL, 'successfully unmarked package as favourite');
    }
}
