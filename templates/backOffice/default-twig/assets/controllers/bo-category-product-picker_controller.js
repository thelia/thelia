import { Controller } from '@hotwired/stimulus';

/**
 * Mirrors the legacy "select a category, then load its products as accessory
 * candidates" picker from product-related-tab.html. On category change, fetches
 * available accessories (products) for the parent product and populates the
 * dependent product select.
 */
export default class extends Controller {
    static targets = ['category', 'product', 'picker', 'empty'];

    static values = {
        urlTemplate: String,
    };

    connect() {
        if (this.hasCategoryTarget && this.categoryTarget.value) {
            this.refresh(this.categoryTarget.value);
        }
    }

    categoryChanged(event) {
        this.refresh(event.target.value);
    }

    refresh(categoryId) {
        if (!categoryId || categoryId === '') {
            this.hide(this.pickerTarget);
            this.hide(this.emptyTarget);
            return;
        }

        const url = this.urlTemplateValue.replace(/\/0\.json$/, `/${categoryId}.json`);
        fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.json();
            })
            .then((items) => {
                this.populate(items);
            })
            .catch(() => {
                this.populate([]);
            });
    }

    populate(items) {
        if (!this.hasProductTarget) {
            return;
        }

        while (this.productTarget.options.length > 1) {
            this.productTarget.remove(1);
        }

        if (!Array.isArray(items) || items.length === 0) {
            this.hide(this.pickerTarget);
            this.show(this.emptyTarget);
            return;
        }

        items.forEach((item) => {
            const option = document.createElement('option');
            option.value = String(item.id);
            const ref = item.ref ? ` (${item.ref})` : '';
            option.textContent = `${item.title}${ref}`;
            this.productTarget.appendChild(option);
        });

        this.hide(this.emptyTarget);
        this.show(this.pickerTarget);
    }

    show(element) {
        if (element) {
            element.classList.remove('d-none');
        }
    }

    hide(element) {
        if (element) {
            element.classList.add('d-none');
        }
    }
}
