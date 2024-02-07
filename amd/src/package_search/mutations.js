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
 * @module qtype_questionpy/package_search/mutations
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

export default class {
    /**
     * @param {{contextid: number, limit: number}} options
     */
    constructor(options) {
        this.options = options;
    }

    /**
     * Search through given categories.
     *
     * If no page is provided the current page will be used.
     *
     * @param {any} state
     * @param {number|null} page
     * @param {string[]} categories
     * @returns {any}
     * @private
     */
    _getSearchPackagesInCategoriesPromise(state, page, categories) {
        const methods = [];
        for (const category of categories) {
            const method = {
                methodname: "qtype_questionpy_search_packages",
                args: {
                    query: state.general.query,
                    tags: state.general.tags,
                    category: category,
                    sort: state.general.sort,
                    order: state.general.order,
                    limit: this.options.limit,
                    page: (typeof page === "number") ? page : state[category].page,
                    contextid: this.options.contextid,
                },
            };
            methods.push(method);
        }
        return Ajax.call(methods);
    }

    /**
     * Used to search packages.
     *
     * Missing arguments are taken from the current state.
     *
     * @param {StateManager} stateManager
     * @param {Object|null} args
     * @param {string[]|null} categories
     */
    async searchPackages(stateManager, args = null, categories = null) {
        const state = stateManager.state;

        // Missing arguments are taken from the current state.
        args = args || {};

        // Search through every category if no categories are provided.
        categories = categories || ["all", "recentlyused", "favourites", "mine"];

        // Update general data.
        stateManager.setReadOnly(false);
        state.general.loading = true;
        state.general.query = (typeof args.query === "string") ? args.query : state.general.query;
        state.general.tags = [];
        state.general.sort = args.sort || state.general.sort;
        state.general.order = args.order || state.general.order;
        stateManager.setReadOnly(true);

        try {
            // Get search results for each category.
            let results = await this._getSearchPackagesInCategoriesPromise(state, args.page, categories);

            stateManager.setReadOnly(false);
            // Update category specific data.
            for (const [index, category] of categories.entries()) {
                state[category].data = await results[index];
                if (typeof args.page === "number") {
                    state[category].page = args.page;
                }
            }
            // Update loading status.
            state.general.loading = false;
            stateManager.setReadOnly(true);
        } catch (exception) {
            await Notification.exception(exception);
        }
    }

    /**
     * Used to search for packages only by providing a query.
     *
     * @param {StateManager} stateManager
     * @param {string} query
     */
    async searchPackagesByQuery(stateManager, query) {
        await this.searchPackages(stateManager, {page: 0, query: query});
    }
}
