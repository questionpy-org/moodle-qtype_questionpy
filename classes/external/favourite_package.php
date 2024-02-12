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
        global $DB, $USER;

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

        // Get relevant context ids.
        $contextids = utils::get_relevant_context_ids($context);
        [$insql, $inparams] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'contextid');

        // Check if package can be marked as favourite.
        $applicable = $DB->record_exists_sql("
            SELECT id
            FROM {qtype_questionpy_pkgversion}
            WHERE (packageid = :packageid) AND (userid IS NULL OR userid = :userid OR contextid {$insql})
        ", array_merge(['packageid' => $params['packageid'], 'userid' => $USER->id], $inparams));

        // Favourite package if it is applicable.
        if ($applicable) {
            try {
                $ufservice->create_favourite('qtype_questionpy', 'package', $params['packageid'], $usercontext);
            } catch (moodle_exception $exception) {
                return false;
            }
        }

         return $applicable;
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

        // Check if the package is even marked as a favourite.
        if (!$ufservice->favourite_exists('qtype_questionpy', 'package', $params['packageid'], $usercontext)) {
            return true;
        }

        // Unfavourite package if possible.
        try {
            $ufservice->delete_favourite('qtype_questionpy', 'package', $params['packageid'], $usercontext);
        } catch (moodle_exception $exception) {
            return false;
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
