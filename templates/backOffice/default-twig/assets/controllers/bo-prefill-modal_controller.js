import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input'];

    static values = {
        sourceTrigger: String,
        targetInput: String,
    };

    connect() {
        this.boundOnShow = this.onShow.bind(this);
        this.element.addEventListener('show.bs.modal', this.boundOnShow);
    }

    disconnect() {
        this.element.removeEventListener('show.bs.modal', this.boundOnShow);
    }

    onShow(event) {
        const trigger = event.relatedTarget;
        if (!trigger) {
            return;
        }

        const value = trigger.getAttribute(this.sourceTriggerValue);
        if (value === null) {
            return;
        }

        if (this.hasInputTarget) {
            this.inputTarget.value = value;
        }
    }
}
