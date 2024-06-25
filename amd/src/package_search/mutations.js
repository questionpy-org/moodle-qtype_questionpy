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
import {favouritePackage} from 'qtype_questionpy/utils';

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
                    sort: state.general.sorting.sort,
                    order: state.general.sorting.order,
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
     * Sets the `loading` property.
     *
     * It only communicates changes to the watchers if the `StateManager` is currently readonly.
     *
     * @param {StateManager} stateManager
     * @param {boolean} loading
     * @private
     */
    _setLoading(stateManager, loading) {
        const state = stateManager.state;
        if (state.loading === loading) {
            return;
        }
        const isReadonly = stateManager.readonly;
        if (isReadonly) {
            stateManager.setReadOnly(false);
        }
        state.general.loading = loading;
        if (isReadonly) {
            stateManager.setReadOnly(true);
        }
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
        categories = categories || ["all", "recentlyused", "favourites"];

        // Update general data.
        stateManager.setReadOnly(false);
        this._setLoading(stateManager, true);
        state.general.query = (typeof args.query === "string") ? args.query : state.general.query;
        state.general.tags = args.tags || state.general.tags;
        state.general.sorting = {
            sort: args.sort || state.general.sorting.sort,
            order: args.order || state.general.sorting.order,
        };
        stateManager.setReadOnly(true);

        try {
            // Get search results for each category.
            let results = await this._getSearchPackagesInCategoriesPromise(state, args.page, categories);

            stateManager.setReadOnly(false);
            // Update category specific data.
            for (const [index, category] of categories.entries()) {
                const result = await results[index];
                state[`${category}Packages`] = result.packages;
                state[category].count = result.count;
                state[category].total = result.total;
                if (typeof args.page === "number") {
                    state[category].page = args.page;
                }
            }
            stateManager.setReadOnly(true);
        } catch (exception) {
            await Notification.exception(exception);
        } finally {
            this._setLoading(stateManager, false);
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

  /**
   * Used to filter packages only by providing tags.
   *
   * @param {StateManager} stateManager
   * @param {int[]} tags
   * @returns {Promise<void>}
   */
    async filterPackagesByTags(stateManager, tags) {
        await this.searchPackages(stateManager, {tags: tags});
    }

    /**
     * Used to change the current page of a tab.
     *
     * @param {StateManager} stateManager
     * @param {string} category
     * @param {number} page
     */
    async changePage(stateManager, category, page) {
        await this.searchPackages(stateManager, {page: page}, [category]);
    }

    /**
     * Used to change the current sorting.
     *
     * @param {StateManager} stateManager
     * @param {string} sort
     * @param {string} order
     */
    async changeSort(stateManager, sort, order) {
        await this.searchPackages(stateManager, {sort: sort, order: order}, ["all", "favourites"]);
    }

    /**
     * Used to re-/load data of given categories.
     *
     * @param {StateManager} stateManager
     * @param {string[]} categories
     */
    async load(stateManager, categories) {
        await this.searchPackages(stateManager, {}, categories);
    }

    /**
     * Used to un-/favourite a package.
     *
     * @param {StateManager} stateManager
     * @param {int} packageid
     * @param {boolean} favourite
     */
    async favourite(stateManager, packageid, favourite) {
        const state = stateManager.state;
        try {
            this._setLoading(stateManager, true);
            const successful = await favouritePackage(packageid, favourite);
            if (!successful) {
                return;
            }
            stateManager.setReadOnly(false);
            for (const category of ["all", "recentlyused"]) {
                const pkg = state[`${category}Packages`].get(packageid);
                if (pkg) {
                    pkg.isfavourite = favourite;
                }
            }
            stateManager.setReadOnly(true);
            let page = state.favourites.page;
            if (!favourite) {
                // Turn back a page in 'favourites' if the unmarked package was the last one on the page.
                const isFirstPage = page === 0;
                const isLastPage = page === Math.floor((state.favourites.total - 1) / this.options.limit);
                const existsOnePackage = state.favourites.count === 1;
                if (!isFirstPage && isLastPage && existsOnePackage) {
                    page -= 1;
                }
            }
            await this.changePage(stateManager, 'favourites', page);
        } catch (exception) {
            await Notification.exception(exception);
        } finally {
            this._setLoading(stateManager, false);
        }
    }
}
