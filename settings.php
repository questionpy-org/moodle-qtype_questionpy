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
 * Settings for this question type.
 *
 * @package    qtype_questionpy
 * @copyright  2022 Martin Gauk, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use qtype_questionpy\package_settings;

if ($ADMIN->fulltree) {

    // General server settings.
    $settings->add(new admin_setting_configtext(
        'qtype_questionpy/server_url',
        new lang_string('server_url', 'qtype_questionpy'),
        new lang_string('server_url', 'qtype_questionpy'),
        'http://localhost:9020/',
        PARAM_URL,
        20
    ));

    $settings->add(new admin_setting_encryptedpassword(
        'qtype_questionpy/server_password',
        new lang_string('server_password', 'qtype_questionpy'),
        new lang_string('server_password_description', 'qtype_questionpy')
    ));

    $settings->add(new admin_setting_configtext(
        'qtype_questionpy/server_timeout',
        new lang_string('server_timeout', 'qtype_questionpy'),
        new lang_string('server_timeout_description', 'qtype_questionpy'),
        5,
        PARAM_INT,
        5
    ));

    // Package settings.
    $settings->add(new admin_setting_heading(
        'qtype_questionpy/heading_packages',
        'Packages',
        null
    ));

    $settings->add(new package_settings());

    $settings->add(new admin_setting_configtext(
        'qtype_questionpy/max_package_size_kb',
        new lang_string('max_package_size_kb', 'qtype_questionpy'),
        new lang_string('max_package_size_kb_description', 'qtype_questionpy'),
        512.0,
        PARAM_FLOAT,
        5
    ));

    $settings->add(new admin_setting_heading(
        'qtype_questionpy/server_info',
        new lang_string('server_info_heading', 'qtype_questionpy'),
        new lang_string('server_info_description', 'qtype_questionpy', [
            'link' => (string) new moodle_url('/question/type/questionpy/server_info.php'),
        ])
    ));
}

