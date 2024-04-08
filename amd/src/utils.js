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
 * @module qtype_questionpy/utils
 */

import Ajax from 'core/ajax';


/**
 * Favourites a package.
 *
 * @param {number} packageid
 * @param {boolean} favourite
 * @param {number} contextid
 * @returns {Promise<boolean>}
 */
export const favouritePackage = async(packageid, favourite, contextid) => {
    return await Ajax.call([{
        methodname: "qtype_questionpy_favourite_package",
        args: {
            packageid: packageid,
            favourite: favourite,
            contextid: contextid,
        },
    }])[0];
};