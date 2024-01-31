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

export default class extends Component {
    getWatchers() {
        return [
            {watch: `${this.category}:updated`, handler: this.render},
        ];
    }

    async create(descriptor) {
        this.category = descriptor.category;
        this.selectors = {
            CONTENT: ".qpy-tab-content",
        };
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
            const promise = templates.renderForPromise("qtype_questionpy/package/package_selection", context);
            promises.push(promise);
        }
        return Promise.all(promises);
    }

    /**
     * Renders every package inside the current state.
     */
    async render() {
        try {
            const state = this.getState()[this.category];
            const packageTemplates = await this._getPackageTemplatesPromise(state.data.packages);
            const element = this.getElement(this.selectors.CONTENT);
            element.innerHTML = "";
            for (const {html, js} of packageTemplates) {
                templates.appendNodeContents(element, html, js);
            }
        } catch (exception) {
            await Notification.exception(exception);
        }
    }
}
