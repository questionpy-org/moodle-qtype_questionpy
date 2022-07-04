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

/**
 * Settings for this question type.
 *
 * @package    qtype_questionpy
 * @copyright  2022 Martin Gauk, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


if ($ADMIN->fulltree) {

    $settings->add(new admin_setting_configtext(
        'qtype_questionpy/applicationserver_url',
        new lang_string('applicationserverurl', 'qtype_questionpy'),
        new lang_string('applicationserverurl', 'qtype_questionpy'),
        'http://localhost:9020/helloworld',
        PARAM_URL,
        20
    ));

    $settings->add(new admin_setting_encryptedpassword(
        'qtype_questionpy/applicationserver_password',
        new lang_string('applicationserverpassword', 'qtype_questionpy'),
        new lang_string('applicationserverpassword_description', 'qtype_questionpy')
    ));

    $settings->add(new admin_setting_configtext(
        'qtype_questionpy/applicationserver_maxservertimeout',
        new lang_string('maxservertimeout', 'qtype_questionpy'),
        new lang_string('maxservertimeout_description', 'qtype_questionpy'),
        5,
        PARAM_INT,
        5
    ));

    $settings->add(new admin_setting_configtext(
        'qtype_questionpy/applicationserver_maxquestionsize',
        new lang_string('maxquestionsize', 'qtype_questionpy'),
        new lang_string('maxquestionsize_description', 'qtype_questionpy'),
        512.0,
        PARAM_FLOAT,
        5
    ));

}

