import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['grid', 'item'];

    static values = {
        toggleUrlTemplate: String,
        deleteUrlTemplate: String,
        positionUrl: String,
    };

    async toggle(event) {
        const id = String(event.params.id);
        const url = (this.toggleUrlTemplateValue || '').replace(/\/0(?=\/toggle|$)/, `/${id}`);

        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (response.ok) {
                const button = event.currentTarget;
                const icon = button.querySelector('i');
                if (icon) {
                    icon.classList.toggle('bi-eye');
                    icon.classList.toggle('bi-eye-slash');
                }
            }
        } catch (e) {
            // silent
        }
    }

    async delete(event) {
        const id = String(event.params.id);
        if (!confirm('Are you sure?')) {
            return;
        }
        const url = (this.deleteUrlTemplateValue || '').replace(/\/0$/, `/${id}`).replace(/\/0(?=\/)/, `/${id}`);

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (response.ok) {
                const item = event.currentTarget.closest('[data-bo-file-list-target="item"]');
                if (item) {
                    item.remove();
                }
            }
        } catch (e) {
            // silent
        }
    }
}
