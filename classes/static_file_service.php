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

namespace qtype_questionpy;

use coding_exception;
use context_system;
use dml_exception;
use invalid_dataroot_permissions;
use qtype_questionpy\api\api;

/**
 * Handles retrieval, access control and caching of static package files.
 *
 * May also handle non-static attempt and scoring files files in the future, we'll see.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2024 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class static_file_service {
    /** @var api */
    private readonly api $api;

    /** @var package_file_service */
    private readonly package_file_service $packagefileservice;

    /**
     * Trivial constructor.
     * @param api $api
     * @param package_file_service $packagefileservice
     */
    public function __construct(api $api, package_file_service $packagefileservice) {
        $this->api = $api;
        $this->packagefileservice = $packagefileservice;
    }

    /**
     * Gets and serves the given static file from the QPy server and dies afterwards.
     *
     * TODO: Cache the file.
     *
     * @param string $packagehash
     * @param string $namespace
     * @param string $shortname
     * @param string $path
     * @return array{ 0: string, 1: string }|null array of temporary file path and mime type or null of the file wasn't
     *                                            found
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_dataroot_permissions
     */
    public function download_public_static_file(string $packagehash, string $namespace, string $shortname, string $path): ?array {
        $path = ltrim($path, "/");
        $packagefileiflocal = $this->packagefileservice->get_file_by_package_hash($packagehash, context_system::instance()->id);

        $temppath = make_request_directory() . "/$packagehash/$namespace/$shortname/$path";
        make_writable_directory(dirname($temppath));

        $mimetype = $this->api->package($packagehash, $packagefileiflocal)
            ->download_static_file($namespace, $shortname, "static", $path, $temppath);

        if (is_null($mimetype)) {
            return null;
        }

        return [$temppath, $mimetype];
    }
}
