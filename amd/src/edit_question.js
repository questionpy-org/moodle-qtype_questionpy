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
 * Event handler to be called when the user selects or uploads a package.
 *
 * Enables the hidden form element <code>package_changed</code> and submits the form.
 * Since <code>package_changed</code> is registered as a no-submit button, it prevents the form data from being saved to
 * the question, while still re-rendering the form with access to the new selected package hash.
 *
 * @param {Event} e
 */
function onPackageChange(e) {
    document.getElementsByName("package_changed")[0]
        .removeAttribute("disabled");
    e.target.form.submit();
}

/**
 * This function is required by <code>qtype_questionpy::display_question_editing_page()</code>.
 */
export function init() {
    document.getElementsByName("qpy_package_hash")
        .forEach(radio => radio.addEventListener("change", onPackageChange));

    document.getElementsByName("qpy_package")[0]
        .addEventListener("change", onPackageChange);
}
