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
 * @module qtype_questionpy/package_search/components/_tag_bar_async
 */

import Ajax from 'core/ajax';

/**
 * Source of data for Ajax element.
 *
 * @param {String} selector The selector of the auto complete element.
 * @param {String} query The query string.
 * @param {Function} callback A callback function receiving an array of results.
 * @param {Function} failure A callback function to be called in case of failure, receiving the error message.
 */
export const transport = (selector, query, callback, failure) => {
    const promise = Ajax.call([{
        methodname: 'qtype_questionpy_get_tags',
        args: {
            query: query
        }
    }])[0];

    // eslint-disable-next-line promise/no-callback-in-promise
    return promise.then(callback).catch(failure);
};

/**
 * Process the results for auto complete elements.
 *
 * @param {string} selector The selector of the auto complete element.
 * @param {array} results An array or results.
 * @return {array} New array of results.
 */
export const processResults = (selector, results) => {
    const tags = [];
    for (const result of results) {
        tags.push({
            value: result.id,
            label: result.tag,
        });
    }
    return tags;
};
