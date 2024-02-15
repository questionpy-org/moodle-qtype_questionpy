/*
 * This file is part of the QuestionPy Moodle plugin - https://questionpy.org
 *
 * Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Moodle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * @module qtype_questionpy/package_search/components/tab_header
 */

import * as strings from 'core/str';
import Component from 'qtype_questionpy/package_search/component';

export default class extends Component {
    getWatchers() {
        return [
            {watch: `${this.category}:updated`, handler: this.render},
        ];
    }

    async create(descriptor) {
        this.category = descriptor.category;
    }

    /**
     * Renders every package inside a specific tab.
     */
    async render() {
        const data = this.getState()[this.category].data;
        this.element.innerHTML = await strings.get_string(`search_${this.category}_header`, "qtype_questionpy", data.total);
    }

}
