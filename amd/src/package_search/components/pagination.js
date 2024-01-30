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
 * @module qtype_questionpy/package_search/components/pagination
 */

import Component from 'qtype_questionpy/package_search/component';

export default class extends Component {
    getWatchers() {
        return [
            {watch: `${this.category}.data:updated`, handler: this.updateCurrentPage},
        ];
    }

    create(descriptor) {
        this.category = descriptor.category;
        this.selectors = {
            PREVIOUS_BUTTON: `[data-for="pagination-previous"]`,
            NEXT_BUTTON: `[data-for="pagination-next"]`,
            CURRENT: `[data-for="pagination-current"]`,
        };
    }

    stateReady() {
        this.addEventListener(this.getElement(this.selectors.PREVIOUS_BUTTON), "click", this.previousPage);
        this.addEventListener(this.getElement(this.selectors.NEXT_BUTTON), "click", this.nextPage);
    }

    /**
     * Returns the current page number starting form zero.
     *
     * @returns {number}
     * @private
     */
    _getCurrentPage() {
        return this.getState()[this.category].page;
    }

    /**
     * Returns the last page number starting from zero.
     *
     * @returns {number}
     * @private
     */
    _getLastPage() {
        const state = this.getState()[this.category];
        if (state.data.total === 0) {
            return 0;
        }
        return Math.floor((state.data.total - 1) / this.reactive.options.limit);
    }

    /**
     * Updates the current page status and disables or enables the previous and next button.
     */
    updateCurrentPage() {
        const currentPage = this._getCurrentPage();
        const lastPage = this._getLastPage();

        this.getElement(this.selectors.CURRENT).innerHTML = `${currentPage + 1} / ${lastPage + 1}`;

        if (currentPage === 0) {
            this.getElement(this.selectors.PREVIOUS_BUTTON).classList.add("disabled");
        } else {
            this.getElement(this.selectors.PREVIOUS_BUTTON).classList.remove("disabled");
        }
        if (currentPage === lastPage) {
            this.getElement(this.selectors.NEXT_BUTTON).classList.add("disabled");
        } else {
            this.getElement(this.selectors.NEXT_BUTTON).classList.remove("disabled");
        }
    }

    /**
     * Dispatches mutation that retrieves packages from the previous page.
     */
    previousPage() {
        const currentPage = this._getCurrentPage();
        if (currentPage > 0) {
            this.reactive.dispatch("changePage", this.category, currentPage - 1);
        }
    }

    /**
     * Dispatches mutation that retrieves packages from the next page.
     */
    nextPage() {
        const currentPage = this._getCurrentPage();
        const lastPage = this._getLastPage();
        if (currentPage < lastPage) {
            this.reactive.dispatch("changePage", this.category, currentPage + 1);
        }
    }

}
