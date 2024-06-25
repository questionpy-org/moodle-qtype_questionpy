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

use admin_setting;
use lang_string;
use moodle_exception;

/**
 * Custom admin settings to fetch packages from the application server, save them in, and delete them from the database.
 *
 * @package    qtype_questionpy
 * @copyright  2023 Jan Britz, TU Berlin, innoCampus - www.questionpy.org
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class package_settings extends admin_setting {
    /**
     * Constructor for custom package settings.
     */
    public function __construct() {
        $this->nosave = true;
        $this->plugin = 'qtype_questionpy';
        parent::__construct(
            'qtype_questionpy/persist_packages',
            new lang_string('packages_subheading', 'qtype_questionpy'),
            null,
            null
        );
    }

    /**
     * Return the XHTML to display the settings.
     *
     * @param mixed $data
     * @param string $query
     * @return string
     * @throws moodle_exception
     */
    public function output_html($data, $query = ''): string {
        global $OUTPUT, $PAGE, $DB;

        $packagecount = $DB->count_records('qtype_questionpy_package');
        $versioncount = $DB->count_records('qtype_questionpy_pkgversion');

        $element = $OUTPUT->render_from_template('qtype_questionpy/settings/packages', [
            'package_count' => $packagecount,
            'version_count' => $versioncount,
        ]);

        $PAGE->requires->js_call_amd('qtype_questionpy/package_settings', 'init');
        return format_admin_setting($this, $this->visiblename, $element, null, '', '', null, $query);
    }

    /**
     * As no setting is written, this function is empty.
     *
     * @return true
     */
    public function get_setting(): bool {
        /* Returning null causes moodle to think the setting is new and has yet to be set,
           blocking the upgradesettings.php flow. So we return true. */
        return true;
    }

    /**
     * As no setting is written, this function is empty.
     *
     * @param mixed $data
     * @return string empty string if ok, string error message otherwise
     */
    public function write_setting($data): string {
        return "";
    }
}
