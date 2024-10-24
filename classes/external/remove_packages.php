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
use qtype_questionpy\package\package;

/**
 * This service removes every package from the database.
 *
 * @package    qtype_questionpy
 * @copyright  2023 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class remove_packages extends external_api {
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
     * It returns the amount of packages and package versions after the removal.
     *
     * @return array
     * @throws moodle_exception
     */
    public static function execute(): array {
        global $DB;

        package::delete_all();

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
