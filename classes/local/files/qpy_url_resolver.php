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

namespace qtype_questionpy\local\files;

use moodle_exception;
use moodle_url;
use qtype_questionpy_question;

/**
 * Aggregates multiple {@see handles_qpy_url_type} implementations and provides a single interface for resolving and serving.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2025 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qpy_url_resolver {
    /** @var string */
    private const QPY_URL_PATTERN = ';qpy://(?P<type>\w+)(?P<path>(?:/[\w\-@:%+.~=]+)+);';

    /** @var array<string, handles_qpy_url_type> $providers */
    private readonly array $providers;

    /**
     * Trivial constructor.
     *
     * @param options_file_service $ofs
     * @param static_file_service $sfs
     */
    public function __construct(options_file_service $ofs, static_file_service $sfs) {
        $this->providers = [
            'options' => $ofs,
            'static' => $sfs,
        ];
    }

    /**
     * Replaces QPy-URLs with functioning pluginfile URLs.
     *
     * @param string $text
     * @param qtype_questionpy_question $question
     * @return string
     * @throws moodle_exception
     */
    public function replace_qpy_urls(string $text, qtype_questionpy_question $question): string {
        return preg_replace_callback(
            self::QPY_URL_PATTERN,
            function (array $match) use ($question) {
                $provider = $this->providers[$match['type']] ?? null;
                if (!$provider) {
                    debugging("Unsupported QPy-URL: '$match[0]'");
                    return new moodle_url('/brokenfile.php');
                }

                return $provider->resolve_qpy_url($match['path'], $question);
            },
            $text
        );
    }

    /**
     * Serves a plugin file using the {@see handles_qpy_url_type} implementation for the given `$filearea`.
     *
     * The arguments are passed directly from {@see qtype_questionpy_pluginfile}.
     *
     * This method never returns.
     *
     * @param object $context
     * @param string $filearea
     * @param array $args
     * @return never
     * @throws moodle_exception
     */
    public function serve_pluginfile(object $context, string $filearea, array $args): never {
        $provider = $this->providers[$filearea] ?? null;
        if (!$provider) {
            send_file_not_found();
        }

        $provider->serve_pluginfile($context, $args);
    }
}
