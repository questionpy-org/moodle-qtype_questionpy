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

namespace qtype_questionpy\local\files;

use coding_exception;
use core\exception\moodle_exception;
use dml_exception;
use GuzzleHttp\Exception\GuzzleException;
use invalid_dataroot_permissions;
use moodle_url;
use qtype_questionpy\constants;
use qtype_questionpy\exception\request_error;
use qtype_questionpy\local\api\api;
use qtype_questionpy\package_file_service;
use qtype_questionpy_question;

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
class static_file_service implements handles_qpy_url_type {
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
     * Downloads the given static file to a temporary path and returns path and mime type.
     *
     * TODO: Cache the file.
     *
     * @param string $packagehash
     * @param string $namespace
     * @param string $shortname
     * @param string $path
     * @param int $contextid
     * @return array{ 0: string, 1: string }|null array of temporary file path and mime type or null of the file wasn't
     *                                            found
     * @throws GuzzleException
     * @throws coding_exception
     * @throws dml_exception
     * @throws request_error
     * @throws invalid_dataroot_permissions
     */
    public function download_public_static_file(string $packagehash, string $namespace, string $shortname, string $path,
                                                int $contextid): ?array {
        $path = ltrim($path, '/');
        $packagefileiflocal = $this->packagefileservice->get_file_by_package_hash($packagehash, $contextid);

        $temppath = make_request_directory() . "/$packagehash/$namespace/$shortname/$path";
        make_writable_directory(dirname($temppath));

        $mimetype = $this->api->package($packagehash, $packagefileiflocal)
            ->download_static_file($namespace, $shortname, 'static', $path, $temppath);

        if (is_null($mimetype)) {
            return null;
        }

        return [$temppath, $mimetype];
    }

    /**
     * Converts a QPy-URL to a functioning pluginfile URL.
     *
     * This method isn't passed the entire URL, but everything after the `qpy://<type>/` prefix. The slash between type and path
     * isn't included in `$path`. See also {@see qpy_url_resolver::QPY_URL_PATTERN}.
     *
     * @param string $path
     * @param qtype_questionpy_question $question
     * @return string
     */
    public function resolve_qpy_url(string $path, qtype_questionpy_question $question): string {
        return moodle_url::make_pluginfile_url(
            $question->contextid,
            'qtype_questionpy',
            constants::FILEAREA_STATIC,
            null,
            '/' . $question->packagehash . dirname($path) . '/',
            basename($path)
        )->out();
    }

    /**
     * Serves a plugin file belonging to this implementation.
     *
     * The arguments are passed directly from {@see qtype_questionpy_pluginfile}.
     *
     * This method never returns.
     *
     * @param object $context
     * @param array $args
     * @return never
     * @throws GuzzleException
     * @throws moodle_exception
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_dataroot_permissions
     * @throws request_error
     */
    public function serve_pluginfile(object $context, array $args): never {
        [$packagehash, $namespace, $shortname] = $args;
        $path = implode('/', array_slice($args, 3));

        [$filepath, $mimetype] = $this->download_public_static_file(
            $packagehash,
            $namespace,
            $shortname,
            $path,
            $context->id,
        );
        if (is_null($filepath)) {
            send_file_not_found();
        }

        /* Set a lifetime of 1 year, i.e. effectively never expire. Since the package hash is part of the URL, cache busting
           is automatic. */
        send_file(
            $filepath,
            basename($path),
            lifetime: 31536000,
            mimetype: $mimetype,
            options: ['immutable' => true, 'cacheability' => 'public']
        );
        die();
    }
}
