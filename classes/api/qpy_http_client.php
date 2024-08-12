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

use core\http_client;
use dml_exception;
use GuzzleHttp\HandlerStack;

/**
 * Guzzle http client configured with Moodle's standards ({@see http_client}) and QPy-specific ones.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2024 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qpy_http_client extends http_client {
    /**
     * Initializes a new client.
     *
     * @param array $config Guzzle config options. Mock handlers and history middleware can be added here, see
     *                      {@link https://docs.guzzlephp.org/en/stable/testing.html#mock-handler}.
     * @throws dml_exception
     */
    public function __construct(array $config = []) {
        $config["base_uri"] = rtrim(get_config('qtype_questionpy', 'server_url'), "/") . "/";
        $config["timeout"] = get_config('qtype_questionpy', 'server_timeout');
        parent::__construct($config);
    }

    /**
     * Get the handler stack according to the settings/options from client.
     *
     * @param array $settings The settings or options from client.
     * @return HandlerStack
     */
    protected function get_handlers(array $settings): HandlerStack {
        $handlerstack = parent::get_handlers($settings);
        /* This checks requests against Moodle's curlsecurityblockedhosts, which we don't want, since admins would need
           to ensure their QPy server isn't in this list otherwise. There may be ways to granularly allow the
           server_url, but this will do for now. */
        $handlerstack->remove("moodle_check_initial_request");
        return $handlerstack;
    }
}
