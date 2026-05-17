import { Controller } from '@hotwired/stimulus';

/**
 * Sends a test email through /admin/configuration/mailingSystem/test and displays
 * the success/failure message inline next to the "Send" button.
 */
export default class extends Controller {
    static targets = ['email', 'button', 'result'];

    static values = {
        url: String,
    };

    send() {
        if (!this.hasUrlValue || !this.hasEmailTarget) {
            return;
        }
        const email = this.emailTarget.value.trim();
        if (email === '') {
            this.display(false, 'Please enter an email');
            return;
        }

        if (this.hasButtonTarget) {
            this.buttonTarget.disabled = true;
        }
        const url = new URL(this.urlValue, window.location.origin);
        url.searchParams.set('email', email);

        fetch(url.toString(), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then((response) => (response.ok ? response.json() : Promise.reject(new Error(`HTTP ${response.status}`))))
            .then((data) => {
                this.display(Boolean(data?.success), String(data?.message ?? ''));
            })
            .catch((error) => {
                this.display(false, error.message);
            })
            .finally(() => {
                if (this.hasButtonTarget) {
                    this.buttonTarget.disabled = false;
                }
            });
    }

    display(success, message) {
        if (!this.hasResultTarget) {
            return;
        }
        this.resultTarget.className = `small ${success ? 'text-success' : 'text-danger'}`;
        this.resultTarget.textContent = message;
    }
}
