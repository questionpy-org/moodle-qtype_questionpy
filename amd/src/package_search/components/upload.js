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
 * @module qtype_questionpy/package_search/components/upload
 */

import Component from 'qtype_questionpy/package_search/component';

import $ from "jquery";
import ModalForm from 'core_form/modalform';
import * as strings from 'core/str';

export default class extends Component {
    stateReady() {
        this.addEventListener(this.element, "click", this.openUploadForm);
    }

    /**
     * Opens the upload form.
     *
     * @param {MouseEvent} event
     */
    openUploadForm(event) {
        const element = event.target;
        // TODO: reuse ModalForm?
        const modalForm = new ModalForm({
            formClass: "qtype_questionpy\\form\\package_upload",
            args: {contextid: this.reactive.options.contextid},
            modalConfig: {
                title: strings.get_string("upload_package", "qtype_questionpy"),
            },
            saveButtonText: strings.get_string("upload"),
            returnFocus: element,
        });
        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, () => this._packageWasUploaded());
        modalForm.show();
    }

    _packageWasUploaded() {
        this.reactive.dispatch("packageUploaded");
        $(this.reactive.target).find('[data-for="custom-header"]').tab('show');
    }
}
