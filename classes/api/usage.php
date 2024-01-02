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

use qtype_questionpy\array_converter\array_converter;
use qtype_questionpy\array_converter\converter_config;
use qtype_questionpy\form\conditions\in;

defined('MOODLE_INTERNAL') || die;

/**
 * Server usage.
 *
 * @package    qtype_questionpy
 * @author     Alexander Schmitz
 * @copyright  2023 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class usage {

    /** @var int */
    public int $requestsinprocess;

    /** @var int */
    public int $requestsinqueue;

    /**
     * Initialize a new usage.
     *
     * @param int $requestsinprocess
     * @param int $requestsinqueue
     */
    public function __construct(int $requestsinprocess, int $requestsinqueue) {
        $this->requestsinprocess = $requestsinprocess;
        $this->requestsinqueue = $requestsinqueue;
    }
}

array_converter::configure(usage::class, function (converter_config $config) {
    $config
        ->rename("requestsinprocess", "requests_in_process")
        ->rename("requestsinqueue", "requests_in_queue");
});
