import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['filter', 'discoverType', 'discoverContent'];

    static values = {
        listUrl: String,
        toggleActivationUrl: String,
        toggleNativeUrl: String,
        discoverUrl: String,
        discoverSaveUrl: String,
    };

    connect() {
        this.newHooks = [];
        this.missingHooks = [];
    }

    filter() {
        if (!this.hasFilterTargetValue && !this.hasFilterTarget) {
            return;
        }
        const type = this.filterTarget.value;
        const url = new URL(this.listUrlValue, window.location.origin);
        url.searchParams.set('type', type);
        window.location.href = url.toString();
    }

    discover() {
        if (!this.hasDiscoverContentTarget || !this.hasDiscoverTypeTarget) {
            return;
        }
        const templateType = this.discoverTypeTarget.value;
        const url = new URL(this.discoverUrlValue, window.location.origin);
        url.searchParams.set('template_type', templateType);
        this.discoverContentTarget.innerHTML = `<div class="text-muted">${this.t('Parsing template...')}</div>`;

        fetch(url.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then((r) => (r.ok ? r.json() : Promise.reject(new Error(`HTTP ${r.status}`))))
            .then((data) => {
                if (!data.success) {
                    this.discoverContentTarget.innerHTML = `<div class="alert alert-danger">${this.escape(data.message || '')}</div>`;
                    return;
                }
                this.newHooks = data.new || [];
                this.missingHooks = data.missing || [];
                if (this.newHooks.length === 0 && this.missingHooks.length === 0) {
                    this.discoverContentTarget.innerHTML = `<div class="alert alert-success">${this.t('Your template is clean. No missing hooks and no new hooks.')}</div>`;
                    return;
                }
                this.renderDiscoverResults(templateType);
            })
            .catch((err) => {
                this.discoverContentTarget.innerHTML = `<div class="alert alert-danger">${this.escape(err.message)}</div>`;
            });
    }

    renderDiscoverResults(templateType) {
        let html = '';
        if (this.newHooks.length > 0) {
            html += `<h3 class="h6 mt-3">${this.t('Your template define new hooks')}</h3>`;
            html += '<table class="table table-sm table-striped"><thead><tr><th><input type="checkbox" data-action="change->bo-hooks#toggleAll" data-bo-hooks-target-list="new"></th><th>Code</th><th>Title</th><th>Block</th><th>Module</th></tr></thead><tbody>';
            this.newHooks.forEach((hook, index) => {
                html += `<tr><td><input type="checkbox" class="bo-hooks-new" data-index="${index}"></td><td>${this.escape(hook.code)}</td><td>${this.escape(hook.title || '-')}</td><td>${hook.block ? '✓' : '—'}</td><td>${hook.module ? '✓' : '—'}</td></tr>`;
            });
            html += '</tbody></table>';
        }
        if (this.missingHooks.length > 0) {
            html += `<h3 class="h6 mt-3">${this.t('Your template does not support this hooks')}</h3>`;
            html += '<table class="table table-sm table-striped"><thead><tr><th></th><th>Code</th><th>Title</th><th>Official</th></tr></thead><tbody>';
            this.missingHooks.forEach((hook) => {
                html += `<tr><td><input type="checkbox" class="bo-hooks-missing" value="${hook.id}" ${hook.activate ? '' : 'disabled'}></td><td>${this.escape(hook.code)}</td><td>${this.escape(hook.title)}</td><td>${hook.native ? '✓' : '—'}</td></tr>`;
            });
            html += '</tbody></table>';
        }
        html += `<div class="mt-2"><button type="button" class="btn btn-success btn-sm" data-action="bo-hooks#saveDiscover" data-template-type="${templateType}">${this.t('Update')}</button></div>`;
        this.discoverContentTarget.innerHTML = html;
    }

    saveDiscover(event) {
        const templateType = event.currentTarget.dataset.templateType;
        const newSelected = Array.from(this.element.querySelectorAll('.bo-hooks-new:checked'))
            .map((input) => this.newHooks[Number(input.dataset.index)]);
        const missingSelected = Array.from(this.element.querySelectorAll('.bo-hooks-missing:checked'))
            .map((input) => input.value);

        const formData = new FormData();
        formData.set('templateType', templateType);
        newSelected.forEach((hook, index) => {
            Object.entries(hook || {}).forEach(([key, value]) => {
                formData.append(`new[${index}][${key}]`, value === true ? '1' : value === false ? '' : String(value ?? ''));
            });
        });
        missingSelected.forEach((id, index) => {
            formData.append(`missing[${index}]`, id);
        });

        fetch(this.discoverSaveUrlValue, { method: 'POST', body: formData, credentials: 'same-origin' })
            .then((r) => (r.ok ? r.json() : r.json().then((d) => Promise.reject(new Error((d.messages || [r.statusText]).join(' ; '))))))
            .then(() => {
                this.discover();
            })
            .catch((err) => {
                this.discoverContentTarget.innerHTML = `<div class="alert alert-danger">${this.escape(err.message)}</div>`;
            });
    }

    toggleAll(event) {
        const target = event.currentTarget.dataset.boHooksTargetList === 'new' ? '.bo-hooks-new' : '.bo-hooks-missing';
        this.element.querySelectorAll(target).forEach((input) => {
            if (!input.disabled) {
                input.checked = event.currentTarget.checked;
            }
        });
    }

    t(text) {
        return text;
    }

    escape(value) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(String(value ?? '')));
        return div.innerHTML;
    }
}
