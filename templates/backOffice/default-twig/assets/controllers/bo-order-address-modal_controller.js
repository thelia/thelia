import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'title',
        'id',
        'company',
        'firstname',
        'lastname',
        'address1',
        'address2',
        'address3',
        'zipcode',
        'city',
        'country',
        'state',
        'stateWrapper',
        'phone',
        'cellphone',
    ];

    static values = {
        states: Array,
    };

    connect() {
        this.boundOnShow = this.onShow.bind(this);
        this.element.addEventListener('show.bs.modal', this.boundOnShow);
    }

    disconnect() {
        this.element.removeEventListener('show.bs.modal', this.boundOnShow);
    }

    onShow(event) {
        const trigger = event.relatedTarget;
        if (!trigger) {
            return;
        }

        const payloadAttr = trigger.getAttribute('data-address-payload');
        if (!payloadAttr) {
            return;
        }

        let payload;
        try {
            payload = JSON.parse(payloadAttr);
        } catch (e) {
            return;
        }

        this.setValue('id', payload.id ?? '');
        this.setSelect('title', payload.title_id ?? '');
        this.setValue('company', payload.company ?? '');
        this.setValue('firstname', payload.firstname ?? '');
        this.setValue('lastname', payload.lastname ?? '');
        this.setValue('address1', payload.address1 ?? '');
        this.setValue('address2', payload.address2 ?? '');
        this.setValue('address3', payload.address3 ?? '');
        this.setValue('zipcode', payload.zipcode ?? '');
        this.setValue('city', payload.city ?? '');
        this.setValue('phone', payload.phone ?? '');
        this.setValue('cellphone', payload.cellphone ?? '');
        this.setSelect('country', payload.country_id ?? '');

        this.populateStates(payload.country_id ?? 0, payload.state_id ?? '');
    }

    onCountryChange(event) {
        this.populateStates(parseInt(event.target.value, 10) || 0, '');
    }

    populateStates(countryId, selectedStateId) {
        if (!this.hasStateTarget) {
            return;
        }

        const states = (this.statesValue ?? []).filter((s) => Number(s.country_id) === Number(countryId));

        const select = this.stateTarget;
        select.innerHTML = '';

        const emptyOption = document.createElement('option');
        emptyOption.value = '';
        emptyOption.textContent = '—';
        select.appendChild(emptyOption);

        states.forEach((state) => {
            const option = document.createElement('option');
            option.value = String(state.id);
            option.textContent = state.title;
            if (Number(state.id) === Number(selectedStateId)) {
                option.selected = true;
            }
            select.appendChild(option);
        });

        if (this.hasStateWrapperTarget) {
            this.stateWrapperTarget.style.display = states.length === 0 ? 'none' : '';
        }
    }

    setValue(targetName, value) {
        const target = `${targetName}Target`;
        if (this[target]) {
            this[target].value = value ?? '';
        }
    }

    setSelect(targetName, value) {
        const target = `${targetName}Target`;
        if (this[target]) {
            const stringValue = value ? String(value) : '';
            const option = Array.from(this[target].options).find((o) => o.value === stringValue);
            if (option) {
                this[target].value = stringValue;
            } else if (this[target].options.length > 0) {
                this[target].selectedIndex = 0;
            }
        }
    }
}
