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

$string['attempt_detail_heading'] = 'QuestionPy Details for Attempt {$a}';
$string['attempt_detail_link'] = 'QuestionPy-specific details';
$string['attempt_does_not_exist'] = 'The attempt does not exist anymore.';
$string['attempt_not_questionpy'] = 'This is not an attempt at a QuestionPy question, but at a \'{$a}\' question.';
$string['attempt_preview'] = 'Preview';
$string['attempt_state_moodle'] = 'Moodle Attempt State';
$string['attempt_state_qpy'] = 'QuestionPy Attempt State';
$string['attempt_step_no'] = 'Step #';
$string['attempt_steps'] = 'Attempt Steps';
$string['change_package'] = 'Change';
$string['disable_json_formatting'] = 'Disable JSON formatting';
$string['enable_json_formatting'] = 'Enable JSON formatting';
$string['event_grading_response_failed'] = 'Grading response failed';
$string['event_starting_attempt_failed'] = 'Starting attempt failed';
$string['event_viewing_attempt_failed'] = 'Viewing attempt failed';
$string['form_fallback_element_text'] = 'The QuestionPy package is using a form element not supported by the Moodle'
    . ' plugin. Please ensure you are using a compatible package or contact your administrators.';
$string['formerror_noqpy_package'] = 'Selected file must be of type .qpy';
$string['load_packages_button'] = 'Load Packages';
$string['mark'] = 'Mark';
$string['mark_as_favourite'] = 'Favourite';
$string['max_package_size_kb'] = 'Maximum file size of a QuestionPy package';
$string['max_package_size_kb_description'] = 'Maximum file size in kB';
$string['missing_select_option'] = '(the selected option is no longer available)';
$string['open_website'] = 'Open website';
$string['options_form_validation_error_element'] = '{$a->name}: {$a->error}';
$string['options_form_validation_error_title'] = 'The following errors occurred while validating the form and could not'
    . ' be mapped to an input element: {$a}';
$string['package_not_found'] = 'The requested package {$a->packagehash} does not exist.';
$string['packages_subheading'] = 'Packages';
$string['pluginname'] = 'QuestionPy';
$string['pluginname_help'] = 'Create own question types in Python.';
$string['pluginnameadding'] = 'Adding a QuestionPy question';
$string['pluginnameediting'] = 'Editing a QuestionPy question';
$string['pluginnamesummary'] = 'A comprehensive question type that allows you to create own question types in Python.';
$string['question_package_search'] = 'Select an existing package';
$string['question_package_upload'] = 'Upload your own';
$string['question_state'] = 'QuestionPy Question State';
$string['questionpy:uploadpackages'] = 'Upload custom QuestionPy packages';
$string['questionpy:viewdetails'] = 'View technical details of QuestionPy questions';
$string['remove_packages_button'] = 'Remove Packages';
$string['render_error_section'] = 'An error occurred';
$string['render_warning_invalid_value'] = 'The last submission set the field {$a->name} to the value {$a->value}, but that option is no longer available. The available options are: {$a->availablevalues}.';
$string['render_warning_invalid_value_not_preserved'] = 'The invalid value will be overwritten the next time you save your answer.';
$string['render_warning_invalid_value_preserved'] = 'You may set the field to a valid value, or leave it untouched to preserve the invalid value.';
$string['render_warnings_hint_contact_teachers'] = 'If you believe this to be in error, contact your teachers. They will also see this notice and may decide to override whichever score you receive.';
$string['request_error'] = 'The {$a->requestmethod} request to "{$a->uri}" failed with the error code "{$a->errorcode}" and'
    . ' status code {$a->statuscode} ({$a->reasonphrase}).';
$string['same_version_different_hash_error'] = 'A package with the same version but different hash already exists.';
$string['scoring_state'] = 'QuestionPy Scoring State';
$string['search_all_header'] = 'All ({$a})';
$string['search_bar'] = 'Search...';
$string['search_bar_label_aria'] = 'Search Bar';
$string['search_favourites_header'] = 'Favourites ({$a})';
$string['search_pagination_label_aria'] = 'Search results pages';
$string['search_pagination_next_aria'] = 'Next';
$string['search_pagination_previous_aria'] = 'Previous';
$string['search_recentlyused_header'] = 'Recently Used ({$a})';
$string['search_sort_alphabetical'] = 'Alphabetical';
$string['search_sort_creation_date'] = 'Creation Date';
$string['search_sort_label_aria'] = 'Sorting';
$string['select_package'] = 'Select';
$string['select_package_element_aria'] = 'Choose version.';
$string['selection_custom_package_header'] = 'Custom Package';
$string['selection_custom_package_text'] = 'This package version was uploaded by a user and might not appear in the package'
    . ' search.';
$string['selection_no_icon'] = 'Could not load the icon.';
$string['selection_package_no_longer_in_database_header'] = 'Discontinued';
$string['selection_package_no_longer_in_database_text'] = 'This package version is no longer available through the package search.';
$string['selection_required'] = 'Please select a package.';
$string['selection_title'] = 'Select QuestionPy Package';
$string['selection_title_selected'] = 'Selected Package';
$string['server_info_allow_lms_packages'] = 'Allows packages from the LMS';
$string['server_info_description'] = '<a href="{$a->link}">Information</a> about the application server you are connected to.';
$string['server_info_heading'] = 'QuestionPy Application Server Information';
$string['server_info_max_package_size'] = 'Maximum package size';
$string['server_info_name'] = 'Name';
$string['server_info_requests_in_process'] = 'Requests in process';
$string['server_info_requests_in_queue'] = 'Requests in queue';
$string['server_info_title_general'] = 'General';
$string['server_info_usage_title'] = 'Usage';
$string['server_info_version'] = 'Version';
$string['server_password'] = 'QuestionPy Application Server Password';
$string['server_password_description'] = 'The Password to access the Application Server';
$string['server_timeout'] = 'Server timeout time';
$string['server_timeout_description'] = 'Server timeout time in seconds';
$string['server_url'] = 'QuestionPy Application Server URL';
$string['server_username'] = 'QuestionPy Application Server Username';
$string['server_username_description'] = 'The Username to access the Application Server';
$string['service_failed'] = 'Failed.';
$string['tag_bar'] = 'Tags...';
$string['tag_bar_label_aria'] = 'Tags';
$string['tag_bar_no_selection'] = '';
$string['total_packages'] = '{$a->packages} packages with a total of {$a->versions} versions';
$string['unmark_as_favourite'] = 'Favourite';
$string['upload_not_permitted'] = 'You do not have permission to upload QuestionPy packages.';
$string['version_is_already_stored_error'] = 'The package version was already stored by the current user.';
