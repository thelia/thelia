import { Controller } from '@hotwired/stimulus';

/**
 * Drives the coupon edit form interactions:
 *  - Coupon type select → AJAX fetch the inputs partial.
 *  - Free shipping checkbox → toggle the postage card.
 *  - Unlimited checkbox → toggle the max-usage card.
 *  - Conditions: select → AJAX fetch inputs, save POST, edit/delete buttons.
 */
export default class extends Controller {
    static targets = [
        'typeSelect',
        'typeTooltip',
        'inputsContainer',
        'unlimitedCheck',
        'postageCheck',
        'maxUsageWrap',
        'postageWrap',
        'conditionsList',
        'conditionTypeSelect',
        'conditionTooltip',
        'conditionInputs',
        'conditionSaveBtn',
    ];

    static values = {
        drawInputsUrl: String,
        couponId: Number,
        saveConditionUrl: String,
        summariesUrl: String,
        readInputsUrl: String,
        updateInputsUrl: String,
        deleteConditionUrl: String,
    };

    connect() {
        this.attachConditionListeners();
    }

    typeSelectTargetConnected() {
        this.typeSelectTarget.addEventListener('change', () => this.onTypeChange());
    }

    unlimitedCheckTargetConnected() {
        this.unlimitedCheckTarget.addEventListener('change', () => this.onUnlimitedChange());
    }

    postageCheckTargetConnected() {
        this.postageCheckTarget.addEventListener('change', () => this.onPostageChange());
    }

    conditionTypeSelectTargetConnected() {
        this.conditionTypeSelectTarget.addEventListener('change', () => this.onConditionTypeChange());
    }

    onTypeChange() {
        const select = this.typeSelectTarget;
        const serviceId = select.value;
        const selectedOption = select.options[select.selectedIndex];
        const tooltip = selectedOption?.dataset?.tooltip ?? '';
        if (this.hasTypeTooltipTarget) {
            this.typeTooltipTarget.textContent = tooltip;
        }

        if (!serviceId) {
            if (this.hasInputsContainerTarget) {
                this.inputsContainerTarget.innerHTML = '';
            }
            return;
        }

        const url = this.drawInputsUrlValue.replace('__SERVICE__', encodeURIComponent(serviceId));
        fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then((response) => (response.ok ? response.json() : Promise.reject(new Error(`HTTP ${response.status}`))))
            .then((payload) => {
                if (this.hasInputsContainerTarget) {
                    this.inputsContainerTarget.innerHTML = Array.isArray(payload) ? payload[0] ?? '' : '';
                }
            })
            .catch(() => {
                if (this.hasInputsContainerTarget) {
                    this.inputsContainerTarget.innerHTML = '';
                }
            });
    }

    onUnlimitedChange() {
        if (!this.hasMaxUsageWrapTarget) {
            return;
        }
        this.maxUsageWrapTarget.style.display = this.unlimitedCheckTarget.checked ? 'none' : '';
    }

    onPostageChange() {
        if (!this.hasPostageWrapTarget) {
            return;
        }
        this.postageWrapTarget.style.display = this.postageCheckTarget.checked ? '' : 'none';
    }

    onConditionTypeChange() {
        const select = this.conditionTypeSelectTarget;
        const conditionId = select.value;
        const selectedOption = select.options[select.selectedIndex];
        if (this.hasConditionTooltipTarget) {
            this.conditionTooltipTarget.textContent = selectedOption?.dataset?.tooltip ?? '';
        }

        if (!conditionId) {
            this.clearConditionInputs();
            return;
        }

        if (!this.hasReadInputsUrlValue) {
            return;
        }

        const url = this.readInputsUrlValue.replace('__CONDITION__', encodeURIComponent(conditionId));
        fetch(url, { credentials: 'same-origin' })
            .then((response) => (response.ok ? response.text() : Promise.reject(new Error(`HTTP ${response.status}`))))
            .then((html) => this.renderConditionInputs(html))
            .catch(() => this.clearConditionInputs());
    }

