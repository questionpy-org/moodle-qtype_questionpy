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
use qtype_questionpy\package;
use stored_file;

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
     * @return package[]
     * @throws moodle_exception
     */
    public function get_packages(): array {
        // Retrieve packages from server.
        $connector = connector::default();
        $response = $connector->get('/packages');

        $response->assert_2xx();
        $packages = $response->get_data();

        $result = [];

        foreach ($packages as $package) {
            try {
                $result[] = array_converter::from_array(package::class, $package);
            } catch (moodle_exception $e) {
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
     * @return ?package the package with the given hash or null if not found
     * @throws moodle_exception
     */
    public function get_package(string $hash): ?package {
        $connector = connector::default();
        $response = $connector->get("/packages/$hash");

        if ($response->code === 404) {
            return null;
        }
        $response->assert_2xx();

        return array_converter::from_array(package::class, $response->get_data());
    }

    /**
     * Returns the {@see package_api} which contains operations on the given package.
     *
     * {@see package_api} takes care of transparently sending the package when it is not cached by the server,
     *
     * @param string $hash           package hash
     * @param stored_file|null $file package file or null. If this is not provided and the package is not available to
     *                               the server, operations will fail
     * @return package_api
     */
    public function package(string $hash, ?stored_file $file = null): package_api {
        return new package_api($hash, $file);
    }

    /**
     * Hello world example.
     *
     * @return string
     * @throws moodle_exception
     */
    public function get_hello_world(): string {
        $connector = connector::default();
        $response = $connector->get('/helloworld');
        $response->assert_2xx();
        return $response->get_data(false);
    }

    /**
     * Get the Package information from the server.
     *
     * @param stored_file $file the package file
     * @return package
     * @throws moodle_exception
     */
    public function package_extract_info(stored_file $file): package {
        $fs = get_file_storage();
        $filepath = $fs->get_file_system()->get_local_path_from_storedfile($file);

        $connector = connector::default();

        $curlfile = curl_file_create($filepath, "application/zip");
        $data = [
            'package' => $curlfile
        ];

        $response = $connector->post("/package-extract-info", $data);
        $response->assert_2xx();
        return array_converter::from_array(package::class, $response->get_data());
    }
}


