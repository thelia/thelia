import { Controller } from '@hotwired/stimulus';

/**
 * Native HTML5 drag-and-drop reorder for tables whose <tbody> hosts
 * the controller. Each <tr> must carry a `data-row-id="<id>"` attribute.
 * On drop, POSTs the moved row's id and its new 1-based position to the
 * configured URL, then reloads the page so the server-rendered table
 * reflects the persisted order.
 */
export default class extends Controller {
    static values = {
        url: String,
        token: String,
        paramName: { type: String, default: 'id' },
    };

    connect() {
        this.dragged = null;
        this.rows().forEach((row) => this.makeDraggable(row));
        this.boundDragStart = this.onDragStart.bind(this);
        this.boundDragOver = this.onDragOver.bind(this);
        this.boundDragLeave = this.onDragLeave.bind(this);
        this.boundDrop = this.onDrop.bind(this);
        this.boundDragEnd = this.onDragEnd.bind(this);
        this.element.addEventListener('dragstart', this.boundDragStart);
        this.element.addEventListener('dragover', this.boundDragOver);
        this.element.addEventListener('dragleave', this.boundDragLeave);
        this.element.addEventListener('drop', this.boundDrop);
        this.element.addEventListener('dragend', this.boundDragEnd);
    }

    disconnect() {
        this.element.removeEventListener('dragstart', this.boundDragStart);
        this.element.removeEventListener('dragover', this.boundDragOver);
        this.element.removeEventListener('dragleave', this.boundDragLeave);
        this.element.removeEventListener('drop', this.boundDrop);
        this.element.removeEventListener('dragend', this.boundDragEnd);
    }

    rows() {
        return Array.from(this.element.querySelectorAll('tr[data-row-id]'));
    }

    makeDraggable(row) {
        row.setAttribute('draggable', 'true');
        row.classList.add('bo-sortable-row');
    }

    onDragStart(event) {
        const row = event.target.closest('tr[data-row-id]');
        if (!row || row.parentElement !== this.element) {
            return;
        }
        this.dragged = row;
        row.classList.add('bo-sortable-dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', row.dataset.rowId || '');
    }

    onDragOver(event) {
        const row = event.target.closest('tr[data-row-id]');
        if (!row || !this.dragged || row === this.dragged) {
            return;
        }
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        row.classList.add('bo-sortable-target');
    }

    onDragLeave(event) {
        const row = event.target.closest('tr[data-row-id]');
        if (row) {
            row.classList.remove('bo-sortable-target');
        }
    }

    onDrop(event) {
        const target = event.target.closest('tr[data-row-id]');
        if (!target || !this.dragged || target === this.dragged) {
            return;
        }
        event.preventDefault();
        target.classList.remove('bo-sortable-target');

        const rows = this.rows();
        const fromIndex = rows.indexOf(this.dragged);
        const toIndex = rows.indexOf(target);
        if (fromIndex < 0 || toIndex < 0) {
            return;
        }

        if (fromIndex < toIndex) {
            target.after(this.dragged);
        } else {
            target.before(this.dragged);
        }

        const newPosition = this.rows().indexOf(this.dragged) + 1;
        const rowId = this.dragged.dataset.rowId;
        this.persist(rowId, newPosition);
    }

    onDragEnd() {
        if (this.dragged) {
            this.dragged.classList.remove('bo-sortable-dragging');
            this.dragged = null;
        }
        this.element
            .querySelectorAll('.bo-sortable-target')
            .forEach((row) => row.classList.remove('bo-sortable-target'));
    }

    persist(rowId, position) {
        const url = new URL(this.urlValue, window.location.origin);
        url.searchParams.set(this.paramNameValue, rowId);
        url.searchParams.set('position', String(position));
        url.searchParams.set('_token', this.tokenValue);

        fetch(url.toString(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'text/html' },
        })
            .then((response) => {
                if (!response.ok && response.status >= 400) {
                    throw new Error(`HTTP ${response.status}`);
                }
                window.location.reload();
            })
            .catch(() => {
                window.location.reload();
            });
    }
}
