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

import "theme_boost/bootstrap/popover";
import {throttle} from "core/utils";

/**
 * @type {?Attempt} Attempt object that is passed to the question package.
 */
let attempt = null;

/**
 * Validates constraints on the given element and adds / removes Bootstrap's `is-invalid` class.
 *
 * Bootstrap 4 will automatically mark valid and invalid inputs when a parent has the `was-validated` class, but that
 * will also display a check mark for inputs which aren't invalid. Since that might suggest that the entered value is
 * correct (which isn't checked here), we don't use that feature.
 *
 * The `aria-invalid` attribute is also added or removed.
 *
 * @param {HTMLInputElement} element
 */
function validateInput(element) {
    const isValid = element.checkValidity();
    if (isValid) {
        element.classList.remove("is-invalid");
        element.removeAttribute("aria-invalid");
    } else {
        // Aria-invalid shouldn't be set for missing inputs until the user has tried to submit them.
        // https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/Attributes/aria-invalid
        element.classList.add("is-invalid");
        if (!element.validity.valueMissing) {
            element.setAttribute("aria-invalid", "true");
        } else {
            element.removeAttribute("aria-invalid");
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
 * @param {string} responseId
 * @param {string[]} roles QPy role names that the user has.
 * @param {Number} environmentVersion
 */
export async function init(
    readOnly,
    showGeneralFeedback,
    showSpecificFeedback,
    showRightAnswer,
    showCorrectness,
    responseId,
    roles,
    environmentVersion,
) {
    for (const element of document.querySelectorAll(`
        [required], [pattern],
        [minlength], [maxlength],
        [min], [max]
    `)) {
        element.addEventListener("change", event => validateInput(event.target));
    }

    const form = window.document.getElementById("qpy-formulation");
    if (form) {
        // On form submit, submit the quiz's main form in the parent window instead.
        form.addEventListener("submit", event => {
            event.preventDefault();
            window.frameElement.closest("form").submit();
        });

        // Modify a field in the main form in order to tell the Quiz's autosaver that the user changed an answer.
        const responseElement = parent.document.getElementById(responseId);
        if (responseElement) {
            // We throttle here, as `JSON.stringify` might affect the performance.
            form.addEventListener("change", throttle(() => {
                responseElement.value = createJsonFromFormData(form);
            }, 250));
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
        roles,
        environmentVersion,
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

/**
 * Contains information about the current environment.
 */
class AttemptEnvironment {
    #name;
    #version;

    /**
     * @param {String} name
     * @param {Number} version
     */
    constructor(name, version) {
        this.#name = name;
        this.#version = version;
    }

    /**
     * Get the name of the current environment.
     *
     * @returns {String}
     */
    get name() {
        return this.#name;
    }

    /**
     * Get the version of the current environment.
     *
     * To make versions trivially comparable, a number is returned. Make sure to check how these are mapped for the
     * different environments. The higher the number, the more recent the version.
     *
     * @returns {Number}
     */
    get version() {
        return this.#version;
    }
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
    #environment;

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
     * @param {Number} environmentVersion
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
        roles,
        environmentVersion,
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
        this.#environment = new AttemptEnvironment("Moodle", environmentVersion);
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

    /**
     * Get information about the current environment.
     *
     * @returns {AttemptEnvironment}
     */
    get environment() {
        return this.#environment;
    }
}

/**
 * Creates JSON from the FormData of the given form.
 *
 * @param {HTMLFormElement} form
 * @returns {string}
 */
function createJsonFromFormData(form) {
    const iframeFormData = new FormData(form);
    const iframeObject = Object.fromEntries(iframeFormData);
    for (const name of iframeFormData.keys()) {
        const values = iframeFormData.getAll(name);
        if (values.length > 1) {
            iframeObject[name] = values;
        }
    }
    return JSON.stringify(iframeObject);
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
        // Since we are throttling the updating process of the response element on a change, it might happen that the
        // value is outdated - this is why we get the data again.
        const jsonFormData = createJsonFromFormData(iframeForm);
        event.formData.set(responseFieldName, jsonFormData);
    });
}
