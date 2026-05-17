import { Controller } from '@hotwired/stimulus';
import { Modal } from 'bootstrap';

/**
 * Mirrors the legacy Smarty tax-rule-edit drag&drop matrix: a configuration
 * selector lists the persisted specifications, a country/state list shows the
 * status of each country relative to the current configuration, and the taxes
 * panel lets the user reorder taxes across groups using native HTML5
 * drag&drop. On Apply, the selected countries and taxes are POSTed to
 * /admin/configuration/taxes_rules/saveTaxes, and the in-page state refreshes
 * from the response.
 */
export default class extends Controller {
    static targets = [
        'configurationSelector',
        'countrySelector',
        'countryList',
        'filterButtons',
        'filterText',
        'taxGroups',
        'taxAvailable',
        'createGroup',
        'applyForm',
        'applyError',
        'taxListInput',
        'countryListInput',
        'countryDeletedInput',
        'countriesAdded',
        'countriesDeleted',
        'countriesAddedCard',
        'countriesDeletedCard',
    ];

    static values = {
        taxRuleId: Number,
        defaultCountryId: Number,
        taxes: Object,
        specification: Object,
        saveUrl: String,
        labels: Object,
    };

    connect() {
        this.specification = this.normalizeSpecification(this.specificationValue);
        this.taxes = this.tax_map(this.taxesValue);
        this.lastSelectedSpec = '';
        this.draggedTax = null;

        this.rebuildConfigurationSelector();
        if (this.hasCountrySelectorTarget && this.defaultCountryIdValue) {
            const defaultValue = `${this.defaultCountryIdValue}-0`;
            const option = Array.from(this.countrySelectorTarget.options).find((opt) => opt.value === defaultValue);
            if (option) {
                this.countrySelectorTarget.value = defaultValue;
            }
        }
        this.applyConfiguration();
    }

    normalizeSpecification(value) {
        if (!value || typeof value !== 'object') {
            return { taxRules: [], specifications: [] };
        }
        return {
            taxRules: Array.isArray(value.taxRules) ? value.taxRules : [],
            specifications: Array.isArray(value.specifications) ? value.specifications : [],
        };
    }

    tax_map(value) {
        if (!value || typeof value !== 'object') {
            return {};
        }
        return value;
    }

    rebuildConfigurationSelector() {
        if (!this.hasConfigurationSelectorTarget) {
            return;
        }
        const labels = this.labelsValue || {};
        const selector = this.configurationSelectorTarget;
        const previous = selector.value;
        selector.innerHTML = '';

        const newOption = document.createElement('option');
        newOption.value = '';
        newOption.textContent = labels.configuration_new || 'New configuration';
        selector.appendChild(newOption);

        this.specification.taxRules.forEach((spec, index) => {
            const count = this.specification.specifications.filter((item) => item.specification === spec).length;
            const opt = document.createElement('option');
            opt.value = spec;
            opt.textContent = `${labels.configuration || 'Configuration'} #${index + 1} (${count} ${labels.countries || 'countries'})`;
            selector.appendChild(opt);
        });

        if (this.lastSelectedSpec && this.specification.taxRules.includes(this.lastSelectedSpec)) {
            selector.value = this.lastSelectedSpec;
            this.lastSelectedSpec = '';
        } else if (previous && this.specification.taxRules.includes(previous)) {
            selector.value = previous;
        }
    }

    configurationChanged() {
        this.applyConfiguration();
    }

    applyConfiguration() {
        const spec = this.hasConfigurationSelectorTarget ? this.configurationSelectorTarget.value : '';
        this.paintCountryStatuses(spec);
        this.renderTaxesForSpec(spec);

        if (this.hasFilterButtonsTarget) {
            const targetId = spec === '' ? 'tax-rule-country-filter-none' : 'tax-rule-country-filter-selected';
            const radio = this.filterButtonsTarget.querySelector(`#${targetId}`);
            if (radio) {
                radio.checked = true;
            }
        }
        this.filterChanged();
    }

