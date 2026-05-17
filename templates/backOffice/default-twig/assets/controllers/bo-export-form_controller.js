import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['toggle', 'archiver'];

    toggle() {
        if (!this.hasArchiverTarget) {
            return;
        }
        const isChecked = this.hasToggleTarget && this.toggleTarget.checked;
        this.archiverTarget.classList.toggle('d-none', !isChecked);
    }
}
