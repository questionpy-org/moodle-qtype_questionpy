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
import {eventTypes} from "core_form/events";
import {dispatchEvent} from "core/event_dispatcher";

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
 * @param {string} responseId Id of the hidden input containing the JSON-encoded main response.
 * @param {string} editorsId Id of the hidden input containing the JSON-encoded WYSIWYG editors data.
 * @param {string[]} editorNames Names of the WYSIWYG editors.
 * @param {string[]} roles QPy role names that the user has.
 * @param {Object.<string, any>} data Dynamic data.
 * @param {Number} environmentVersion
 */
export async function init(
    readOnly,
    showGeneralFeedback,
    showSpecificFeedback,
    showRightAnswer,
    showCorrectness,
    responseId,
    editorsId,
    editorNames,
    roles,
    data,
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

        // Since we cannot directly access the attempt object from outside the iframe, we set the `data` field here.
        form.addEventListener("formdata", event => {
            event.formData.set("data", JSON.stringify(attempt.data));
        });

        // Modify a field in the main form in order to tell the Quiz's autosaver that the user changed an answer.
        const responseElement = parent.document.getElementById(responseId);
        const editorsElement = parent.document.getElementById(editorsId);
        if (responseElement || editorsElement) {
            // We throttle here, as `JSON.stringify` might affect the performance.
            form.addEventListener("change", throttle(() => {
                const [responseData, editorsData] = collectFormData(form, editorNames);
                if (responseElement) {
                    responseElement.value = responseData;
                }
                if (editorsElement) {
                    editorsElement.value = editorsData;
                }
            }, 250));
        }

        // Filemanager doesn't use the "change" event, so the above doesn't cover it.
        // Instead, we "forward" its specific event to the parent DOM.
        form.addEventListener(eventTypes.uploadChanged, () => {
            dispatchEvent(
                eventTypes.uploadChanged,
                {},
                window.frameElement.closest("form"),
                {
                    bubbles: true,
                    cancelable: false,
                    composed: false
                }
            );
        });
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
        data,
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
 * Creates a proxy which fires the given callback when the properties of the object are modified.
 *
 * @template T, U
 * @param {Object.<T, U>} obj
 * @param {() => void} onChange
 * @returns {Object.<T, U>}
 */
function createChangeNotifyingProxy(obj, onChange) {
    return new Proxy(obj, {
        set(target, propertyKey, newValue, receiver) {
            const success = Reflect.set(target, propertyKey, newValue, receiver);
            onChange();
            return success;
        },
        defineProperty(target, propertyKey, attributes) {
            const success = Reflect.defineProperty(target, propertyKey, attributes);
            onChange();
            return success;
        },
        deleteProperty(target, propertyKey) {
            const success = Reflect.deleteProperty(target, propertyKey);
            onChange();
            return success;
        },
    });
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
    #data;
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
     * @param {Object.<string, any>} data
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
        data,
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

        // We also want the autosaver to act when dynamic data was changed.
        const callback = () => this.formulationElement.dispatchEvent(new Event("change"));
        this.#data = createChangeNotifyingProxy(data, callback);

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
     * Get the object used to store dynamic data.
     *
     * @note
     * This object will be serialized to JSON by using `JSON.stringify` and therefore follows the conversion
     * rules of this function. This also means that the keys of (nested) objects will be converted to
     * strings.
     *
     * The depth of the object should not exceed 16.
     *
     * @returns {Object.<string, any>}
     */
    get data() {
        return this.#data;
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

function buildDraftFileUrlRegex() {
    const wwwrootWithoutScheme = M.cfg.wwwroot.replace(/^https?:\/\//, "");

    return new RegExp(
        // Phpcs:disable -- phpcs is massively confused by this.
        String.raw`https?://${wwwrootWithoutScheme}/draftfile\.php/(?<contextid>\d+)`
        + String.raw`/user/draft/(?<itemid>\d+)/(?<filename>[^\'\",&<>|\`\s:\\\\]+)`
        // Phpcs:enable
    )
}

/**
 * Creates JSON from the FormData of the given form.
 *
 * @param {HTMLFormElement} form
 * @param {string[]} editorNames
 * @returns {[string, string]}
 */
function collectFormData(form, editorNames) {
    const iframeFormData = new FormData(form);

    const editorData = {};
    for (const name of editorNames) {
        // TODO: Turn draftfile.php-URLs into @@PLUGINFILE@@-URLs.

        const textKey = `${name}[text]`;
        const formatKey = `${name}[format]`;
        const itemidKey = `${name}[itemid]`;

        const text = iframeFormData.get(textKey);
        if (text === null) {
            continue;
        }

        // TODO: Handle content pasted from other editors, where the draft item id would be different. We'd probably
        // need to pass the encountered foreign files somewhere and copy them to our area in qbehaviour_questionpy.
        const replacedText = text.replaceAll(buildDraftFileUrlRegex(), "@@PLUGINFILE@@/$<filename>");

        editorData[name] = {text: replacedText};
        iframeFormData.delete(textKey);

        const format = iframeFormData.get(formatKey);
        if (format !== null) {
            editorData[name].format = format;
            iframeFormData.delete(formatKey);
        }

        // The itemid is added by qpy_rich_text_editor to the list that gets sent from outside the iframe.
        // No need to duplicate it here.
        iframeFormData.delete(itemidKey);
    }

    const responseObject = Object.fromEntries(iframeFormData);
    for (const name of iframeFormData.keys()) {
        const values = iframeFormData.getAll(name);
        if (values.length > 1) {
            responseObject[name] = values;
        }
    }

    if (responseObject.data) {
        responseObject.data = JSON.parse(responseObject.data);
    } else {
        window.console.warn("The form data field 'data' is missing in the question iframe form.");
    }

    return [JSON.stringify(responseObject), JSON.stringify(editorData)];
}

/**
 * JSON-encodes and adds the question's form data located in the iframe to the main form when it is submitted.
 *
 * This function must be called outside the iframe, on the parent window.
 *
 * @param {string} iframeId - The ID of the question's iframe.
 * @param {string} responseFieldName - The complete field name for the JSON-encoded iframe form data.
 * @param {string} editorsFieldName - The complete field name for the JSON-encoded WYSIWYG editors data.
 * @param {string[]} editorNames - The input names that are WYSIWYG editors.
 */
export function addIframeFormDataOnSubmit(iframeId, responseFieldName, editorsFieldName, editorNames) {
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
        const [responseData, editorsData] = collectFormData(iframeForm, editorNames);
        event.formData.set(responseFieldName, responseData);
        event.formData.set(editorsFieldName, editorsData);
    });
}
