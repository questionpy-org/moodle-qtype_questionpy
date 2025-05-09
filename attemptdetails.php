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
 * Detailed view of QuestionPy states associated with a question attempt.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2025 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\di;
use qtype_questionpy\constants;
use qtype_questionpy\qpy_question_display_options;

require_once(__DIR__ . '/../../../config.php');
global $PAGE;

$attemptid = required_param('attemptid', PARAM_INT);
$noprettyprint = optional_param('nopretty', false, PARAM_BOOL);

$PAGE->set_url('/question/type/questionpy/attemptdetails.php', ['attemptid' => $attemptid]);

require_login();

global $DB;
// Get the context ID that owns the quiz (probably a mod_quiz context, but not necessarily).
$contextid = $DB->get_field_sql('
    SELECT contextid
    FROM {question_usages} quba
    JOIN {question_attempts} qa
    ON quba.id = qa.questionusageid
    WHERE qa.id = :attemptid
', ['attemptid' => $attemptid]);
if ($contextid === false) {
    throw new \core\exception\moodle_exception('attempt_does_not_exist', 'qtype_questionpy');
}

$context = context::instance_by_id($contextid);
if ($context === false) {
    throw new \core\exception\coding_exception("Context '$contextid' of attempt '$attemptid' does not exist.");
}

if ($context instanceof context_module) {
    $cm = get_fast_modinfo($context->get_course_context()->instanceid)->get_cm($context->instanceid);
    $PAGE->set_cm($cm);
}

if ($context instanceof context_user) {
    // When viewing this page on a preview, the attempt seems to belong to the user context, but going from the preview to the user
    // would be rather surprising, so we just show it in the system context.
    $PAGE->set_context(context_system::instance());
} else {
    $PAGE->set_context($context);
}

require_capability(constants::ROLE_VIEW_DETAILS, $context);

require_once(__DIR__ . '/../../engine/lib.php');

$qedm = di::get(question_engine_data_mapper::class);
$attempt = $qedm->load_question_attempt($attemptid);

$question = $attempt->get_question();
if (!($question instanceof qtype_questionpy_question)) {
    throw new \core\exception\moodle_exception('attempt_not_questionpy', 'qtype_questionpy', a: $question->qtype->local_name());
}

$title = new lang_string('attempt_detail_heading', 'qtype_questionpy', $attemptid);
$PAGE->set_title($title);
global $OUTPUT;

echo $OUTPUT->header();
echo $OUTPUT->heading($title);

$stepsarray = iterator_to_array($attempt->get_step_iterator());

$options = new qpy_question_display_options();
$options->qpyattemptdetailslink = question_display_options::HIDDEN;
$options->readonly = true;
$options->context = $context;

/**
 * If the state looks, swims, and quacks like JSON, pretty-print it.
 *
 * We technically don't show the exact states because of this, but it makes it much easier to read. For any packages that somehow
 * manage to use states which are valid JSON, but are not JSON and whitespace-sensitive, there is an opt-out.
 *
 * @param string|null $state
 * @return string|null
 */
function maybe_format_json(?string $state): ?string {
    global $noprettyprint;
    if (!$noprettyprint && $state && str_starts_with($state, '{') && str_ends_with($state, '}')) {
        // Assume it's JSON (as most states are) and try to format it.
        $formatted = json_encode(json_decode($state), JSON_PRETTY_PRINT);
        if (json_last_error() === JSON_ERROR_NONE) {
            // Successfully decoded and encoded -> it's JSON!
            return $formatted;
        }
    }

    return $state;
}

$enableppurl = new moodle_url($PAGE->url);
$enableppurl->remove_params('nopretty');
$disableppurl = new moodle_url($PAGE->url, ['nopretty' => true]);

echo $OUTPUT->render_from_template('qtype_questionpy/attempt_details', [
    'disable_pretty_url' => $noprettyprint ? null : $disableppurl,
    'enable_pretty_url' => $noprettyprint ? $enableppurl : null,
    'attempt_html' => $attempt->render($options, null),
    'question_state' => maybe_format_json($question->questionstate),
    'attempt_state' => maybe_format_json($attempt->get_last_qt_var(constants::QT_VAR_ATTEMPT_STATE)),
    'steps' => array_map(
        function ($step, $index) use ($question, $attempt) {
            $restrattempt = new question_attempt_with_restricted_history($attempt, $index, null);
            $behaviour = $restrattempt->get_behaviour();
            return [
                'index' => $index,
                'time' => userdate($step->get_timecreated(), get_string('strftimedatetimeshortaccurate', 'core_langconfig')),
                'state' => $behaviour->get_state_string(true),
                'mark' => $restrattempt->format_mark(2) ?? '',
                'scoring_state' => maybe_format_json($restrattempt->get_last_qt_var(constants::QT_VAR_SCORING_STATE)),
            ];
        },
        $stepsarray,
        array_keys($stepsarray)
    ),
]);

echo $OUTPUT->footer();
