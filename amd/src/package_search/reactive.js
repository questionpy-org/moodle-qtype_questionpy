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
 * @module qtype_questionpy/package_search/reactive
 */

import {Reactive} from 'core/reactive';

import SearchMutations from 'qtype_questionpy/package_search/mutations';
import {eventNames, notifyStateChanged} from 'qtype_questionpy/package_search/events';
import Container from 'qtype_questionpy/package_search/components/container';
import SearchBar from 'qtype_questionpy/package_search/components/search_bar';

let counter = 0;

export default class extends Reactive {
    /**
     * Reactive element used for package search.
     *
     * @param {HTMLDivElement} target
     * @param {{contextid: number, limit: number}} options
     */
    constructor(target, options) {
        super({
            name: `PackageSearch${counter++}`,
            eventName: eventNames.stateChanged,
            eventDispatch: notifyStateChanged,
            target: target,
            mutations: new SearchMutations(options),
            state: {
                general: {
                    loading: true,
                    selected: "all",
                    sort: "alpha",
                    order: "asc",
                    query: "",
                },
                all: {
                    data: {
                        packages: [],
                        count: 0,
                        total: 0,
                    },
                    page: 0,
                },
                recentlyused: {
                    data: {
                        packages: [],
                        count: 0,
                        total: 0,
                    },
                    page: 0,
                },
                favourites: {
                    data: {
                        packages: [],
                        count: 0,
                        total: 0,
                    },
                    page: 0,
                },
                mine: {
                    data: {
                        packages: [],
                        count: 0,
                        total: 0,
                    },
                    page: 0,
                },
            },
        });
        this.options = options;
    }

    /**
     * Loads every component of the package search area.
     */
    load() {
        this.searchBar = new SearchBar({
            element: this.target.getElementsByClassName("qpy-package-search-search-bar")[0],
            name: "search_bar",
            reactive: this
        });
        this.packageContainer = new Container({
            element: this.target.getElementsByClassName("qpy-package-search-container")[0],
            name: "container",
            reactive: this
        });
    }
}
