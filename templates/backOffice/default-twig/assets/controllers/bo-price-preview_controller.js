import { Controller } from '@hotwired/stimulus';

/**
 * Live HT → TTC preview on the create-product modal. Listens on the untaxed
 * price input and the tax-rule select, debounces calls to the calculate-price
 * endpoint, and renders the taxed value next to the price field. The endpoint
 * already exists for iso compatibility with the Smarty modal.
 */
export default class extends Controller {
    static targets = ['price', 'taxRule', 'output'];

    static values = {
        url: String,
        prefix: { type: String, default: '≈ ' },
        suffix: { type: String, default: ' incl. tax' },
    };

    connect() {
        this.debounce = null;
        this.boundUpdate = this.scheduleUpdate.bind(this);
        if (this.hasPriceTarget) {
            this.priceTarget.addEventListener('input', this.boundUpdate);
        }
        if (this.hasTaxRuleTarget) {
            this.taxRuleTarget.addEventListener('change', this.boundUpdate);
        }
        this.scheduleUpdate();
    }

    disconnect() {
        if (this.hasPriceTarget) {
            this.priceTarget.removeEventListener('input', this.boundUpdate);
        }
        if (this.hasTaxRuleTarget) {
            this.taxRuleTarget.removeEventListener('change', this.boundUpdate);
        }
        if (this.debounce !== null) {
            window.clearTimeout(this.debounce);
        }
    }

    scheduleUpdate() {
        if (this.debounce !== null) {
            window.clearTimeout(this.debounce);
        }
        this.debounce = window.setTimeout(() => this.update(), 200);
    }

    update() {
        const price = parseFloat(this.priceTarget?.value);
        const taxRuleId = this.taxRuleTarget?.value;
        if (!this.hasOutputTarget) {
            return;
        }

        if (!Number.isFinite(price) || price <= 0 || !taxRuleId) {
            this.outputTarget.textContent = '';
            return;
        }

        const url = new URL(this.urlValue, window.location.origin);
        url.searchParams.set('price', String(price));
        url.searchParams.set('tax_rule_id', String(taxRuleId));
        url.searchParams.set('action', 'untaxed_to_taxed');

        fetch(url.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then((response) => (response.ok ? response.json() : null))
            .then((data) => {
                if (!data || typeof data.result !== 'number') {
                    this.outputTarget.textContent = '';
                    return;
                }
                this.outputTarget.textContent = `${this.prefixValue}${data.result.toFixed(2)}${this.suffixValue}`;
            })
            .catch(() => {
                this.outputTarget.textContent = '';
            });
    }
}
