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
 * @module qtype_questionpy/package_search/components/tag_bar
 */

import Component from 'qtype_questionpy/package_search/component';
import Autocomplete from 'core/form-autocomplete';
import {debounce} from 'core/utils';
import {getString} from 'core/str';

export default class extends Component {
    create() {
        this.selectors = {
            TAG_BAR: '[data-for="tag-bar"] > select',
        };
    }

    async stateReady() {
        this.addEventListener(
            this.getElement(this.selectors.TAG_BAR),
            'change',
            debounce((event) => this.filterPackages(event.target.selectedOptions), 300)
        );

        Autocomplete.enhance(
            // The selector to the select element.
            this.selectors.TAG_BAR,
            // No custom words allowed.
            false,
            // We want to use ajax.
            'qtype_questionpy/package_search/components/_tag_bar_async',
            // The placeholder.
            await getString('tag_bar', 'qtype_questionpy'),
            // We do not want to be case-sensitive.
            false,
            // We want to show suggestions.
            true,
            // If no selection is made, show this string.
            await getString('tag_bar_no_selection', 'qtype_questionpy'),
            // Close suggestion.
            true,
            // We want to overwrite the layout.
            {
                layout: 'qtype_questionpy/package_search/tag_bar/layout',
                selection: 'qtype_questionpy/package_search/tag_bar/selection',
            },
        );
    }

  /**
   * Dispatches package filter by tag mutation.
   *
   * @param {HTMLCollectionOf<HTMLOptionElement>} selectedOptions
   */
    filterPackages(selectedOptions) {
        const tags = [];
        for (let i = 0; i < selectedOptions.length; i++) {
            tags.push(selectedOptions[i].value);
        }
        this.reactive.dispatch('filterPackagesByTags', tags);
    }
}
