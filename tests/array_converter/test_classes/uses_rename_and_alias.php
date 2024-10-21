<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace qtype_questionpy\array_converter\test_classes;

use qtype_questionpy\array_converter\attributes\array_alias;
use qtype_questionpy\array_converter\attributes\array_key;

/**
 * Test class using {@see array_key} and {@see array_alias}.
 *
 * @package    qtype_questionpy
 * @author     Maximilian Haye
 * @copyright  2024 TU Berlin, innoCampus {@link https://www.questionpy.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class uses_rename_and_alias {
    /** @var string $myprop1 */
    #[array_key("my_prop_1")]
    #[array_alias("my_alias_1")]
    public string $myprop1;

    /**
     * Initializes a new instance.
     *
     * @param string $myprop2
     */
    public function __construct(
        /** @var string $myprop2 */
        #[array_key("my_prop_2")]
        #[array_alias("my_alias_2")]
        public string $myprop2
    ) {
    }
}