    saveCondition() {
        if (!this.hasSaveConditionUrlValue) {
            return;
        }

        const formData = new FormData();
        const conditionServiceId = this.conditionTypeSelectTarget.value;
        if (!conditionServiceId) {
            return;
        }
        formData.append('categoryCondition', conditionServiceId);

        const inputs = this.conditionInputsTarget.querySelectorAll('input, select, textarea');
        inputs.forEach((input) => {
            if (input.type === 'checkbox' && !input.checked) {
                return;
            }
            if (input.name) {
                formData.append(input.name, input.value);
            }
        });

        fetch(this.saveConditionUrlValue, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData,
        })
            .then((response) => (response.ok ? response.text() : Promise.reject(new Error(`HTTP ${response.status}`))))
            .then(() => this.refreshSummaries())
            .catch(() => {});
    }

    deleteCondition(conditionIndex) {
        if (!this.hasDeleteConditionUrlValue) {
            return;
        }

        const url = this.deleteConditionUrlValue.replace('888888', String(conditionIndex));
        fetch(url, { credentials: 'same-origin' })
            .then((response) => (response.ok ? response.text() : Promise.reject(new Error(`HTTP ${response.status}`))))
            .then(() => this.refreshSummaries())
            .catch(() => {});
    }

    editCondition(serviceId, index) {
        if (!this.hasUpdateInputsUrlValue) {
            return;
        }

        const url = this.updateInputsUrlValue.replace('888888', String(index));
        fetch(url, { credentials: 'same-origin' })
            .then((response) => (response.ok ? response.text() : Promise.reject(new Error(`HTTP ${response.status}`))))
            .then((html) => {
                this.conditionTypeSelectTarget.value = serviceId;
                this.conditionTooltipTarget.textContent = this.conditionTypeSelectTarget.selectedOptions[0]?.dataset?.tooltip ?? '';
                this.renderConditionInputs(html);
            })
            .catch(() => this.clearConditionInputs());
    }

    refreshSummaries() {
        if (!this.hasSummariesUrlValue) {
            return;
        }

        fetch(this.summariesUrlValue, { credentials: 'same-origin' })
            .then((response) => (response.ok ? response.text() : Promise.reject(new Error(`HTTP ${response.status}`))))
            .then((html) => {
                if (this.hasConditionsListTarget) {
                    this.conditionsListTarget.innerHTML = html;
                    this.attachConditionListeners();
                }
                this.clearConditionInputs();
                this.conditionTypeSelectTarget.value = '';
                if (this.hasConditionTooltipTarget) {
                    this.conditionTooltipTarget.textContent = '';
                }
            })
            .catch(() => {});
    }

    renderConditionInputs(html) {
        if (this.hasConditionInputsTarget) {
            this.conditionInputsTarget.innerHTML = html;
        }
        if (this.hasConditionSaveBtnTarget) {
            this.conditionSaveBtnTarget.classList.remove('d-none');
        }
    }

    clearConditionInputs() {
        if (this.hasConditionInputsTarget) {
            this.conditionInputsTarget.innerHTML = '';
        }
        if (this.hasConditionSaveBtnTarget) {
            this.conditionSaveBtnTarget.classList.add('d-none');
        }
    }

    attachConditionListeners() {
        if (!this.hasConditionsListTarget) {
            return;
        }

        this.conditionsListTarget.querySelectorAll('.bo-coupon-condition-delete').forEach((button) => {
            button.addEventListener('click', () => {
                const index = button.dataset.conditionIndex;
                if (index !== undefined) {
                    this.deleteCondition(Number.parseInt(index, 10));
                }
            });
        });

        this.conditionsListTarget.querySelectorAll('.bo-coupon-condition-edit').forEach((button) => {
            button.addEventListener('click', () => {
                const serviceId = button.dataset.conditionServiceId;
                const index = button.dataset.conditionIndex;
                if (serviceId && index !== undefined) {
                    this.editCondition(serviceId, Number.parseInt(index, 10));
                }
            });
        });
    }
}
