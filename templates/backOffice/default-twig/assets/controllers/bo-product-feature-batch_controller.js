import { Controller } from '@hotwired/stimulus';

/**
 * Batch helpers for the product attributes/features tab. Allows selecting or
 * clearing every option in every feature multi-select at once, and provides
 * a per-row "clear selected values" link that mirrors the legacy Smarty link.
 */
export default class extends Controller {
    static targets = ['table', 'select'];

    selectAll(event) {
        event.preventDefault();
        this.selectTargets.forEach((select) => {
            Array.from(select.options).forEach((option) => {
                option.selected = true;
            });
        });
    }

    deselectAll(event) {
        event.preventDefault();
        this.selectTargets.forEach((select) => {
            Array.from(select.options).forEach((option) => {
                option.selected = false;
            });
        });
    }

    clearOne(event) {
        event.preventDefault();
        const selector = event.currentTarget.dataset.targetSelect;
        if (!selector) {
            return;
        }
        const select = document.querySelector(selector);
        if (!select) {
            return;
        }
        Array.from(select.options).forEach((option) => {
            option.selected = false;
        });
    }
}
