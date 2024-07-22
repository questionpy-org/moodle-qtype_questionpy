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

/**
 * Unit tests for the get_tags function.
 *
 * @package    qtype_questionpy
 * @copyright  2024 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later */

namespace qtype_questionpy\external;

use external_api;
use moodle_exception;
use function qtype_questionpy\package_provider;

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__DIR__) . '/data_provider.php');

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for {@see get_tags}.
 *
 * @runTestsInSeparateProcesses
 *
 * @package    qtype_questionpy
 * @author     Jan Britz
 * @copyright  2024 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_tags_test extends \externallib_advanced_testcase {
    /**
     * Test that the user needs to be logged in.
     *
     * @covers \qtype_questionpy\external\get_tags::execute
     * @throws moodle_exception
     */
    public function test_get_tags_needs_user_to_be_logged_in(): void {
        $this->expectException(\require_login_exception::class);
        get_tags::execute('');
    }

    /**
     * Provider for {@see test}
     *
     * @return array[]
     */
    public static function query_provider(): array {
        return [
            'No query and no tags' => [
                '',
                [],
                [],
            ],
            'No query and tags' => [
                '',
                [['a'], ['b'], ['c']],
                ['a', 'b', 'c'],
            ],
            'No query and duplicate tags' => [
                '',
                [['a'], ['a', 'b']],
                ['a', 'b'],
            ],
            'No query and sorting by usage count' => [
                '',
                [['a'], ['b'], ['b']],
                ['b', 'a'],
            ],
            'No query, sorting by usage count and then alphabetically' => [
                '',
                [['a'], ['c'], ['b', 'z'], ['z']],
                ['z', 'a', 'b', 'c'],
            ],
            'Tags which start with query at the front' => [
                'tag',
                [['a_tag'], ['tag', 'a_tag']],
                ['tag', 'a_tag'],
            ],
            'Tags which start with query at the front and sorted alphabetically' => [
                'tag',
                [['a_tag'], ['tag_z', 'tag_a'], ['tag_a', 'tag_z']],
                ['tag_a', 'tag_z', 'a_tag'],
            ],
            'Tags which start with query at the front and sorted by usage count then alphabetically' => [
                'tag',
                [['a_tag'], ['tag_z', 'tag_a'], ['tag_a', 'tag_z'], ['tag_z']],
                ['tag_z', 'tag_a', 'a_tag'],
            ],
            'No matching tag' => [
                'a',
                [['b'], ['c']],
                [],
            ],
            'Some matching tags' => [
                'a',
                [['b'], ['a'], ['c']],
                ['a'],
            ],
            // TODO: test for sql injection.
        ];
    }

    /**
     * Test that service orders results by usage count.
     *
     * @param string $query
     * @param array $packagetags Each list represents a package and each entry a tag.
     * @param array $expected Expected tags.
     * @throws moodle_exception
     * @dataProvider query_provider
     * @covers \qtype_questionpy\external\get_tags::execute
     */
    public function test_get_tags(string $query, array $packagetags, array $expected): void {
        $this->resetAfterTest();
        $this->setGuestUser();

        foreach ($packagetags as $i => $tags) {
            package_provider(['namespace' => "ns$i", 'tags' => $tags])->store();
        }
        $tags = get_tags::execute($query);
        $tags = external_api::clean_returnvalue(get_tags::execute_returns(), $tags);

        $this->assertEquals($expected, array_column($tags, 'tag'));
        $counts = array_count_values(array_merge(...$packagetags));
        foreach ($tags as $tag) {
            $this->assertEquals($counts[$tag['tag']], $tag['usage_count'], 'Usage count does not match.');
        }
    }
}
