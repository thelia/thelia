import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'status', 'list'];

    static values = {
        saveUrl: String,
        listUrl: String,
    };

    connect() {
        this.refresh();
    }

    async submit(event) {
        event.preventDefault();
        if (!this.hasInputTarget || !this.inputTarget.files || this.inputTarget.files.length === 0) {
            return;
        }

        const formData = new FormData();
        formData.append('file', this.inputTarget.files[0]);

        this.showStatus('uploading');
        try {
            const response = await fetch(this.saveUrlValue, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: formData,
            });
            if (!response.ok) {
                throw new Error(await response.text());
            }
            this.inputTarget.value = '';
            this.showStatus('success');
            this.refresh();
        } catch (e) {
            this.showStatus('error', e.message);
        }
    }

    async refresh() {
        if (!this.listUrlValue || !this.hasListTarget) {
            return;
        }

        try {
            const response = await fetch(this.listUrlValue, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
            });
            if (response.ok) {
                this.listTarget.innerHTML = await response.text();
            }
        } catch (e) {
            // silent
        }
    }

    showStatus(kind, message) {
        if (!this.hasStatusTarget) {
            return;
        }
        this.statusTarget.hidden = false;
        const label = kind === 'uploading' ? 'Uploading…' : kind === 'success' ? '✓' : (message || 'Error');
        const cls = kind === 'error' ? 'alert alert-danger small' : 'alert alert-info small';
        this.statusTarget.className = cls;
        this.statusTarget.textContent = label;

        if (kind !== 'uploading') {
            setTimeout(() => {
                this.statusTarget.hidden = true;
            }, 2000);
        }
    }
}
