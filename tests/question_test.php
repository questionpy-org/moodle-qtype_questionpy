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

use coding_exception;
use core\di;
use moodle_exception;
use qbehaviour_questionpy;
use qtype_questionpy\event\grading_response_failed;
use qtype_questionpy\event\starting_attempt_failed;
use qtype_questionpy\event\viewing_attempt_failed;
use qtype_questionpy\local\api\api;
use qtype_questionpy\local\api\package_api;
use qtype_questionpy\local\api\question_data;
use qtype_questionpy\local\api\question_response;
use qtype_questionpy\local\api\scoring_method;
use qtype_questionpy\local\attempt_ui\question_ui_metadata_extractor;
use qtype_questionpy\local\files\response_file_service;
use qtype_questionpy_question;
use question_attempt;
use question_attempt_step;
use question_bank;
use question_engine;
use question_state;
use testable_question_attempt;
use Throwable;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/behaviour/questionpy/behaviour.php');
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');

/**
 * Unit tests for the questionpy question class.
 *
 * @package    qtype_questionpy
 * @copyright  2024 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class question_test extends \advanced_testcase {
    /**
     * @var api $api
     */
    private readonly api $api;

    /**
     * @var package_api $packageapi
     */
    private readonly package_api $packageapi;

    /** @var qbehaviour_questionpy $behaviour */
    private readonly qbehaviour_questionpy $behaviour;

    /**
     * This method is called before each test.
     *
     * @throws moodle_exception
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        // Load questionpy.
        question_bank::load_question_definition_classes('questionpy');

        $this->api = $this->createMock(api::class);
        $this->packageapi = $this->createMock(package_api::class);
        $this->api->method('package')
            ->willReturn($this->packageapi);

        $this->behaviour = $this->createStub(qbehaviour_questionpy::class);
    }

    /**
     * Create a QuestionPy question.
     *
     * @return qtype_questionpy_question
     * @throws coding_exception
     */
    private function create_question(): qtype_questionpy_question {
        question_engine::load_behaviour_class('questionpy');
        $state = 'state';
        $response = new question_response('en', $state, scoring_method::automatically_scorable);
        $questiondata = question_data::from_question_response($response);

        $question = new qtype_questionpy_question(
            hash('sha256', 'hash'),
            $state,
            $questiondata,
            packagefile: null,
            api: $this->api,
            rfs: $this->createStub(response_file_service::class),
        );
        $question->behaviour = $this->behaviour;
        return $question;
    }

    /**
     * Tests that a failed start_attempt-call gets logged and neither the ui nor metadata gets set.
     *
     * @covers \qtype_questionpy_question::start_attempt
     * @throws Throwable
     */
    public function test_start_attempt_request_failed(): void {
        $exception = new \Exception('example error message');
        $this->packageapi->method('start_attempt')
            ->willThrowException($exception);

        $question = $this->create_question();

        $sink = $this->redirectEvents();

        // Calling expectExpectation and assertDebuggingCalled seems buggy.
        try {
            $question->start_attempt(new question_attempt_step(), 1);
            $this->fail('An exception should have been thrown.');
        } catch (\Exception $e) {
            $this->assertEquals($exception, $e);
        }

        // Check if the event was created.
        $events = $sink->get_events();
        $event = reset($events);
        $sink->close();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(starting_attempt_failed::class, $event);
        $this->assertStringContainsString($exception->getMessage(), $event->get_description());

        // Check if debugging() was called.
        $this->assertDebuggingCalled();

        // Check if ui and metadata is not set.
        $this->assertFalse(isset($question->ui));
        $this->assertFalse(isset($question->metadata));
    }

    /**
     * Tests that a failed apply_attempt_state-call gets logged and neither the ui nor metadata gets set.
     *
     * @covers \qtype_questionpy_question::apply_attempt_state
     * @throws moodle_exception
     */
    public function test_apply_attempt_state_failed(): void {
        $exception = new \Exception('example error message');
        $this->packageapi->method('view_attempt')->willThrowException($exception);

        $question = $this->create_question();
        $qa = new testable_question_attempt($question, 1);
        $this->behaviour->method('get_qa')->willReturn($qa);

        // Pretend that the question was started successfully.
        $step = new question_attempt_step();
        $step->set_qt_var(constants::QT_VAR_ATTEMPT_STATE, 'state');
        $qa->add_step($step);

        $sink = $this->redirectEvents();
        $question->apply_attempt_state($step);

        // Check if the event was created.
        $events = $sink->get_events();
        $event = reset($events);
        $sink->close();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(viewing_attempt_failed::class, $event);
        $this->assertStringContainsString($exception->getMessage(), $event->get_description());

        // Check if debugging() was called.
        $this->assertDebuggingCalled();

        // Check if ui and metadata is not set.
        $this->assertFalse(isset($question->ui));
        $this->assertFalse(isset($question->metadata));
    }

    /**
     * Tests that a failed grade_response-call gets logged and the question is marked as needs manual grading.
     *
     * @covers \qtype_questionpy_question::grade_response
     * @throws moodle_exception
     */
    public function test_grade_response_failed(): void {
        $exception = new \Exception('example error message');
        $this->packageapi->method('score_attempt')->willThrowException($exception);

        $question = $this->create_question();
        $question->metadata = $this->createStub(question_ui_metadata_extractor::class);
        $question->attemptstate = 'state';
        $question->scoringstate = 'state';

        $sink = $this->redirectEvents();
        [$fraction, $state] = $question->grade_response([]);

        // Check if the event was created.
        $events = $sink->get_events();
        $event = reset($events);
        $sink->close();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(grading_response_failed::class, $event);
        $this->assertStringContainsString($exception->getMessage(), $event->get_description());

        // Check if debugging() was called.
        $this->assertDebuggingCalled();

        // Check the fraction and state.
        $this->assertEquals(0, $fraction);
        $this->assertEquals(question_state::$needsgrading, $state);

        // Check if ui and metadata is not set.
        $this->assertFalse(isset($question->ui));
    }

    /**
     * Tests that the method returns the correct value.
     *
     * @covers \qtype_questionpy_question::get_expected_data
     * @throws coding_exception
     */
    public function test_get_expected_data(): void {
        $question = $this->create_question();

        $this->assertEquals([
            constants::QT_VAR_RESPONSE => PARAM_RAW_TRIMMED,
            constants::QT_VAR_RESPONSE_FILES => question_attempt::PARAM_FILES,
            constants::QT_VAR_EDITORS => PARAM_RAW_TRIMMED,
        ], $question->get_expected_data());
    }

    /**
     * Tests that the method returns false if there is no metadata and no response.
     *
     * @covers \qtype_questionpy_question::is_complete_response
     * @throws coding_exception
     */
    public function test_is_complete_response_should_return_false_if_error_during_load(): void {
        $question = $this->create_question();
        $question->errorduringload = true;
        $this->assertFalse($question->is_complete_response([]));
    }

    /**
     * Tests that the method returns true if there is no metadata but a response.
     *
     * @covers \qtype_questionpy_question::is_complete_response
     * @throws \core\exception\coding_exception
     */
    public function test_is_complete_response_should_return_true_if_error_during_load_and_non_empty_response(): void {
        $question = $this->create_question();
        $question->errorduringload = true;
        $this->assertTrue($question->is_complete_response([constants::QT_VAR_RESPONSE => json_encode(['test' => 'data'])]));
    }
}
