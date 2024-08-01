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
use external_single_structure;
use external_value;
use moodle_exception;
use qtype_questionpy\api\api;
use qtype_questionpy\package\package;
use qtype_questionpy\package\package_version;

/**
 * This service loads QuestionPy packages from the application server into the database.
 *
 * Before doing so, it removes previously stored packages from the database.
 *
 * @package    qtype_questionpy
 * @copyright  2023 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class load_packages extends external_api {
    /**
     * Used to verify the parameters passed to the service - this services does not allow any parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * This method is called when the service is executed.
     * It returns the amount of packages and package versions after the insertion.
     *
     * @return array
     * @throws moodle_exception
     */
    public static function execute(): array {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        // Load and store packages from the application server.
        $api = new api();
        $packages = $api->get_packages();
        $incomingpackageids = [];
        foreach ($packages as $package) {
            [$incomingpackageids[], ] = $package->upsert();
        }

        // Remove old packages.
        if (empty($incomingpackageids)) {
            // There are no incoming packages -> remove every package.
            $packages = package::get_records();
            foreach ($packages as $package) {
                $package->delete();
            }
        } else {
            [$sql, $params] = $DB->get_in_or_equal($incomingpackageids, SQL_PARAMS_NAMED, 'packageid', false);
            $removedpackageids = $DB->get_fieldset_select('qtype_questionpy_package', 'id', "id $sql", $params);
            foreach ($removedpackageids as $id) {
                [$package] = package::get_records(['id' => $id]);
                $package->delete();
            }
        }

        $transaction->allow_commit();

        return [
            'packages' => $DB->count_records('qtype_questionpy_package'),
            'versions' => $DB->count_records('qtype_questionpy_pkgversion'),
        ];
    }

    /**
     * This method is used to specify the return value of the service.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'packages' => new external_value(PARAM_INT, 'The total amount of packages in the DB.'),
            'versions' => new external_value(PARAM_INT, 'The total amount of package versions in the DB.'),
        ]);
    }
}
