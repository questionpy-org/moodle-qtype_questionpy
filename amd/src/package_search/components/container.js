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
            {watch: `general.loading:updated`, handler: this.updateStatus},
            {watch: `all:updated`, handler: this.render},
        ];
    }

    async create() {
        this.selectors = {
            LOADING_INDICATOR: `[data-for="loading-indicator"]`,
            ALL_HEADER: `[data-for="all-header"]`,
            ALL_CONTENT: `[data-for="all-content"]`,
        };

        // Prefetch the package template for faster rendering.
        this.packageTemplate = "qtype_questionpy/package/package_selection";
        await templates.prefetchTemplates([this.packageTemplate]);
    }

    async stateReady() {
        // Initial loading of the packages.
        this.reactive.dispatch('searchPackages');
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
    _getRenderPromise(contexts) {
        let promises = [];
        for (const context of contexts) {
            const promise = templates.renderForPromise(this.packageTemplate, context);
            promises.push(promise);
        }
        return Promise.all(promises);
    }

    /**
     * Renders every package inside the current state.
     *
     * @returns {Promise<void>}
     */
    async render() {
        try {
            // Get string and render templates.
            const getStringAll = strings.get_string("all_packages", "qtype_questionpy", this.getState().all.data.total);
            const renderTemplatesAll = this._getRenderPromise(this.getState().all.data.packages);
            const [stringAll, templatesAll] = await Promise.all([getStringAll, renderTemplatesAll]);

            // Update DOM.
            this.getElement(this.selectors.ALL_CONTENT).innerHTML = "";
            for (const {html, js} of templatesAll) {
                templates.appendNodeContents(this.getElement(this.selectors.ALL_CONTENT), html, js);
            }
            this.getElement(this.selectors.ALL_HEADER).innerHTML = stringAll;
        } catch (exception) {
            await Notification.exception(exception);
        }
    }
}
