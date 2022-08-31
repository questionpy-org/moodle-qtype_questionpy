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

namespace qtype_questionpy\form\elements;

/**
 * Collection type for {@link form_element}s.
 */
class form_elements extends \ArrayIterator {
    public function __construct(form_element ...$items) {
        parent::__construct($items);
    }

    public function current(): form_element {
        return parent::current();
    }

    public function offsetGet($key): form_element {
        return parent::offsetGet($key);
    }

    public static function from_array(array $array): self {
        return new self(...array_map([form_element::class, "from_array_any"], $array));
    }
}
