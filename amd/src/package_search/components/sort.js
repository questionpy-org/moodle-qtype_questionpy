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
 * @module qtype_questionpy/package_search/components/sort
 */

import Component from 'qtype_questionpy/package_search/component';

export default class extends Component {
    getWatchers() {
        return [
            {watch: "general.sorting:updated", handler: this.switchSortSelection},
        ];
    }

    async stateReady() {
        this.addEventListener(this.element, "change", this.changeSort);
    }

    /**
     * Dispatches mutation that retrieves packages with a new sort order.
     *
     * @param {Event} event
     */
    changeSort(event) {
        // Get `sort` and `order` by accessing the dataset of the selected options-element.
        const dataset = event.target.options[event.target.selectedIndex].dataset;
        this.reactive.dispatch("changeSort", dataset.sort, dataset.order);
    }

    switchSortSelection() {
        const {sort, order} = this.reactive.stateManager.state.general.sorting;
        this.getElement(`[data-sort="${sort}"][data-order="${order}"]`).selected = true;
    }
}
