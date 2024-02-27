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
import Area from 'qtype_questionpy/package_search/components/area';

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
                    sorting: {
                      sort: "alpha",
                      order: "asc",
                    },
                    query: "",
                },
                all: {
                    count: 0,
                    total: 0,
                    page: 0,
                },
                allPackages: [],
                recentlyused: {
                    count: 0,
                    total: 0,
                    page: 0,
                },
                recentlyusedPackages: [],
                favourites: {
                    count: 0,
                    total: 0,
                    page: 0,
                },
                favouritesPackages: [],
                mine: {
                    count: 0,
                    total: 0,
                    page: 0,
                },
                minePackages: [],
            },
        });
        this.options = options;
    }

    /**
     * Loads every component of the package search area.
     */
    load() {
        new Area({
            element: this.target,
            name: "search_area",
            reactive: this
        });
    }
}
