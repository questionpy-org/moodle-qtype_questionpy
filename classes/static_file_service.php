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
use dml_exception;
use invalid_dataroot_permissions;
use moodle_url;
use qtype_questionpy\local\api\api;
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
     * @param int $contextid
     * @return array{ 0: string, 1: string }|null array of temporary file path and mime type or null of the file wasn't
     *                                            found
     * @throws coding_exception
     * @throws dml_exception
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
     * Converts a QPy-URI such as `qpy://static/acme/great_package/css/styles.css` to a functioning pluginfile URL.
     *
     * @param string $qpyurl
     * @param qtype_questionpy_question $question
     * @return moodle_url|false
     * @throws coding_exception
     */
    public static function reify_qpy_url(string $qpyurl, qtype_questionpy_question $question): string|false {
        $result = preg_match(constants::QPY_URL_PATTERN, $qpyurl, $matches);

        if ($result === 0) {
            return false;
        }
        if ($result === false) {
            throw new coding_exception('Regex error while parsing QPy URL');
        }

        $path = $matches[1];
        return moodle_url::make_pluginfile_url(
            $question->contextid,
            'qtype_questionpy',
            'static',
            null,
            '/' . $question->packagehash . dirname($path) . '/',
            basename($path)
        )->out();
    }
}
