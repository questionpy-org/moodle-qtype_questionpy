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
 * QuestionPy external functions.
 *
 * @package    qtype_questionpy
 * @copyright  2023 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'qtype_questionpy_load_packages' => [
        'classname' => 'qtype_questionpy\external\load_packages',
        'description' => 'Loads packages from the application server.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'qtype_questionpy_remove_packages' => [
        'classname' => 'qtype_questionpy\external\remove_packages',
        'description' => 'Removes packages from the database that were not uploaded by a trainer.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'qtype_questionpy_search_packages' => [
        'classname' => 'qtype_questionpy\external\search_packages',
        'description' => 'Search and filter for packages in the database.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'qtype_questionpy_favourite_package' => [
        'classname' => 'qtype_questionpy\external\favourite_package',
        'methodname' => 'favourite_package_execute',
        'description' => 'Favourite a package.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'qtype_questionpy_unfavourite_package' => [
        'classname' => 'qtype_questionpy\external\favourite_package',
        'methodname' => 'unfavourite_package_execute',
        'description' => 'Unfavourite a package.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
];
