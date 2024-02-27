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
 * @module qtype_questionpy/package_search/components/tab_content
 */

import * as templates from 'core/templates';
import Notification from 'core/notification';
import Component from 'qtype_questionpy/package_search/component';
import Pagination from 'qtype_questionpy/package_search/components/pagination';
import Sort from 'qtype_questionpy/package_search/components/sort';
import Package from 'qtype_questionpy/package_search/components/package';

export default class extends Component {
    getWatchers() {
        return [
            {watch: `state.${this.category}Packages:updated`, handler: this.render},
        ];
    }

    async create(descriptor) {
        this.category = descriptor.category;
        this.selectors = {
            CONTENT: ".qpy-tab-content",
            SORT: '[data-for="sort"]',
            PAGINATION: '[data-for="pagination"]',
        };

        // Register sort if available.
        // TODO: register component inside mustache template.
        const sortElement = this.getElement(this.selectors.SORT);
        if (sortElement) {
            new Sort({
                element: sortElement,
                name: `sort_${this.category}`,
                reactive: descriptor.reactive,
            });
        }

        // Register pagination.
        // TODO: register component inside mustache template.
        new Pagination({
            element: this.getElement(this.selectors.PAGINATION),
            name: `pagiation_${this.category}`,
            reactive: descriptor.reactive,
            category: this.category,
        });
    }

    /**
     * Groups render promises for package templates.
     *
     * @param {Object[]} contexts
     * @returns {Promise}
     * @private
     */
    _getPackageTemplatesPromise(contexts) {
        let promises = [];
        for (const context of contexts) {
            // Context is a proxy, we need to get the target.
            const contextObj = Object.assign({}, context);
            const promise = templates.renderForPromise("qtype_questionpy/package/package_selection", contextObj);
            promises.push(promise);
        }
        return Promise.all(promises);
    }

    /**
     * Renders every package inside the current state.
     */
    async render() {
        try {
            const packages = Array.from(this.getState()[`${this.category}Packages`].values());
            const packageTemplates = await this._getPackageTemplatesPromise(packages);
            const contentElement = this.getElement(this.selectors.CONTENT);
            contentElement.innerHTML = "";
            let index = 0;
            for (const {html, js} of packageTemplates) {
                const packageElement = templates.appendNodeContents(contentElement, html, js)[0];
                // TODO: register component inside mustache template.
                new Package({
                    element: packageElement,
                    category: this.category,
                    packageid: packages[index++].id,
                });
            }
        } catch (exception) {
            await Notification.exception(exception);
        }
    }
}