    countryChanged() {
        const value = this.countrySelectorTarget.value || '';
        const match = value.match(/^(\d+)-(\d+)$/);
        if (!match) {
            return;
        }
        const countryId = Number(match[1]);
        const stateId = Number(match[2]);
        const found = this.specification.specifications.find((item) => Number(item.country) === countryId && Number(item.state) === stateId);
        if (!found) {
            return;
        }
        this.configurationSelectorTarget.value = found.specification;
        this.applyConfiguration();
    }

    paintCountryStatuses(spec) {
        if (!this.hasCountryListTarget) {
            return;
        }
        const pills = this.countryListTarget.querySelectorAll('.bo-country-pill');
        pills.forEach((pill) => {
            pill.classList.remove('text-bg-success', 'text-bg-danger', 'text-bg-light');
            pill.classList.add('text-bg-light');
            pill.removeAttribute('data-status');
            delete pill.dataset.memorize;
        });

        this.specification.specifications.forEach((entry) => {
            const selector = `[data-id="${entry.country}-${entry.state}"]`;
            const pill = this.countryListTarget.querySelector(selector);
            if (!pill) {
                return;
            }
            pill.classList.remove('text-bg-light', 'text-bg-success', 'text-bg-danger');
            if (entry.specification === spec) {
                pill.classList.add('text-bg-success');
                pill.dataset.status = 'selected';
            } else {
                pill.classList.add('text-bg-danger');
                pill.dataset.status = 'other';
            }
        });

        const remaining = this.countryListTarget.querySelectorAll('.bo-country-pill:not([data-status])');
        remaining.forEach((pill) => {
            pill.dataset.status = 'none';
        });
    }

    renderTaxesForSpec(spec) {
        if (!this.hasTaxGroupsTarget || !this.hasTaxAvailableTarget) {
            return;
        }
        const groups = this.parseSpec(spec);
        const usedIds = new Set();
        const taxesByGroup = [];

        groups.forEach((pair) => {
            const [taxId, groupIndex] = pair;
            usedIds.add(taxId);
            if (!taxesByGroup[groupIndex - 1]) {
                taxesByGroup[groupIndex - 1] = [];
            }
            taxesByGroup[groupIndex - 1].push(taxId);
        });

        const availableIds = Object.keys(this.taxes).map((id) => Number(id)).filter((id) => !usedIds.has(id));

        this.taxGroupsTarget.innerHTML = '';
        if (taxesByGroup.length === 0) {
            this.taxGroupsTarget.appendChild(this.createGroupElement([]));
        } else {
            taxesByGroup.forEach((groupTaxes) => {
                this.taxGroupsTarget.appendChild(this.createGroupElement(groupTaxes || []));
            });
        }

        this.taxAvailableTarget.innerHTML = '';
        availableIds.forEach((taxId) => {
            this.taxAvailableTarget.appendChild(this.createTaxTile(taxId, 'available'));
        });
    }

    createGroupElement(taxIds) {
        const wrapper = document.createElement('div');
        wrapper.className = 'bo-tax-group';
        wrapper.dataset.bbTaxGroup = '1';
        wrapper.addEventListener('dragover', (event) => this.allowDrop(event));
        wrapper.addEventListener('drop', (event) => this.dropOnGroup(event, wrapper));

        const empty = document.createElement('p');
        empty.className = 'text-muted small mb-1';
        empty.textContent = this.labelsValue?.add_to_group || 'Drop tax here';
        wrapper.appendChild(empty);

        taxIds.forEach((id) => wrapper.appendChild(this.createTaxTile(id, 'group')));
        return wrapper;
    }

    createTaxTile(taxId, mode) {
        const tile = document.createElement('div');
        tile.className = mode === 'available' ? 'bo-tax-tile bo-tax-tile--available' : 'bo-tax-tile bo-tax-tile--group';
        tile.draggable = true;
        tile.dataset.taxId = String(taxId);
        tile.dataset.mode = mode;
        tile.textContent = this.taxes[taxId] || `#${taxId}`;
        tile.addEventListener('dragstart', (event) => this.onDragStart(event, tile));
        tile.addEventListener('dragend', () => this.onDragEnd(tile));
        return tile;
    }

