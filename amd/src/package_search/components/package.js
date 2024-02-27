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
 * @module qtype_questionpy/package_search/components/package
 */

import Component from 'qtype_questionpy/package_search/component';

export default class extends Component {
    getWatchers() {
        return [
            {watch: `${this.category}Packages[${this.packageid}].isfavourite:updated`, handler: this.favouriteChanged},
        ];
    }

    create(descriptor) {
        this.packageid = descriptor.packageid;
        this.category = descriptor.category;
        this.selectors = {
            FAVOURITE_BUTTON: '[data-for="favourite-button"]',
        };
    }

    isFavourite() {
        return this.getState()[`${this.category}Packages`].get(this.packageid).isfavourite;
    }

    stateReady() {
        this.addEventListener(this.getElement(this.selectors.FAVOURITE_BUTTON), "click", () => {
            this.reactive.dispatch("favourite", this.packageid, !this.isFavourite());
        });
    }

    async favouriteChanged() {
        const isFavourite = this.isFavourite();
        this.getElement(this.selectors.FAVOURITE_BUTTON).toggleAttribute("data-is-favourite", isFavourite);
    }
}
