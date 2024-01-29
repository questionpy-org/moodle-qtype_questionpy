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
import * as strings from 'core/str';
import Notification from 'core/notification';
import {BaseComponent} from 'core/reactive';

export default class extends BaseComponent {
    constructor(description) {
        super(description);
    }

    getWatchers() {
        return [
            {watch: "general.loading:updated", handler: this.updateStatus},
            {watch: "all:updated", handler: this.renderAll},
            {watch: "recentlyused:updated", handler: this.renderRecentlyUsed},
            {watch: "favourites:updated", handler: this.renderFavourites},
            {watch: "mine:updated", handler: this.renderMine},
        ];
    }

    async create() {
        this.selectors = {
            LOADING_INDICATOR: `[data-for="loading-indicator"]`,
            ALL_HEADER: `[data-for="all-header"]`,
            ALL_CONTENT: `[data-for="all-content"]`,
            RECENTLY_USED_HEADER: `[data-for="recently-used-header"]`,
            RECENTLY_USED_CONTENT: `[data-for="recently-used-content"]`,
            FAVOURITES_HEADER: `[data-for="favourites-header"]`,
            FAVOURITES_CONTENT: `[data-for="favourites-content"]`,
            MINE_HEADER: `[data-for="mine-header"]`,
            MINE_CONTENT: `[data-for="mine-content"]`,
        };

        // Prefetch the package template for faster rendering.
        this.packageTemplate = "qtype_questionpy/package/package_selection";
        await templates.prefetchTemplates([this.packageTemplate]);
    }

    async stateReady() {
        // Initial loading of the packages.
        this.reactive.dispatch("searchPackages");
    }

    /**
     * Returns the current state.
     *
     * @returns {any}
     */
    getState() {
        return this.reactive.stateManager.state;
    }

    /**
     * Hides or shows the loading indicator.
     */
    updateStatus() {
        const loading = this.getState().general.loading;
        this.getElement(this.selectors.LOADING_INDICATOR).style.visibility = loading ? "visible" : "hidden";
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
            const promise = templates.renderForPromise(this.packageTemplate, context);
            promises.push(promise);
        }
        return Promise.all(promises);
    }

    /**
     * Groups header and templates promises.
     *
     * @param {string} headerStringKey
     * @param {Object} packageData
     * @returns {Promise<[string, Object]>}
     * @private
     */
    async _renderPromise(headerStringKey, packageData) {
        // Get string and render templates.
        const getString = strings.get_string(headerStringKey, "qtype_questionpy", packageData.total);
        const renderTemplates = this._getPackageTemplatesPromise(packageData.packages);
        return Promise.all([getString, renderTemplates]);
    }

    /**
     * Renders every package inside a specific tab.
     *
     * @param {string} headerSelector
     * @param {string} contentSelector
     * @param {string} header
     * @param {Object} content
     * @private
     */
    _render(headerSelector, contentSelector, header, content) {
        const contentElement = this.getElement(contentSelector);
        contentElement.innerHTML = "";
        for (const {html, js} of content) {
            templates.appendNodeContents(contentElement, html, js);
        }
        this.getElement(headerSelector).innerHTML = header;
    }


    /**
     * Renders every package inside the current state for the `all`-category.
     *
     * @returns {Promise<void>}
     */
    async renderAll() {
        try {
            const state = this.getState();
            // Get string and package templates.
            const [string, packageTemplates] = await this._renderPromise("search_all_header", state.all.data);
            // Update DOM.
            this._render(this.selectors.ALL_HEADER, this.selectors.ALL_CONTENT, string, packageTemplates);
        } catch (exception) {
            await Notification.exception(exception);
        }
    }

    /**
     * Renders every package inside the current state for the `recentlyused`-category.
     *
     * @returns {Promise<void>}
     */
    async renderRecentlyUsed() {
        try {
            const state = this.getState();
            // Get string and package templates.
            const [string, packageTemplates] = await this._renderPromise("search_recently_used_header", state.recentlyused.data);
            // Update DOM.
            this._render(this.selectors.RECENTLY_USED_HEADER, this.selectors.RECENTLY_USED_CONTENT, string, packageTemplates);
        } catch (exception) {
            await Notification.exception(exception);
        }
    }

    /**
     * Renders every package inside the current state for the `favourites`-category.
     *
     * @returns {Promise<void>}
     */
    async renderFavourites() {
        try {
            const state = this.getState();
            // Get string and package templates.
            const [string, packageTemplates] = await this._renderPromise("search_favourites_header", state.favourites.data);
            // Update DOM.
            this._render(this.selectors.FAVOURITES_HEADER, this.selectors.FAVOURITES_CONTENT, string, packageTemplates);
        } catch (exception) {
            await Notification.exception(exception);
        }
    }

    /**
     * Renders every package inside the current state for the `mine`-category.
     *
     * @returns {Promise<void>}
     */
    async renderMine() {
        try {
            const state = this.getState();
            // Get string and package templates.
            const [string, packageTemplates] = await this._renderPromise("search_mine_header", state.mine.data);
            // Update DOM.
            this._render(this.selectors.MINE_HEADER, this.selectors.MINE_CONTENT, string, packageTemplates);
        } catch (exception) {
            await Notification.exception(exception);
        }
    }
}