    onDragStart(event, tile) {
        this.draggedTax = tile;
        tile.classList.add('opacity-50');
        if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', tile.dataset.taxId || '');
        }
    }

    onDragEnd(tile) {
        tile.classList.remove('opacity-50');
        this.draggedTax = null;
    }

    allowDrop(event) {
        event.preventDefault();
        if (event.dataTransfer) {
            event.dataTransfer.dropEffect = 'move';
        }
    }

    dropOnGroup(event, group) {
        event.preventDefault();
        if (!this.draggedTax) {
            return;
        }
        if (this.draggedTax.dataset.mode === 'group' && this.draggedTax.parentElement === group) {
            return;
        }
        const tile = this.cloneAsGroupTile(this.draggedTax);
        group.appendChild(tile);
        this.draggedTax.remove();
        this.draggedTax = null;
    }

    dropCreateGroup(event) {
        event.preventDefault();
        if (!this.draggedTax) {
            return;
        }
        const newGroup = this.createGroupElement([]);
        this.taxGroupsTarget.appendChild(newGroup);
        const tile = this.cloneAsGroupTile(this.draggedTax);
        newGroup.appendChild(tile);
        this.draggedTax.remove();
        this.draggedTax = null;
        this.cleanupEmptyGroups();
    }

    dropAvailable(event) {
        event.preventDefault();
        if (!this.draggedTax) {
            return;
        }
        if (this.draggedTax.dataset.mode === 'available') {
            return;
        }
        const tile = this.createTaxTile(Number(this.draggedTax.dataset.taxId || '0'), 'available');
        this.taxAvailableTarget.appendChild(tile);
        this.draggedTax.remove();
        this.draggedTax = null;
        this.cleanupEmptyGroups();
    }

    cloneAsGroupTile(source) {
        return this.createTaxTile(Number(source.dataset.taxId || '0'), 'group');
    }

    cleanupEmptyGroups() {
        if (!this.hasTaxGroupsTarget) {
            return;
        }
        const groups = Array.from(this.taxGroupsTarget.querySelectorAll('.bo-tax-group'));
        if (groups.length <= 1) {
            return;
        }
        groups.forEach((group) => {
            if (group.querySelectorAll('.bo-tax-tile').length === 0) {
                group.remove();
            }
        });
    }

    parseSpec(spec) {
        if (!spec) {
            return [];
        }
        return spec.split(',').filter(Boolean).map((entry) => {
            const [taxId, position] = entry.split('-').map((part) => Number(part));
            return [taxId, position];
        });
    }

    toggleCountry(event) {
        const pill = event.currentTarget;
        if (!pill) {
            return;
        }
        if (pill.dataset.memorize === undefined) {
            pill.dataset.memorize = pill.className;
        } else {
            pill.className = pill.dataset.memorize;
            delete pill.dataset.memorize;
            return;
        }
        if (pill.classList.contains('text-bg-success')) {
            pill.classList.remove('text-bg-success');
            pill.classList.add('text-bg-light');
        } else if (pill.classList.contains('text-bg-danger')) {
            pill.classList.remove('text-bg-danger');
            pill.classList.add('text-bg-success');
        } else {
            pill.classList.remove('text-bg-light');
            pill.classList.add('text-bg-success');
        }
    }

    resetCountryList() {
        if (!this.hasCountryListTarget) {
            return;
        }
        const pills = this.countryListTarget.querySelectorAll('.bo-country-pill');
        pills.forEach((pill) => {
            if (pill.dataset.memorize !== undefined) {
                pill.className = pill.dataset.memorize;
                delete pill.dataset.memorize;
            }
        });
    }

    filterChanged() {
        if (!this.hasCountryListTarget || !this.hasFilterButtonsTarget) {
            return;
        }
        const status = this.filterButtonsTarget.querySelector('input[type="radio"]:checked')?.value || 'all';
        const text = (this.hasFilterTextTarget ? this.filterTextTarget.value : '').toLowerCase().trim();

        const pills = this.countryListTarget.querySelectorAll('.bo-country-pill');
        pills.forEach((pill) => {
            const pillStatus = pill.dataset.status || 'none';
            const matchesStatus = status === 'all' || pillStatus === status;
            const matchesText = text === '' || pill.textContent.toLowerCase().includes(text);
            pill.classList.toggle('d-none', !(matchesStatus && matchesText));
        });

        const groups = this.countryListTarget.querySelectorAll('.bo-country-states');
        groups.forEach((group) => {
            const visible = group.querySelectorAll('.bo-country-pill:not(.d-none)').length;
            group.classList.toggle('d-none', visible === 0);
        });
    }

    openApply() {
        if (!this.hasTaxGroupsTarget || !this.hasCountryListTarget) {
            return;
        }
        const taxList = [];
        this.taxGroupsTarget.querySelectorAll('.bo-tax-group').forEach((group, index) => {
            const ids = Array.from(group.querySelectorAll('.bo-tax-tile')).map((tile) => Number(tile.dataset.taxId));
            if (ids.length > 0) {
                taxList[index] = ids;
            }
        });
        const compactTaxList = taxList.filter((entry) => Array.isArray(entry) && entry.length > 0);

        const added = [];
        const deleted = [];
        if (this.hasCountriesAddedTarget) {
            this.countriesAddedTarget.innerHTML = '';
        }
        if (this.hasCountriesDeletedTarget) {
            this.countriesDeletedTarget.innerHTML = '';
        }

        this.countryListTarget.querySelectorAll('.bo-country-pill').forEach((pill) => {
            const id = (pill.dataset.id || '').split('-').map((part) => Number(part));
            if (id.length !== 2) {
                return;
            }
            if (pill.classList.contains('text-bg-success')) {
                added.push(id);
                if (this.hasCountriesAddedTarget) {
                    this.countriesAddedTarget.appendChild(this.clonePill(pill));
                }
            } else if (pill.dataset.memorize && pill.dataset.memorize.indexOf('text-bg-success') !== -1) {
                deleted.push(id);
                if (this.hasCountriesDeletedTarget) {
                    this.countriesDeletedTarget.appendChild(this.clonePill(pill));
                }
            }
        });

        if (this.hasCountriesAddedCardTarget) {
            this.countriesAddedCardTarget.classList.toggle('d-none', added.length === 0);
        }
        if (this.hasCountriesDeletedCardTarget) {
            this.countriesDeletedCardTarget.classList.toggle('d-none', deleted.length === 0);
        }

        if (this.hasTaxListInputTarget) {
            this.taxListInputTarget.value = JSON.stringify(compactTaxList);
        }
        if (this.hasCountryListInputTarget) {
            this.countryListInputTarget.value = JSON.stringify(added);
        }
        if (this.hasCountryDeletedInputTarget) {
            this.countryDeletedInputTarget.value = JSON.stringify(deleted);
        }

        this.lastSelectedSpec = compactTaxList
            .map((group, index) => {
                const sorted = [...group].sort((a, b) => a - b);
                return sorted.map((tax) => `${tax}-${index + 1}`).join(',');
            })
            .filter(Boolean)
            .join(',');
    }

    clonePill(pill) {
        const clone = pill.cloneNode(true);
        clone.classList.remove('d-none');
        clone.removeAttribute('data-action');
        return clone;
    }

    submitApply(event) {
        event.preventDefault();
        const form = this.applyFormTarget;
        const body = new FormData(form);
        const url = this.saveUrlValue || form.action;

        fetch(url, {
            method: 'POST',
            body,
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then((response) => response.json())
            .then((payload) => {
                if (!payload || payload.success !== true) {
                    const message = payload?.message || this.labelsValue?.save_error || 'Save error';
                    if (this.hasApplyErrorTarget) {
                        this.applyErrorTarget.textContent = message;
                        this.applyErrorTarget.classList.remove('d-none');
                    }
                    return;
                }
                this.specification = this.normalizeSpecification(payload.data);
                this.rebuildConfigurationSelector();
                this.applyConfiguration();
                if (this.hasApplyErrorTarget) {
                    this.applyErrorTarget.classList.add('d-none');
                }
                const modalElement = document.getElementById('tax-rule-apply-modal');
                if (modalElement) {
                    const modal = Modal.getInstance(modalElement) || new Modal(modalElement);
                    modal.hide();
                }
            })
            .catch(() => {
                if (this.hasApplyErrorTarget) {
                    this.applyErrorTarget.textContent = this.labelsValue?.save_error || 'Save error';
                    this.applyErrorTarget.classList.remove('d-none');
                }
            });
    }
}
