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

use moodle_exception;

/**
 * Manages DB entries for recently used QuestionPy packages.
 *
 * @package    qtype_questionpy
 * @copyright  2024 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class last_used_service {
    /**
     * Inserts or updates an entry in the database with the current timestamp.
     *
     * @param int $contextid
     * @param int $packageid
     * @throws moodle_exception
     */
    public static function add(int $contextid, int $packageid): void {
        global $DB;

        $fields = ['contextid' => $contextid, 'packageid' => $packageid];
        $id = $DB->get_field('qtype_questionpy_lastused', 'id', $fields);
        $fields['timeused'] = time();
        if ($id) {
            // Entry does already exist -> update time.
            $fields['id'] = $id;
            $DB->update_record('qtype_questionpy_lastused', $fields);
        } else {
            // Create new entry.
            $DB->insert_record('qtype_questionpy_lastused', $fields);
        }
    }

    /**
     * Removes every entry with the given package id.
     *
     * @param int ...$packageids
     * @return void
     * @throws moodle_exception
     */
    public static function remove_by_package(int ...$packageids): void {
        global $DB;
        [$insql, $inparams] = $DB->get_in_or_equal($packageids, SQL_PARAMS_NAMED, "packageids");
        $DB->delete_records_select('qtype_questionpy_lastused', "packageid $insql", $inparams);
    }
}
