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

/**
 * Unit Tests for the \qtype_questionpy\package class.
 *
 * @package    qtype_questionpy
 * @copyright  2022 Alexander Schmitz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \qtype_questionpy\package
 */
class package_test extends \advanced_testcase {


    /**
     * Helper to get an array of packages from a json file.
     *
     * @coversNothing
     * @param string $filename
     * @return package[]
     * @throws \moodle_exception
     */
    private function get_packages_from_file(string $filename): array {
        $myfile = fopen($filename, 'r') || die('Unable to open file!');
        $response = new http_response_container(200, fread($myfile, filesize($filename)));
        fclose($myfile);
        $packages = $response->get_data();

        $result = [];
        foreach ($packages as $package) {
            $result[] = package::from_array($package);
        }
        return $result;
    }

    /**
     * Test if after adding a package to the db, there is indeed one more record present.
     *
     * @covers \qtype_questionpy\package::store_in_db
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_storing_one_package_in_db() {
        global $DB;
        $this->resetAfterTest(true);

        $packages = $this->get_packages_from_file('question/type/questionpy/tests/classes/mock_packages.json');
        $initial = count($DB->get_records('qtype_questionpy_package'));
        if ($packages[0] instanceof package) {
            $packages[0]->store_in_db();
        }
        $final = count($DB->get_records('qtype_questionpy_package'));

        $this->assertEquals(1, $final - $initial);
    }

    /**
     * Adds one Package to db, then retrieves it. Tests if the retrieved package is the same as the original.
     *
     * @covers \qtype_questionpy\package::get_from_db
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function test_storing_and_retrieving_one_package_from_db() {
        global $DB;
        $this->resetAfterTest(true);

        $packages = $this->get_packages_from_file('question/type/questionpy/tests/classes/mock_packages.json');
        $initial = $packages[0];
        if ($initial instanceof package) {
            $initial->store_in_db();
        }
        $final = package::get_from_db($initial->hash);

        // Output data to the stderr (forbidden but good for debugging).
        // fwrite(STDERR, print_r($initial->hash, true));?

        // Work in progress on how to check if two packages are equal.
        // For now checking if the hashes are the same is redundant.
        $this->assertEquals($initial->hash, $final->hash);
    }

}
