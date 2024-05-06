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

namespace qtype_questionpy\api;

use moodle_exception;
use qtype_questionpy\array_converter\array_converter;
use qtype_questionpy\package\package_raw;
use stored_file;
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
     * Retrieves QuestionPy packages from the application server.
     *
     * @return package_raw[]
     * @throws moodle_exception
     */
    public function get_packages(): array {
        $connector = connector::default();
        $response = $connector->get('/packages');
        $response->assert_2xx();
        $packages = $response->get_data();

        $result = [];
        foreach ($packages as $package) {
            try {
                $result[] = array_converter::from_array(package_raw::class, $package);
            } catch (TypeError $e) {
                // TODO: decide what to do with faulty package.
                debugging($e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Retrieves the package with the given hash, returns null if not found.
     *
     * @param string $hash the hash of the package to get
     * @return ?package_raw the package with the given hash or null if not found
     * @throws moodle_exception
     */
    public function get_package(string $hash): ?package_raw {
        $connector = connector::default();
        $response = $connector->get("/packages/$hash");

        if ($response->code === 404) {
            return null;
        }
        $response->assert_2xx();
        return array_converter::from_array(package_raw::class, $response->get_data());
    }

    /**
     * Returns the {@see package_api} of a specific package.
     *
     * @param string $hash the hash of the package
     * @param stored_file|null $file the package file if any
     * @return package_api
     */
    public function package(string $hash, ?stored_file $file = null): package_api {
        return new package_api($hash, $file);
    }

    /**
     * Get a {@see package_raw} from a file.
     *
     * @param stored_file $file
     * @return package_raw
     * @throws moodle_exception
     */
    public static function extract_package_info(stored_file $file): package_raw {
        $connector = connector::default();

        $filestorage = get_file_storage();
        $filepath = $filestorage->get_file_system()->get_local_path_from_storedfile($file, true);

        $data = [
            'package' => curl_file_create($filepath),
        ];

        $response = $connector->post("/package-extract-info", $data);
        $response->assert_2xx();
        return array_converter::from_array(package_raw::class, $response->get_data());
    }
    /**
     * Get the status and information from the server.
     *
     * @return status
     * @throws moodle_exception
     */
    public static function get_server_status(): status {
        $connector = connector::default();
        $response = $connector->get("/status");
        $response->assert_2xx();
        return array_converter::from_array(status::class, $response->get_data());
    }
}
