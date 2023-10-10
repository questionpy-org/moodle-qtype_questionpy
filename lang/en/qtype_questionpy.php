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
 * Strings for component 'qtype_questionpy', language 'en'
 *
 * @package    qtype_questionpy
 * @copyright  2022 Martin Gauk, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'QuestionPy';
$string['pluginname_help'] = 'Create own question types in Python.';
$string['pluginnameadding'] = 'Adding a QuestionPy question';
$string['pluginnameediting'] = 'Editing a QuestionPy question';
$string['pluginnamesummary'] = 'A comprehensive question type that allows you to create own question types in Python.';

// Application server settings.
$string['server_url'] = 'QuestionPy Application Server URL';
$string['server_password'] = 'QuestionPy Application Server Password';
$string['server_password_description'] = 'The Password to access the Application Server';
$string['server_timeout'] = 'Server timeout time';
$string['server_timeout_description'] = 'Server timeout time in seconds';
$string['max_package_size_kb'] = 'Maximum file size of a QuestionPy package';
$string['max_package_size_kb_description'] = 'Maximum file size in kB';
$string['packages_subheading'] = 'Packages';
$string['total_packages'] = '{$a->packages} packages with a total of {$a->versions} versions';
$string['load_packages_button'] = 'Load Packages';
$string['remove_packages_button'] = 'Remove Packages';
$string['service_failed'] = 'Failed.';
$string['server_info_heading'] = 'QuestionPy Application Server Information';
$string['server_info_description'] = '<a href="{$a->link}">Information</a> about the application server you are connected to.';
$string['server_info_title_general'] = 'General';
$string['server_info_name'] = 'Name';
$string['server_info_version'] = 'Version';
$string['server_info_allow_lms_packages'] = 'Allows packages from the LMS';
$string['server_info_max_package_size'] = 'Maximum package size';
$string['server_info_usage_title'] = 'Usage';
$string['server_info_requests_in_process'] = 'Requests in process';
$string['server_info_requests_in_queue'] = 'Requests in queue';

// Package upload.
$string['formerror_noqpy_package'] = 'Selected file must be of type .qpy';

// Package selection.
$string['selection_title'] = 'Select QuestionPy Package';
$string['selection_searchbar'] = 'Search...';
$string['selection_no_icon'] = 'Could not load the icon.';
$string['selection_required'] = 'Please select a package.';

$string['open_website'] = 'Open website';

$string['select_package'] = 'Select';
$string['select_package_element_aria'] = 'Choose version.';
$string['change_package'] = 'Change';

// Package selection container.
$string['all_packages'] = 'All ({$a})';

// Question management.
$string['package_not_found'] = 'The requested package {$a->packagehash} does not exist.';

// Connector.
$string['curl_init_error'] = 'Could not initialize cURL. Error number: {$a}';
$string['curl_exec_error'] = 'Error while fetching from server. Error number: {$a}';
$string['curl_set_opt_error'] = 'Failed to set cURL option. Error number: {$a}';
$string['json_parsing_error'] = 'Could not parse data to JSON.';
$string['server_bad_status'] = 'The QuestionPy server could not successfully complete our request. Status code: {$a}';
