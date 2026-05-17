import { Controller } from '@hotwired/stimulus';

/**
 * Reproduces the Smarty "select a folder, then load its available contents"
 * picker. The controller fetches available related contents as JSON when the
 * folder select changes, then populates the dependent content select. Mirrors
 * the legacy behaviour of category-edit.html / product-related-tab.html.
 */
export default class extends Controller {
    static targets = ['folder', 'content', 'picker', 'empty'];

    static values = {
        urlTemplate: String,
    };

    connect() {
        if (this.hasFolderTarget && this.folderTarget.value) {
            this.refresh(this.folderTarget.value);
        }
    }

    folderChanged(event) {
        this.refresh(event.target.value);
    }

    refresh(folderId) {
        if (!folderId || folderId === '') {
            this.hide(this.pickerTarget);
            this.hide(this.emptyTarget);
            return;
        }

        const url = this.urlTemplateValue.replace(/\/0\.json$/, `/${folderId}.json`);
        fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.json();
            })
            .then((items) => {
                this.populate(items);
            })
            .catch(() => {
                this.populate([]);
            });
    }

    populate(items) {
        if (!this.hasContentTarget) {
            return;
        }

        while (this.contentTarget.options.length > 1) {
            this.contentTarget.remove(1);
        }

        if (!Array.isArray(items) || items.length === 0) {
            this.hide(this.pickerTarget);
            this.show(this.emptyTarget);
            return;
        }

        items.forEach((item) => {
            const option = document.createElement('option');
            option.value = String(item.id);
            option.textContent = item.title;
            this.contentTarget.appendChild(option);
        });

        this.hide(this.emptyTarget);
        this.show(this.pickerTarget);
    }

    show(element) {
        if (element) {
            element.classList.remove('d-none');
        }
    }

    hide(element) {
        if (element) {
            element.classList.add('d-none');
        }
    }
}
