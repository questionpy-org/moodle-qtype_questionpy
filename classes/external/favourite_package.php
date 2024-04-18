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
use context_system;
use context_user;
use external_api;
use external_function_parameters;
use external_value;
use moodle_exception;
use qtype_questionpy\utils;

/**
 * This service can be used to mark or unmark a package as favourite.
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
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'packageid' => new external_value(PARAM_INT),
            'favourite' => new external_value(PARAM_BOOL),
        ]);
    }

    /**
     * This method is called when the service is executed.
     *
     * Only packages with relevant context ids, packages from the current user or packages
     * from the application server can be marked as favourite.
     *
     * @param int $packageid
     * @param bool $favourite
     * @return bool
     * @throws moodle_exception
     */
    public static function execute(int $packageid, bool $favourite): bool {
        global $DB, $USER;

        self::validate_context(context_system::instance());

        // Basic parameter validation.
        $params = self::validate_parameters(self::execute_parameters(), [
            'packageid' => $packageid,
            'favourite' => $favourite,
        ]);

        // Get user favourite service.
        $usercontext = context_user::instance($USER->id);
        $ufservice = \core_favourites\service_factory::get_service_for_user_context($usercontext);

        // Check if the package is marked as favourite.
        if ($ufservice->favourite_exists('qtype_questionpy', 'package', $params['packageid'], $usercontext)) {
            if (!$params['favourite']) {
                $ufservice->delete_favourite('qtype_questionpy', 'package', $params['packageid'], $usercontext);
            }
            // The package was either already marked as favourite or the package was unmarked successfully.
            return true;
        }

        // Package is not marked as favourite.
        if (!$params['favourite']) {
            // There is no package to be unmarked.
            return true;
        }

        // Mark package as favourite if it is accessible by the user.
        if ($exists = $DB->record_exists('qtype_questionpy_pkgversion', ['packageid' => $params['packageid']])) {
            $ufservice->create_favourite('qtype_questionpy', 'package', $params['packageid'], $usercontext);
        }
        return $exists;
    }

    /**
     * This method is used to specify the return value of the service.
     *
     * @return external_value
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_BOOL, 'successfully un-/marked package as favourite');
    }
}
