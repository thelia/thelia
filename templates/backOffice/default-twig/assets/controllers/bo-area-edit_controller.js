import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['filter', 'addSelect', 'addedBody'];

    static values = {
        areaId: Number,
        dataUrl: String,
        initial: Array,
        unassigned: Array,
        statesAllLabel: String,
    };

    connect() {
        this.fetchData();
    }

    fetchData() {
        fetch(this.dataUrlValue, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then((response) => (response.ok ? response.json() : Promise.reject(new Error(`HTTP ${response.status}`))))
            .then((data) => {
                this.countries = Array.isArray(data) ? data : [];
                this.render();
            })
            .catch(() => {
                this.countries = [];
                this.render();
            });
    }

    isSelected(countryId, stateId) {
        return (this.initialValue || []).some((item) => (
            Number(item.country_id) === Number(countryId)
            && Number(item.state_id || 0) === Number(stateId || 0)
        ));
    }

    render() {
        const addList = [];
        const addedList = [];

        (this.countries || []).forEach((country) => {
            if (!country.hasStates) {
                if (this.isSelected(country.id, 0)) {
                    addedList.push({ ...country, states: [], allStates: false });
                } else {
                    addList.push({ ...country, states: [], allStates: false });
                }
                return;
            }
            const addStates = [];
            const addedStates = [];
            (country.states || []).forEach((state) => {
                if (this.isSelected(country.id, state.id)) {
                    addedStates.push(state);
                } else {
                    addStates.push(state);
                }
            });
            const allStatesSelected = this.isSelected(country.id, 0);
            if (addedStates.length > 0 || allStatesSelected) {
                addedList.push({ ...country, states: addedStates, allStates: allStatesSelected });
            }
            if (addStates.length > 0) {
                addList.push({ ...country, states: addStates, allStates: !allStatesSelected });
            }
        });

        addList.sort((a, b) => a.title.localeCompare(b.title));
        addedList.sort((a, b) => a.title.localeCompare(b.title));

        if (this.hasAddSelectTarget) {
            this.addSelectTarget.innerHTML = this.renderAddOptions(addList);
        }
        if (this.hasAddedBodyTarget) {
            this.addedBodyTarget.innerHTML = addedList.length === 0
                ? `<tr><td colspan="2" class="text-center text-muted">${this.statesAllLabelValue ? '' : ''}${this.translate('This shipping zone does not contains any country.')}</td></tr>`
                : this.renderAddedRows(addedList);
        }
    }

    translate(text) {
        return text;
    }

    renderAddOptions(list) {
        let html = '';
        list.forEach((country) => {
            if (!country.hasStates) {
                html += `<option value="${country.id}-0">${this.escape(country.title)}</option>`;
                return;
            }
            html += `<optgroup label="${this.escape(country.title)}">`;
            if (country.allStates) {
                html += `<option value="${country.id}-0">${this.escape(this.statesAllLabelValue)}</option>`;
            }
            country.states.forEach((state) => {
                html += `<option value="${country.id}-${state.id}">${this.escape(state.title)}</option>`;
            });
            html += '</optgroup>';
        });
        return html;
    }

    renderAddedRows(list) {
        let html = '';
        list.forEach((country) => {
            if (!country.hasStates) {
                html += `<tr><td>${this.escape(country.title)}</td><td class="text-center"><input class="form-check-input country-selection" type="checkbox" name="country_id[]" value="${country.id}-0"></td></tr>`;
                return;
            }
            html += `<tr><th colspan="2">${this.escape(country.title)}</th></tr>`;
            if (country.allStates) {
                html += `<tr><td> &mdash; ${this.escape(this.statesAllLabelValue)}</td><td class="text-center"><input class="form-check-input country-selection" type="checkbox" name="country_id[]" value="${country.id}-0"></td></tr>`;
            }
            country.states.forEach((state) => {
                html += `<tr><td> &mdash; ${this.escape(state.title)}</td><td class="text-center"><input class="form-check-input country-selection" type="checkbox" name="country_id[]" value="${country.id}-${state.id}"></td></tr>`;
            });
        });
        return html;
    }

    escape(value) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(String(value ?? '')));
        return div.innerHTML;
    }

    filter() {
        if (!this.hasAddSelectTarget || !this.hasFilterTarget) {
            return;
        }
        const search = (this.filterTarget.value || '').trim().toLowerCase();
        this.addSelectTarget.querySelectorAll('option, optgroup').forEach((node) => {
            const text = node.textContent.toLowerCase();
            node.classList.toggle('d-none', search !== '' && !text.includes(search));
        });
    }

    selectUnassigned() {
        if (!this.hasAddSelectTarget) {
            return;
        }
        const ids = new Set((this.unassignedValue || []).map((id) => Number(id)));
        this.addSelectTarget.querySelectorAll('option').forEach((option) => {
            const [countryId, stateId] = option.value.split('-').map(Number);
            option.selected = stateId === 0 && ids.has(countryId);
        });
    }

    selectAll(event) {
        event.preventDefault();
        this.toggleCheckboxes(() => true);
    }

    selectNone(event) {
        event.preventDefault();
        this.toggleCheckboxes(() => false);
    }

    selectReverse(event) {
        event.preventDefault();
        this.toggleCheckboxes((checkbox) => !checkbox.checked);
    }

    toggleCheckboxes(predicate) {
        if (!this.hasAddedBodyTarget) {
            return;
        }
        this.addedBodyTarget.querySelectorAll('input.country-selection').forEach((checkbox) => {
            checkbox.checked = predicate(checkbox);
        });
    }

    filterTargetConnected(element) {
        element.addEventListener('input', this.filter.bind(this));
    }

    filterTargetDisconnected(element) {
        element.removeEventListener('input', this.filter.bind(this));
    }
}
