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
 * @module qtype_questionpy/package_search/components/container
 */

import * as templates from 'core/templates';
import Component from 'qtype_questionpy/package_search/component';
import TabHeader from 'qtype_questionpy/package_search/components/tab_header';
import TabContent from 'qtype_questionpy/package_search/components/tab_content';

export default class extends Component {
    async create(descriptor) {
        // Register header and content of tabs.
        // TODO: register components inside mustache template.
        for (const category of ["all", "recentlyused", "favourites", "mine"]) {
            new TabHeader({
                element: this.getElement(`[data-for="${category}-header"]`),
                name: `category_${category}_header`,
                reactive: descriptor.reactive,
                category: category,
            });
            new TabContent({
                element: this.getElement(`[data-for="${category}-content"]`),
                name: `category_${category}_header`,
                reactive: descriptor.reactive,
                category: category,
            });
        }

        // Prefetch the package template for faster rendering.
        templates.prefetchTemplates(["qtype_questionpy/package/package_selection"]);
    }
}
