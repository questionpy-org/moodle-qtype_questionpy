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
 * @module qtype_questionpy/package_search/components/search_bar
 */

import {debounce} from 'core/utils';
import {BaseComponent} from 'core/reactive';

export default class extends BaseComponent {
    constructor(description) {
        super(description);
    }

    create() {
        this.selectors = {
            SEARCH_BAR: `[data-for="search-bar"]`,
        };

        // Debounce search method to reduce requests.
        this.searchPackages = debounce((event) => this._searchPackages(event), 300);
    }

    stateReady() {
        this.addEventListener(this.getElement(this.selectors.SEARCH_BAR), "input", this.searchPackages);
        this.addEventListener(this.getElement(this.selectors.SEARCH_BAR), "keydown", this.ignoreEnter);
    }

    /**
     * Prevents form submission by pressing the enter key.
     *
     * @param {KeyboardEvent} event
     */
    ignoreEnter(event) {
        if (event.key === "Enter") {
            event.preventDefault();
        }
    }

    /**
     * Dispatches package search by query mutation.
     *
     * @param {InputEvent} event
     * @private
     */
    _searchPackages(event) {
        this.reactive.dispatch('searchPackagesByQuery', event.target.value);
    }

}
