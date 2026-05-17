import { Controller } from '@hotwired/stimulus';
import { Modal } from 'bootstrap';

/**
 * Powers the mailing template preview tab:
 *  - Open the HTML or text preview in an iframe modal, injecting the variable key/value pairs as query string.
 *  - Send a sample email through /admin/message/send/{id} and display the response inline.
 *  - Add new variable rows on demand.
 */
export default class extends Controller {
    static targets = ['recipient', 'result', 'variablesTable', 'modalBody'];

    static values = {
        htmlUrl: String,
        textUrl: String,
        sendUrl: String,
    };

    previewHtml() {
        this.openPreview(this.htmlUrlValue);
    }

    previewText() {
        this.openPreview(this.textUrlValue);
    }

    openPreview(baseUrl) {
        if (!baseUrl) {
            return;
        }
        const url = this.appendQuery(baseUrl);
        const modalEl = document.getElementById('message-preview-modal');
        if (!modalEl) {
            return;
        }
        if (this.hasModalBodyTarget) {
            this.modalBodyTarget.innerHTML = `<iframe src="${url}" style="width:100%;height:70vh;border:0"></iframe>`;
        }
        const modal = Modal.getInstance(modalEl) ?? new Modal(modalEl);
        modal.show();
    }

    sendSample() {
        if (!this.hasSendUrlValue || !this.hasRecipientTarget) {
            return;
        }
        const recipient = this.recipientTarget.value.trim();
        if (recipient === '') {
            this.setResult(false, 'Recipient email is required');
            return;
        }

        const formData = new FormData();
        formData.append('recipient_email', recipient);
        for (const [key, value] of this.collectVariables()) {
            formData.append(key, value);
        }

        fetch(this.sendUrlValue, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData,
        })
            .then((response) => response.text())
            .then((text) => this.setResult(true, text))
            .catch((error) => this.setResult(false, error.message));
    }

    addVariable() {
        if (!this.hasVariablesTableTarget) {
            return;
        }
        const tbody = this.variablesTableTarget.querySelector('tbody');
        if (!tbody) {
            return;
        }
        const row = document.createElement('tr');
        row.className = 'url-variable';
        row.innerHTML = `
            <td><input type="text" class="form-control form-control-sm" data-bo-message-preview-target="varKey"></td>
            <td><input type="text" class="form-control form-control-sm" data-bo-message-preview-target="varValue"></td>
            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" data-action="bo-message-preview#removeVariable"><i class="bi bi-trash"></i></button></td>
        `;
        tbody.appendChild(row);
    }

    removeVariable(event) {
        const row = event.currentTarget.closest('tr');
        if (row) {
            row.remove();
        }
    }

    appendQuery(url) {
        const params = this.collectVariables();
        if (params.length === 0) {
            return url;
        }
        const target = new URL(url, window.location.origin);
        for (const [key, value] of params) {
            target.searchParams.set(key, value);
        }
        return target.toString();
    }

    collectVariables() {
        if (!this.hasVariablesTableTarget) {
            return [];
        }
        const keys = this.variablesTableTarget.querySelectorAll('[data-bo-message-preview-target="varKey"]');
        const values = this.variablesTableTarget.querySelectorAll('[data-bo-message-preview-target="varValue"]');
        const pairs = [];
        keys.forEach((keyInput, index) => {
            const key = keyInput.value.trim();
            const value = values[index]?.value ?? '';
            if (key !== '') {
                pairs.push([key, value]);
            }
        });
        return pairs;
    }

    setResult(success, message) {
        if (!this.hasResultTarget) {
            return;
        }
        this.resultTarget.className = `small ${success ? 'text-success' : 'text-danger'}`;
        this.resultTarget.textContent = message;
    }
}
