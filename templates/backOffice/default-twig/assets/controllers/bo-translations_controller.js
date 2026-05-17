import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { baseUrl: String };

    changeItem(event) {
        const form = this.element.querySelector('form');
        if (!form) {
            return;
        }
        const itemNameInput = form.querySelector('input[name="item_name"], select[name="item_name"]');
        if (itemNameInput) {
            itemNameInput.value = '';
        }
        const partInput = form.querySelector('input[name="module_part"], select[name="module_part"]');
        if (partInput) {
            partInput.value = '';
        }
        form.method = 'get';
        form.action = this.hasBaseUrlValue ? this.baseUrlValue : form.action;
        form.submit();
    }

    submitForm() {
        const form = this.element.querySelector('form');
        if (!form) {
            return;
        }
        form.method = 'get';
        form.action = this.hasBaseUrlValue ? this.baseUrlValue : form.action;
        form.submit();
    }
}
