import { Controller } from '@hotwired/stimulus';

/**
 * Filter a multi-select with a search box: options whose label does not match
 * the input are hidden client-side. Selected options stay visible to avoid
 * losing the current state when the operator switches.
 */
export default class extends Controller {
    static targets = ['input', 'select'];

    filter() {
        const term = (this.inputTarget.value || '').trim().toLocaleLowerCase();
        if (!this.hasSelectTarget) {
            return;
        }

        Array.from(this.selectTarget.options).forEach((option) => {
            const text = (option.text || '').toLocaleLowerCase();
            const matches = term === '' || text.includes(term);
            option.hidden = !matches && !option.selected;
        });
    }
}
