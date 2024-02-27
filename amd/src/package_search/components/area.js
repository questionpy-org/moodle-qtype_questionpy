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
 * @module qtype_questionpy/package_search/components/area
 */

import Component from 'qtype_questionpy/package_search/component';
import Container from 'qtype_questionpy/package_search/components/container';
import SearchBar from 'qtype_questionpy/package_search/components/search_bar';

export default class extends Component {
    getWatchers() {
        return [
            {watch: `general.loading:updated`, handler: this.updateStatus},
        ];
    }

    create(descriptor) {
        // Register search bar.
        // TODO: register component inside mustache template.
        new SearchBar({
            element: this.getElement('[data-for="search-bar-container"'),
            name: "search_bar",
            reactive: descriptor.reactive,
        });
        // Register package container.
        // TODO: register component inside mustache template.
        new Container({
            element: this.getElement('[data-for="package-container"'),
            name: "container",
            reactive: descriptor.reactive,
        });
    }

    stateReady() {
        // Initial loading of the packages.
        this.reactive.dispatch("searchPackages");
    }

    /**
     * Adds or removes the `qpy-loading` class from the search area.
     */
    updateStatus() {
        const loading = this.getState().general.loading;
        this.element.classList.toggle("qpy-loading", loading);
    }
}
