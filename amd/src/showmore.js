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


/**
 * Return `true` if the element is overflowing.
 *
 * @param {HTMLElement} element
 * @returns {boolean}
 */
const isOverflowing = (element) => {
    return element.scrollHeight > element.clientHeight;
};


/**
 * Initializes the dynamic description box.
 *
 * @param {string} id
 */
export const init = (id) => {
    const element = document.getElementById(id);

    const container = element.querySelector(".qpy-show-more-container");
    const button = element.querySelector(".qpy-show-more-button");

    window.addEventListener("resize", () => {
        if (button.classList.contains("collapsed")) {
            button.classList.toggle("d-none", !isOverflowing(container));
        }
    });

    button.classList.toggle("d-none", !isOverflowing(container));
};
