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

import {call as callMany} from 'core/ajax';
import {get_string as getString} from 'core/str';


/**
 * Initializes an action button which calls a service.
 *
 * @param {string} service
 * @param {HTMLElement} button
 * @param {HTMLElement} totalPackagesStatus
 * @param {HTMLElement} loadingIcon
 * @param {HTMLElement} errorStatus
 */
function initActionButton(service, button, totalPackagesStatus, loadingIcon, errorStatus) {
    button.addEventListener('click', e => {
        e.preventDefault();

        // Hide error status if it was visible.
        errorStatus.classList.add('d-none');
        // Show loading icon.
        loadingIcon.classList.remove('d-none');

        // Call service and update status.
        callMany([{
            methodname: service,
            args: {},
            done: data => {
                getString('total_packages', 'qtype_questionpy', data).then(string => {
                    totalPackagesStatus.innerText = string;
                    return true;
                }).catch(() => {
                    return true;
                });
                // Hide loading icon.
                loadingIcon.classList.add('d-none');
            },
            fail: () => {
                // Show error status.
                errorStatus.classList.remove('d-none');
                // Hide loading icon.
                loadingIcon.classList.add('d-none');
            },
        }]);
    });
}

/**
 * Load packages button
 */
export function init() {
    let totalPackagesStatus = document.getElementById('qpy-total-packages');

    // Initialize button to load packages.
    initActionButton(
        'qtype_questionpy_load_packages',
        document.getElementById('qpy-load-packages-button'),
        totalPackagesStatus,
        document.getElementById('qpy-load-packages-icon'),
        document.getElementById('qpy-load-packages-error-message')
    );

    // Initialize button to remove packages.
    initActionButton(
        'qtype_questionpy_remove_packages',
        document.getElementById('qpy-remove-packages-button'),
        totalPackagesStatus,
        document.getElementById('qpy-remove-packages-icon'),
        document.getElementById('qpy-remove-packages-error-message')
    );

}
