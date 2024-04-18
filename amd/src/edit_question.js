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

import {resetFormDirtyState} from 'core_form/changechecker';
import Notification from 'core/notification';
import {favouritePackage} from 'qtype_questionpy/utils';

/**
 * This function is called by the <code>package_selection</code>-template and initializes the action button.
 *
 * When the package is changed, this function enables the hidden form element <code>qpy_package_changed</code> and
 * submits the form. Since <code>qpy_package_changed</code> is registered as a no-submit button, it prevents the form
 * data from being saved to the question, while still re-rendering the form with access to the new selected package
 * hash.
 *
 * @param {HTMLDivElement} card
 * @param {boolean} selected
 */
export function initActionButton(card, selected) {
    const packageChanged = document.querySelector('input[name="qpy_package_changed"]');
    const packageHash = document.querySelector('input[name="qpy_package_hash"]');

    if (selected) {
        // Initialize the button to change the package.
        const changeButton = card.getElementsByClassName("qpy-version-selection-button")[0];
        changeButton.addEventListener("click", (e) => {
            e.preventDefault();

            // Remove package hash.
            packageChanged.removeAttribute("disabled");
            packageHash.value = '';

            // We do not want any form checking when changing a package.
            resetFormDirtyState(changeButton);
            e.target.form.submit();
        });
    } else {
        const selectedHash = card.getElementsByClassName("qpy-version-selection")[0];
        const selectButton = card.getElementsByClassName("qpy-version-selection-button")[0];

        selectButton.addEventListener("click", (e) => {
            e.preventDefault();

            // Set package hash.
            packageChanged.removeAttribute("disabled");
            packageHash.value = selectedHash.value;

            // We do not want any form checking when selecting a package.
            resetFormDirtyState(selectButton);
            e.target.form.submit();
        });
    }
}

/**
 * This function is called by the <code>package_selection</code>-template and initializes the favourite button, when
 * a package is already selected.
 *
 * @param {HTMLDivElement} card
 * @param {number} packageId
 */
export function initFavouriteButton(card, packageId) {
    const button = card.querySelector('[data-for="favourite-button"]');
    if (!button) {
        return;
    }
    const isFavouriteAttributeName = "data-is-favourite";
    button.addEventListener("click", async() => {
        try {
            const isFavourite = button.hasAttribute(isFavouriteAttributeName);
            const successful = await favouritePackage(packageId, !isFavourite);
            if (successful) {
                button.toggleAttribute(isFavouriteAttributeName, !isFavourite);
            }
        } catch (exception) {
            await Notification.exception(exception);
        }
    });
}