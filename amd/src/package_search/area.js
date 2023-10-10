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
 * @module qtype_questionpy/package_search/area
 */

import PackageSearchReactive from 'qtype_questionpy/package_search/reactive';

/**
 * Initializes a package search area.
 *
 * @param {HTMLDivElement} element
 * @param {{contextid: number, limit: number}} options
 * @returns {Promise<void>}
 */
export const init = async(element, options) => {
    if (!element.packageSearch) {
        element.packageSearch = new PackageSearchReactive(element, options);
        await element.packageSearch.load();
    }
};
