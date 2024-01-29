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
     * Sets search current status.
     *
     * @param {StateManager} stateManager
     * @param {boolean} loading
     */
    _setLoading(stateManager, loading) {
        stateManager.setReadOnly(false);
        stateManager.state.general.loading = loading;
        stateManager.setReadOnly(true);
    }

    /**
     * Search through given categories with same arguments.
     *
     * @param {Object} args the arguments
     * @param {string} categories
     * @returns {any}
     * @private
     */
    _getSearchPackagesInCategoriesPromise(args, ...categories) {
        let clonedArgs = {...args};
        let methods = [];
        for (const category of categories) {
            let method = {
                methodname: "qtype_questionpy_search_packages",
                args: clonedArgs,
            };
            method.args.category = category;
            methods.push(method);
            clonedArgs = {...args};
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
     */
    async searchPackages(stateManager, args = null) {
        const state = stateManager.state;

        // Missing arguments are taken from the current state.
        args = args || {};
        let mergedArgs = {
            query: args.query || state.general.query,
            tags: [], // TODO.
            sort: args.sort || state.general.sort,
            order: args.order || state.general.order,
            limit: this.options.limit,
            page: 0,
            contextid: this.options.contextid,
        };

        this._setLoading(stateManager, true);

        try {
            let [all, recentlyused, favourites, mine] = await this._getSearchPackagesInCategoriesPromise(mergedArgs,
                "all", "recentlyused", "favourites", "mine");

            stateManager.setReadOnly(false);
            state.all.data = await all;
            state.recentlyused.data = await recentlyused;
            state.favourites.data = await favourites;
            state.mine.data = await mine;
            stateManager.setReadOnly(true);
        } catch (exception) {
            await Notification.exception(exception);
        }

        this._setLoading(stateManager, false);
    }

    /**
     * Used to search for packages only by providing a query.
     *
     * @param {StateManager} stateManager
     * @param {string} query
     */
    async searchPackagesByQuery(stateManager, query) {
        await this.searchPackages(stateManager, {query: query});
    }
}
