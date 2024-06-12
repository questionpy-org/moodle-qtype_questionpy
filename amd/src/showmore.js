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
 * @module qtype_questionpy/showmore
 */

import $ from "jquery";


/**
 * Return `true` if the element is overflowing.
 *
 * @param {jQuery} element
 * @returns {boolean}
 */
const isOverflowing = (element) => {
    return element[0].scrollHeight > element.height();
};


/**
 * Initializes the dynamic description box.
 *
 * @param {string} id
 */
export const init = (id) => {
    const element = $(`#${id}`);

    const container = element.find("div");
    const button = element.find("button");

    window.addEventListener("resize", () => {
        button.toggleClass("d-none", !isOverflowing(container));
    });

    button.toggleClass("d-none", !isOverflowing(container));

    const showLessButton = element.find(".qpy-show-less-btn");
    const showMoreButton = element.find(".qpy-show-more-btn");

    element.on("show.bs.collapse", () => {
        showLessButton.toggleClass("d-none", false);
        showMoreButton.toggleClass("d-none", true);
    });
    element.on("hide.bs.collapse", () => {
        showLessButton.toggleClass("d-none", true);
        showMoreButton.toggleClass("d-none", false);
    });
};
