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

namespace qtype_questionpy;

use moodle_exception;
use TypeError;

/**
 * Helper class for communicating to the application server.
 *
 * @package    qtype_questionpy
 * @copyright  2022 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {

    /**
     * Initializes new connector with current server url.
     *
     * @throws moodle_exception
     */
    private static function create_connector(): connector {
        // Get server url.
        $serverurl = get_config('qtype_questionpy', 'server_url');
        return new connector($serverurl);
    }

    /**
     * Retrieves QuestionPy packages from the application server.
     *
     * @throws moodle_exception
     * @return package[]
     */
    public static function get_packages(): array {
        // Retrieve packages from server.
        $connector = self::create_connector();
        $data = $connector->get('/packages');

        $packages = json_decode($data, true);

        $result = [];

        foreach ($packages as $package) {
            try {
                $result[] = package::from_array($package);
            } catch (TypeError $e) {
                // TODO: decide what to do with faulty package.
                continue;
            }
        }

        return $result;
    }

    /**
     * Hello world example.
     *
     * @throws moodle_exception
     * @return string
     */
    public static function get_hello_world(): string {
        $connector = self::create_connector();
        return $connector->get('/helloworld');
    }

}
