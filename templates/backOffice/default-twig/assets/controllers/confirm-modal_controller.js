import { Controller } from '@hotwired/stimulus';

/**
 * Confirm a destructive action via the browser native confirm dialog.
 * Cancels the event if the user dismisses.
 *
 * Usage:
 *   <button data-controller="confirm-modal"
 *           data-action="click->confirm-modal#confirm"
 *           data-confirm-modal-message-value="Are you sure?">
 *     Delete
 *   </button>
 */
export default class extends Controller {
    static values = {
        message: { type: String, default: 'Are you sure?' },
    };

    confirm(event) {
        if (!window.confirm(this.messageValue)) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }
}
