import { Controller } from '@hotwired/stimulus';
import { Tooltip, Popover } from 'bootstrap';

/**
 * Auto-initialize Bootstrap 5 tooltips and popovers inside a connected element.
 * Replaces the historical [rel="tooltip"] + $.tooltip() jQuery pattern.
 *
 * Usage:
 *   <body data-controller="bootstrap-bridge">
 *     <button data-bs-toggle="tooltip" title="…">…</button>
 *   </body>
 */
export default class extends Controller {
    connect() {
        this.tooltips = Array.from(
            this.element.querySelectorAll('[data-bs-toggle="tooltip"]'),
        ).map((el) => new Tooltip(el));

        this.popovers = Array.from(
            this.element.querySelectorAll('[data-bs-toggle="popover"]'),
        ).map((el) => new Popover(el));
    }

    disconnect() {
        this.tooltips?.forEach((t) => t.dispose());
        this.popovers?.forEach((p) => p.dispose());
    }
}
