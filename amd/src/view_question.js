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

import $ from "jquery";
import "theme_boost/bootstrap/popover";

/**
 * If the given input(-like) element is labelled, returns the label element. Returns null otherwise.
 *
 * @param {HTMLElement} input
 * @return {HTMLLabelElement | null}
 * @see {@link https://html.spec.whatwg.org/multipage/forms.html#the-label-element}
 */
function getLabelFor(input) {
    // A label can reference its labeled control in its for attribute.
    const id = input.id;
    if (id !== "") {
        const label = document.querySelector(`label[for='${id}']`);
        if (label) {
            return label;
        }
    }

    // Or the labeled control can be a descendant of the label.
    const label = input.closest("label");
    if (label) {
        return label;
    }

    return null;
}

/**
 * Marks the given input element as invalid.
 *
 * @param {HTMLElement} element
 * @param {string} message validation message to show
 * @param {boolean} ariaInvalid
 */
function markInvalid(element, message, ariaInvalid = true) {
    element.classList.add("is-invalid");
    if (ariaInvalid) {
        element.setAttribute("aria-invalid", "true");
    } else {
        element.removeAttribute("aria-invalid");
    }

    let popoverTarget = element;
    if (element.type === "checkbox" || element.type === "radio") {
        // Checkboxes and radios make for a very small hit area for the popover, so we attach the popover to the label.
        const label = getLabelFor(element);
        if (!label) {
            // No label -> Add the popover just to the checkbox.
            popoverTarget = element;
        } else if (label.contains(element)) {
            // Label contains checkbox -> Add the popover just to the label.
            popoverTarget = label;
        } else {
            // Separate label and checkbox -> Add the popover to both.
            popoverTarget = [element, label];
        }
    }

    $(popoverTarget).popover({
        toggle: "popover",
        trigger: "hover",
        content: message
    });
}

/**
 * Undoes what {@link markInvalid} did.
 *
 * @param {HTMLInputElement} element
 */
function unmarkInvalid(element) {
    element.classList.remove("is-invalid");
    element.removeAttribute("aria-invalid");

    $([element, getLabelFor(element)]).popover("dispose");
}

/**
 * Softly (i.e. without preventing form submission) validates constraints on the given element.
 *
 * @param {HTMLInputElement} element
 */
async function checkConstraints(element) {
    /* Our goal here is to show helpful localised validation messages without actually preventing form submission.
       One way to achieve this would be to add the attribute "novalidate" to the form element, but that might interfere
       with other questions (since they share the same form).
       We also don't want to reimplement the validation logic already implemented by browsers.
       Instead, the standard validation attributes are added, their validity checked, the message used to create a
       popover, and the attributes removed. */
    try {
        if ("qpy_required" in element.dataset) {
            element.setAttribute("required", "required");
        }
        for (const attr of ["pattern", "minlength", "maxlength", "min", "max"]) {
            if (`qpy_${attr}` in element.dataset) {
                element.setAttribute(attr, element.dataset[`qpy_${attr}`]);
            }
        }

        const isValid = element.checkValidity();
        if (isValid) {
            unmarkInvalid(element);
        } else {
            // Aria-invalid shouldn't be set for missing inputs until the user has tried to submit them.
            // https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/Attributes/aria-invalid
            markInvalid(element, element.validationMessage, !element.validity.valueMissing);
        }
    } finally {
        for (const attr of ["required", "pattern", "minlength", "maxlength", "min", "max"]) {
            element.removeAttribute(attr);
        }
    }
}

/**
 * Adds change event handlers for soft validation.
 */
export async function init() {
    for (const element of document.querySelectorAll(`
        [data-qpy_required], [data-qpy_pattern], 
        [data-qpy_minlength], [data-qpy_maxlength], 
        [data-qpy_min], [data-qpy_max]
    `)) {
        await checkConstraints(element);
        element.addEventListener("change", event => checkConstraints(event.target));
    }
}
