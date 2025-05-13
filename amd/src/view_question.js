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
 * @type {?Attempt} Attempt object that is passed to the question package.
 */
let attempt = null;

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
        placement: "bottom",
        content: message,
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
 * Initializes the question.
 *
 * This function must be called within the iframe.
 *
 * @param {boolean} readOnly
 * @param {boolean} showGeneralFeedback
 * @param {boolean} showSpecificFeedback
 * @param {boolean} showRightAnswer
 * @param {boolean} showCorrectness
 * @param {string} autoSaveHintInputId
 * @param {string[]} roles QPy role names that the user has.
 */
export async function init(
    readOnly,
    showGeneralFeedback,
    showSpecificFeedback,
    showRightAnswer,
    showCorrectness,
    autoSaveHintInputId,
    roles
) {
    // Add change event handlers for soft validation.
    for (const element of document.querySelectorAll(`
        [data-qpy_required], [data-qpy_pattern],
        [data-qpy_minlength], [data-qpy_maxlength],
        [data-qpy_min], [data-qpy_max]
    `)) {
        await checkConstraints(element);
        element.addEventListener("change", event => checkConstraints(event.target));
    }

    const form = window.document.getElementById("qpy-formulation");
    if (form) {
        // On form submit, submit the quiz's main form in the parent window instead.
        form.addEventListener("submit", event => {
            event.preventDefault();
            window.frameElement.closest("form").submit();
        });

        // Modify a field in the main form in order to tell the Quiz's autosaver that the user changed an answer.
        const autoSaveHintElement = parent.document.getElementById(autoSaveHintInputId);
        if (autoSaveHintElement) {
            form.addEventListener("change", function() {
                autoSaveHintElement.value = parseInt(autoSaveHintElement.value) + 1;
            });
        }
    }

    // Attempt object that is passed to the question package.
    attempt = new Attempt(
        readOnly,
        showGeneralFeedback,
        showSpecificFeedback,
        showRightAnswer,
        showCorrectness,
        window.document.getElementById("qpy-formulation"),
        window.document.getElementById("qpy-general-feedback"),
        window.document.getElementById("qpy-specific-feedback"),
        window.document.getElementById("qpy-right-answer"),
        roles
    );
}

/**
 * Get a QuestionPy attempt.
 *
 * @returns {Attempt}
 */
export function getAttempt() {
    if (attempt === null) {
        throw new Error("Attempt not initialized");
    }
    return attempt;
}

class Attempt {
    #readOnly;
    #showGeneralFeedback;
    #showSpecificFeedback;
    #showRightAnswer;
    #showCorrectness;
    #formulation;
    #generalFeedback;
    #specificFeedback;
    #rightAnswer;
    #roles;

    /**
     * @param {boolean} readOnly
     * @param {boolean} showGeneralFeedback
     * @param {boolean} showSpecificFeedback
     * @param {boolean} showRightAnswer
     * @param {boolean} showCorrectness
     * @param {Element} formulationElement
     * @param {?Element} generalFeedbackElement
     * @param {?Element} specificFeedbackElement
     * @param {?Element} rightAnswer
     * @param {string[]} roles
     */
    constructor(
      readOnly,
      showGeneralFeedback,
      showSpecificFeedback,
      showRightAnswer,
      showCorrectness,
      formulationElement,
      generalFeedbackElement,
      specificFeedbackElement,
      rightAnswer,
      roles
    ) {
        this.#readOnly = readOnly;
        this.#showGeneralFeedback = showGeneralFeedback;
        this.#showSpecificFeedback = showSpecificFeedback;
        this.#showRightAnswer = showRightAnswer;
        this.#showCorrectness = showCorrectness;
        this.#formulation = formulationElement;
        this.#generalFeedback = generalFeedbackElement;
        this.#specificFeedback = specificFeedbackElement;
        this.#rightAnswer = rightAnswer;
        this.#roles = roles;
    }

    /**
     * Whether the question should be displayed as a read-only review.
     *
     * @returns {boolean}
     */
    get readOnly() {
        return this.#readOnly;
    }

    /**
     * Whether the general feedback should be visible.
     *
     * This is typically feedback shown to all students after the question
     * is finished, irrespective of which answer they gave.
     *
     * @returns {boolean}
     */
    get showGeneralFeedback() {
        return this.#showGeneralFeedback;
    }

  /**
   * Whether the specific feedback should be visible.
   *
   * Specific feedback is typically the part of the feedback that changes based on the
   * answer that the student gave.
   *
   * @returns {boolean}
   */
    get showSpecificFeedback() {
        return this.#showSpecificFeedback;
    }

    /**
     * Whether the automatically generated display of what the correct answer is should be visible.
     *
     * @returns {boolean}
     */
    get showRightAnswer() {
        return this.#showRightAnswer;
    }

    /**
     * Whether the student should have what they got right and wrong clearly indicated.
     *
     * @returns {boolean}
     */
    get showCorrectness() {
        return this.#showCorrectness;
    }

    /**
     * Get the top html element where the question's formulation xhtml was inserted.
     *
     * @returns {Element}
     */
    get formulationElement() {
        return this.#formulation;
    }

    /**
     * Get the top html element where the question's general feedback xhtml was inserted (if available).
     *
     * @returns {?Element}
     */
    get generalFeedbackElement() {
        return this.#generalFeedback;
    }

    /**
     * Get the top html element where the question's specific feedback xhtml was inserted (if available).
     *
     * @returns {?Element}
     */
    get specificFeedbackElement() {
        return this.#specificFeedback;
    }

    /**
     * Get the top html element where the question's right answer xhtml was inserted (if available).
     *
     * @returns {?Element}
     */
    get rightAnswerElement() {
        return this.#rightAnswer;
    }

    /**
     * Get the names of the roles that the current user has.
     *
     * @typedef {'teacher' | 'developer' | 'scorer' | 'proctor'} roleName
     * @returns {roleName[]}
     */
    get userRoles() {
        return this.#roles;
    }
}

/**
 * JSON-encodes and adds the question's form data located in the iframe to the main form when it is submitted.
 *
 * This function must be called outside the iframe, on the parent window.
 *
 * @param {string} iframeId - The ID of the question's iframe.
 * @param {string} responseFieldName - The complete field name for the JSON-encoded iframe form data.
 */
export function addIframeFormDataOnSubmit(iframeId, responseFieldName) {
    const iframe = window.document.getElementById(iframeId);
    if (iframe === null) {
        window.console.error(`Could not find question iframe ${iframeId}. Cannot save answers.`);
        return;
    }

    const form = iframe.closest("form");
    form.addEventListener("formdata", event => {
        const iframeForm = iframe.contentDocument.getElementById("qpy-formulation");
        if (iframeForm === null) {
            window.console.error("Could not find form in question iframe " + iframeId);
            return;
        }
        const iframeFormData = new FormData(iframeForm);
        const iframeObject = Object.fromEntries(iframeFormData);
        for (const name of iframeFormData.keys()) {
            const values = iframeFormData.getAll(name);
            if (values.length > 1) {
                iframeObject[name] = values;
            }
        }
        event.formData.append(responseFieldName, JSON.stringify(iframeObject));
    });
}
