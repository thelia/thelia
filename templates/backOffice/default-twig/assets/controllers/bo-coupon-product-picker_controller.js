import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['category', 'product', 'productMulti'];

    static values = {
        data: Object,
        initialProduct: String,
        initialMulti: Array,
    };

    connect() {
        if (this.hasCategoryTarget && this.hasProductTarget) {
            this.refreshSingle(this.categoryTarget.value);
        }
    }

    onCategoryChange(event) {
        this.refreshSingle(event.target.value);
    }

    refreshSingle(categoryId) {
        if (!this.hasProductTarget) {
            return;
        }

        const select = this.productTarget;
        const data = this.dataValue || {};
        const products = data[categoryId] || data[String(categoryId)] || [];

        select.innerHTML = '<option value="">— Select a product —</option>';
        products.forEach((p) => {
            const opt = document.createElement('option');
            opt.value = String(p.id);
            opt.textContent = p.title;
            if (String(this.initialProductValue || '') === String(p.id)) {
                opt.selected = true;
            }
            select.appendChild(opt);
        });
    }

    onCategoryChangeMulti(event) {
        const categoryId = event.target.value;
        if (!this.hasProductMultiTarget) {
            return;
        }

        const data = this.dataValue || {};
        const select = this.productMultiTarget;
        const currentSelected = new Set(Array.from(select.selectedOptions).map((o) => o.value));

        if (!categoryId) {
            select.innerHTML = '';
            const allProducts = [];
            Object.values(data).forEach((arr) => allProducts.push(...arr));
            allProducts.forEach((p) => this.appendOption(select, p, currentSelected));
            return;
        }

        const products = data[categoryId] || data[String(categoryId)] || [];
        select.innerHTML = '';
        products.forEach((p) => this.appendOption(select, p, currentSelected));
    }

    appendOption(select, product, selectedSet) {
        const opt = document.createElement('option');
        opt.value = String(product.id);
        opt.textContent = product.title;
        if (selectedSet.has(String(product.id))) {
            opt.selected = true;
        }
        select.appendChild(opt);
    }
}
