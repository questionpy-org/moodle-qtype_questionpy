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

use core\context;
use core\exception\coding_exception;
use core\exception\moodle_exception;
use core_user\fields;

/**
 * Base class for bridges between QuestionPy and other plugins that use QuestionPy questions.
 *
 * This class is used to retrieve data that is not available through the Question API.
 *
 * @package    qtype_questionpy
 * @author     Martin Gauk
 * @copyright  2025 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class question_bridge_base {
    /** @var \question_attempt */
    protected $attempt;

    /** @var context */
    protected $context;

    /**
     * Create an instance from a question attempt.
     *
     * @param \question_attempt $attempt
     * @param context $context
     */
    public function __construct(\question_attempt $attempt, context $context) {
        $this->attempt = $attempt;
        $this->context = $context;
    }

    /**
     * Get user or group id this attempt belongs to.
     *
     * @return array{0: 'user'|'group', 1: int}
     */
    abstract public function get_user_or_group_id(): array;

    /**
     * Get additional LMS attributes.
     *
     * For example: attempt_started_at, submission_at, lms_moodle_component_name and lms_moodle_module_instance.
     *
     * @param string[] $requestedattributes
     * @return string[]
     */
    abstract protected function get_additional_lms_attributes(array $requestedattributes): array;

    /**
     * Get the requested attributes about the attempt.
     *
     * @param string[] $requestedattributes by the QPy package
     * @return array {
     *     lms?: array<string, string|int|null>,
     *     user?: array<string, string|int|null>,
     *     group?: array {
     *         group_id?: string|int|null,
     *         group_name?: string|null,
     *         members: list<array<string, string|int|null>>,
     *     },
     * }
     */
    public function get_attributes(array $requestedattributes): array {
        $attributes = [];
        $lmsattributes = [];

        if (in_array('course_id', $requestedattributes)) {
            $coursecontext = $this->context->get_course_context(false);
            $lmsattributes['course_id'] = $coursecontext ? $coursecontext->instanceid : null;
        }

        if (in_array('attempt_id', $requestedattributes)) {
            $lmsattributes['attempt_id'] = $this->attempt->get_database_id();
        }

        $lmsattributes += $this->get_additional_lms_attributes($requestedattributes);
        if ($lmsattributes) {
            $attributes['lms'] = $lmsattributes;
        }

        [$userfieldsapi, $userfieldsmapping] = $this->get_user_fields_api_mapping($requestedattributes);
        if ($userfieldsapi || in_array('group_id', $requestedattributes) || in_array('group_name', $requestedattributes)) {
            [$userorgroup, $id] = $this->get_user_or_group_id();

            if ($userorgroup === 'user' && $userfieldsapi) {
                $attributes['user'] = $this->get_user_attributes($id, $userfieldsapi, $userfieldsmapping);
            } else if ($userorgroup === 'group') {
                $attributes['group'] = $this->get_group_attributes($id, $requestedattributes, $userfieldsapi, $userfieldsmapping);
            }
        }

        return $attributes;
    }

    /**
     * Get user fields object and mapping for the requested attributes.
     *
     * @param array $requestedattributes
     * @return array{0: \core_user\fields|null, 1: array<string, string>|null} [null, null] if no user fields requested
     */
    protected function get_user_fields_api_mapping(array $requestedattributes) {
        $userfieldsapi = \core_user\fields::empty();
        $fieldsmapping = [];

        foreach ($requestedattributes as $attribute) {
            if ($attribute === 'user_id') {
                // ID must always be included in the query because get_records expects the first column to be unique.
                $fieldsmapping[$attribute] = 'id';
            } else if ($attribute === 'login_identifier') {
                $userfieldsapi->including('username');
                $fieldsmapping[$attribute] = 'username';
            } else if ($attribute === 'email') {
                $userfieldsapi->including('email');
                $fieldsmapping[$attribute] = 'email';
            } else if ($attribute === 'display_name') {
                $userfieldsapi->with_name();
                $fieldsmapping[$attribute] = ''; // We need to call the fullname function.
            } else if ($attribute === 'person_first_name') {
                $userfieldsapi->with_name();
                $fieldsmapping[$attribute] = 'firstname';
            } else if ($attribute === 'person_last_name') {
                $userfieldsapi->with_name();
                $fieldsmapping[$attribute] = 'lastname';
            } else if (str_starts_with($attribute, 'profile_field_')) {
                // Ensure the custom field exists, because the fields class JOINs the table.
                // If the field does not exist, we would get an empty result.
                $shortname = substr($attribute, 14);
                $fieldinfo = profile_get_custom_field_data_by_shortname($shortname);
                if ($fieldinfo) {
                    $userfieldsapi->including($attribute);
                    $fieldsmapping[$attribute] = $attribute;
                }
            }
        }

        if ($fieldsmapping) {
            return [$userfieldsapi, $fieldsmapping];
        }
        return [null, null];
    }

    /**
     * Get the attributes of a single user.
     *
     * @param int $userid
     * @param fields $userfieldsapi as returned by {@see self::get_user_fields_api_mapping()}
     * @param array $userfieldsmapping as returned by {@see self::get_user_fields_api_mapping()}
     * @return array
     */
    protected function get_user_attributes(int $userid, \core_user\fields $userfieldsapi, array $userfieldsmapping): array {
        global $DB;

        $fieldssql = $userfieldsapi->get_sql('u', true);
        $params = $fieldssql->params;
        $params['uid'] = $userid;
        $data = $DB->get_record_sql(
            "SELECT u.id {$fieldssql->selects} FROM {user} u {$fieldssql->joins} WHERE u.id = :uid",
            $params
        );
        return $this->extract_user_attributes($data, $userfieldsmapping);
    }

    /**
     * Extract the user attributes from the user data object.
     *
     * @param \stdClass $userdata
     * @param array $userfieldsmapping as returned by {@see self::get_user_fields_api_mapping()}
     * @return array
     */
    protected function extract_user_attributes(\stdClass $userdata, array $userfieldsmapping): array {
        $attributes = [];

        foreach ($userfieldsmapping as $attributename => $field) {
            if ($attributename === 'display_name') {
                $attributes[$attributename] = \core_user::get_fullname($userdata, $this->context);
            } else {
                $attributes[$attributename] = $userdata->{$field} ?? null;
            }
        }

        return $attributes;
    }

    /**
     * Get the attributes of a group and its members.
     *
     * @param int $groupid
     * @param array $requestedattributes
     * @param fields|null $userfieldsapi as returned by {@see self::get_user_fields_api_mapping()}
     * @param array|null $userfieldsmapping as returned by {@see self::get_user_fields_api_mapping()}
     * @return array
     */
    protected function get_group_attributes(int $groupid, array $requestedattributes, ?\core_user\fields $userfieldsapi,
                                            ?array $userfieldsmapping): array {
        global $DB;
        $attributes = [];

        if (in_array('group_id', $requestedattributes)) {
            $attributes['group_id'] = $groupid;
        }

        if (in_array('group_name', $requestedattributes)) {
            $attributes['group_name'] = $DB->get_field('groups', 'name', ['id' => $groupid]);
        }

        // Get group members and their attributes.
        if ($userfieldsapi) {
            $fieldssql = $userfieldsapi->get_sql('u', true);
            $params = $fieldssql->params;
            $params['groupid'] = $groupid;
            $data = $DB->get_records_sql("SELECT u.id {$fieldssql->selects}
                                         FROM {groups_members} gm
                                         JOIN {user} u ON (u.id = gm.userid)
                                         {$fieldssql->joins}
                                         WHERE gm.groupid = :groupid", $params);

            $attributes['members'] = [];
            foreach ($data as $userdata) {
                $attributes['members'][] = $this->extract_user_attributes($userdata, $userfieldsmapping);
            }
        }

        return $attributes;
    }

    /**
     * Create a bridge for the given attempt.
     *
     * We already provide our own bridges for the core_question_preview and mod_quiz components.
     * Otherwise, we try to load a bridge class from the component namespace.
     *
     * @param \question_attempt $attempt
     * @return self
     */
    public static function create(\question_attempt $attempt): self {
        global $DB;

        if ($attempt->get_database_id() === null || !is_numeric($attempt->get_usage_id())) {
            throw new moodle_exception('attempt_not_saved', 'qtype_questionpy');
        }

        $usage = $DB->get_record('question_usages', ['id' => $attempt->get_usage_id()], '*', MUST_EXIST);
        $context = context::instance_by_id($usage->contextid);

        if ($usage->component === 'mod_quiz') {
            return new local\bridge\mod_quiz($attempt, $context);
        }
        if ($usage->component === 'core_question_preview') {
            return new local\bridge\core_question_preview($attempt, $context);
        }

        $classname = "\\{$usage->component}\\qtype_questionpy\\bridge";
        if (class_exists($classname)) {
            return new $classname($attempt, $context);
        }
        throw new coding_exception("QuestionPy bridge class not found: '$classname'");
    }
}
