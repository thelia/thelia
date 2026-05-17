import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['title', 'hint', 'loading', 'empty', 'grid'];

    static values = {
        listUrlTemplate: String,
        toggleUrlTemplate: String,
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

        this.pseId = trigger.getAttribute('data-pse-id') || '';
        this.type = trigger.getAttribute('data-assoc-type') || 'image';

        if (this.hasTitleTarget) {
            this.titleTarget.textContent = trigger.getAttribute('data-modal-title')
                || this.titleTarget.textContent;
        }

        if (this.hasHintTarget) {
            const hintKey = `${this.type}Hint`;
            const hint = this.hintTarget.dataset[hintKey] || '';
            this.hintTarget.textContent = hint;
        }

        this.fetchItems();
    }

    async fetchItems() {
        if (!this.pseId || !this.type) {
            return;
        }

        this.show(this.loadingTarget, true);
        this.show(this.emptyTarget, false);
        if (this.hasGridTarget) {
            this.gridTarget.innerHTML = '';
        }

        const url = (this.listUrlTemplateValue || '')
            .replace('TYPE', this.type)
            .replace(/\/0(?!\d)/, `/${this.pseId}`);

        try {
            const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
            if (!response.ok) {
                throw new Error('list failed');
            }
            const data = await response.json();
            this.render(data.items || []);
        } catch (e) {
            this.show(this.emptyTarget, true);
            this.emptyTarget.textContent = e.message || 'Error.';
        } finally {
            this.show(this.loadingTarget, false);
        }
    }

    render(items) {
        if (items.length === 0) {
            this.show(this.emptyTarget, true);
            return;
        }

        this.show(this.emptyTarget, false);
        this.gridTarget.innerHTML = '';
        items.forEach((item) => this.gridTarget.appendChild(this.buildCard(item)));
    }

    buildCard(item) {
        const col = document.createElement('div');
        col.className = 'col-6 col-md-4 col-lg-3';

        const card = document.createElement('button');
        card.type = 'button';
        card.className = `card h-100 text-start p-0 border-2 ${item.is_associated ? 'border-primary' : 'border-light'}`;
        card.dataset.itemId = String(item.id);
        card.dataset.associated = item.is_associated ? '1' : '0';
        card.setAttribute('data-testid', `pse-assoc-item-${item.id}`);
        card.addEventListener('click', () => this.onItemClick(card, item));

        const inner = document.createElement('div');
        inner.className = 'card-body small';

        if (this.type === 'image' && item.url) {
            const img = document.createElement('img');
            img.src = item.url;
            img.alt = item.title || '';
            img.className = 'img-fluid mb-2';
            inner.appendChild(img);
        } else {
            const icon = document.createElement('i');
            icon.className = 'bi bi-file-earmark fs-1 d-block mb-2';
            inner.appendChild(icon);
        }

        const title = document.createElement('div');
        title.className = 'fw-semibold text-truncate';
        title.textContent = item.title || item.filename || `#${item.id}`;
        inner.appendChild(title);

        const status = document.createElement('div');
        status.className = item.is_associated ? 'text-success small' : 'text-muted small';
        status.textContent = item.is_associated ? '✓ ' + (this.element.dataset.associatedLabel || 'Associated') : '';
        inner.appendChild(status);

        card.appendChild(inner);
        col.appendChild(card);
        return col;
    }

    async onItemClick(card, item) {
        const url = (this.toggleUrlTemplateValue || '')
            .replace('TYPE', this.type)
            .replace(/\/0\//, `/${this.pseId}/`)
            .replace(/\/0$/, `/${item.id}`);

        card.disabled = true;
        try {
            const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
            if (!response.ok) {
                throw new Error('toggle failed');
            }
            const data = await response.json();
            const associated = Number(data.is_associated || 0) === 1;
            card.dataset.associated = associated ? '1' : '0';
            card.classList.toggle('border-primary', associated);
            card.classList.toggle('border-light', !associated);

            const status = card.querySelector('.text-success, .text-muted');
            if (status) {
                status.className = associated ? 'text-success small' : 'text-muted small';
                status.textContent = associated ? '✓ ' + (this.element.dataset.associatedLabel || 'Associated') : '';
            }
        } catch (e) {
            // silent; the user can retry
        } finally {
            card.disabled = false;
        }
    }

    show(target, on) {
        if (!target) {
            return;
        }
        target.hidden = !on;
    }
}
