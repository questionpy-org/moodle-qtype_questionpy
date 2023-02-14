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
 * This file has data provider functions for unit tests.
 *
 * @package    qtype_questionpy
 * @copyright  2022 Alexander Schmitz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_questionpy;

use qtype_questionpy\array_converter\array_converter;

/**
 * Data provider for {@see package}.
 *
 * @return package A sample package for the tests
 */
function package_provider1(): package {
    return array_converter::from_array(package::class, [
        'package_hash' => 'dkZZGAOgHTpBOSZMBGNM',
        'short_name' => 'adAqMNxOZNhuSUWflNui',
        'namespace' => 'default',
        'name' => [
            'en' => 'She piece local.',
            'de' => 'Style important.'
        ],
        'version' => '865.7797993.0--.0',
        'type' => 'questiontype',
        'author' => 'Mario Hunt',
        'url' => 'http://www.kane.com/',
        'languages' => [
            0 => 'en',
            1 => 'de'
        ],
        'description' => [
            'en' => 'en: Activity organization letter. Report alone why center.
                    Real outside glass maintain right hear.
                    Brother develop process work. Build ago north.
                    Develop with defense understand garden recently work.',
            'de' => 'de: Activity few enter medical side position. Safe need no guy price.
                    Source necessary our me series month seven born.
                    Anyone everything interest where accept apply. Expert great significant.'
        ],
        'icon' => 'https://placehold.jp/40e47e/598311/150x150.png',
        'license' => '',
        'tags' => [
            0 => 'fXuprCRqsLnQQYzFZgAt'
        ]
    ]);
}

/**
 * Data provider for {@see package}.
 *
 * @return package Same package as {@see package_provider1} but values in languages array are swapped.
 */
function package_provider2(): package {
    return array_converter::from_array(package::class, [
        'package_hash' => 'dkZZGAOgHTpBOSZMBGNM',
        'short_name' => 'adAqMNxOZNhuSUWflNui',
        'namespace' => 'default',
        'name' => [
            'en' => 'She piece local.',
            'de' => 'Style important.'
        ],
        'version' => '865.7797993.0--.0',
        'type' => 'questiontype',
        'author' => 'Mario Hunt',
        'url' => 'http://www.kane.com/',
        'languages' => [
            0 => 'de',
            1 => 'en'
        ],
        'description' => [
            'en' => 'en: Activity organization letter. Report alone why center.
                    Real outside glass maintain right hear.
                    Brother develop process work. Build ago north.
                    Develop with defense understand garden recently work.',
            'de' => 'de: Activity few enter medical side position. Safe need no guy price.
                    Source necessary our me series month seven born.
                    Anyone everything interest where accept apply. Expert great significant.'
        ],
        'icon' => 'https://placehold.jp/40e47e/598311/150x150.png',
        'license' => '',
        'tags' => [
            0 => 'fXuprCRqsLnQQYzFZgAt'
        ]
    ]);
}
