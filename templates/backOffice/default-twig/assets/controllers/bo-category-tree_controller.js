import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['root', 'node', 'children'];

    static values = {
        moveUrl: String,
        token: String,
    };

    connect() {
        this.boundOnDragStart = this.onDragStart.bind(this);
        this.boundOnDragOver = this.onDragOver.bind(this);
        this.boundOnDragLeave = this.onDragLeave.bind(this);
        this.boundOnDrop = this.onDrop.bind(this);
        this.boundOnDragEnd = this.onDragEnd.bind(this);

        this.attachDragHandlers();
    }

    attachDragHandlers() {
        this.nodeTargets.forEach((node) => {
            node.addEventListener('dragstart', this.boundOnDragStart);
            node.addEventListener('dragover', this.boundOnDragOver);
            node.addEventListener('dragleave', this.boundOnDragLeave);
            node.addEventListener('drop', this.boundOnDrop);
            node.addEventListener('dragend', this.boundOnDragEnd);
        });

        if (this.hasRootTarget) {
            this.rootTarget.addEventListener('dragover', this.boundOnDragOver);
            this.rootTarget.addEventListener('drop', this.boundOnDrop);
        }
    }

    toggle(event) {
        const node = event.currentTarget.closest('[data-bo-category-tree-target="node"]');
        if (!node) {
            return;
        }
        const children = node.querySelector(':scope > [data-bo-category-tree-target="children"]');
        if (!children) {
            return;
        }
        const chevron = event.currentTarget.querySelector('.bo-category-tree__chevron');
        const collapsed = children.classList.toggle('d-none');
        if (chevron) {
            chevron.classList.toggle('bi-chevron-down', !collapsed);
            chevron.classList.toggle('bi-chevron-right', collapsed);
        }
    }

    onDragStart(event) {
        this.draggedId = event.currentTarget.dataset.categoryId;
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', this.draggedId);
        event.currentTarget.classList.add('opacity-50');
    }

    onDragOver(event) {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        const target = this.findDropTarget(event.currentTarget);
        if (target) {
            target.classList.add('bo-category-tree__drop-target');
        }
    }

    onDragLeave(event) {
        const target = this.findDropTarget(event.currentTarget);
        if (target) {
            target.classList.remove('bo-category-tree__drop-target');
        }
    }

    async onDrop(event) {
        event.preventDefault();
        event.stopPropagation();

        const droppedOn = event.currentTarget;
        this.clearDropTargets();

        if (!this.draggedId) {
            return;
        }

        let newParentId = '0';
        if (droppedOn.dataset.bocategorytreeTarget === 'root' || droppedOn.matches('[data-bo-category-tree-target="root"]')) {
            newParentId = '0';
        } else if (droppedOn.dataset.categoryId) {
            newParentId = droppedOn.dataset.categoryId;
        }

        if (String(newParentId) === String(this.draggedId)) {
            return;
        }

        const formData = new FormData();
        formData.append('category_id', this.draggedId);
        formData.append('new_parent_id', newParentId);
        formData.append('position', '0');
        formData.append('_token', this.tokenValue || '');

        try {
            const response = await fetch(this.moveUrlValue, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: formData,
            });
            if (response.ok) {
                window.location.reload();
            }
        } catch (e) {
            // silent
        }
    }

    onDragEnd(event) {
        event.currentTarget.classList.remove('opacity-50');
        this.clearDropTargets();
        this.draggedId = null;
    }

    findDropTarget(element) {
        if (element.matches('[data-bo-category-tree-target="node"], [data-bo-category-tree-target="root"]')) {
            return element;
        }
        return null;
    }

    clearDropTargets() {
        this.element.querySelectorAll('.bo-category-tree__drop-target').forEach((el) => {
            el.classList.remove('bo-category-tree__drop-target');
        });
    }
}
