import { Controller } from '@hotwired/stimulus';

// Tilts the element in 3D towards the pointer and moves a glare highlight with it.
export default class extends Controller {
    static values = { max: { type: Number, default: 9 } };

    connect() {
        this.frame = null;
        this.pointer = null;
        this.media = window.matchMedia('(hover: hover) and (pointer: fine) and (prefers-reduced-motion: no-preference)');
        this.onMediaChange = () => {
            this.enabled = this.media.matches;
            if (!this.enabled) {
                this.reset();
            }
        };
        this.onBeforeCache = () => this.reset();
        this.media.addEventListener('change', this.onMediaChange);
        document.addEventListener('turbo:before-cache', this.onBeforeCache);
        this.reset();
        this.onMediaChange();
    }

    disconnect() {
        this.enabled = false;
        this.media.removeEventListener('change', this.onMediaChange);
        document.removeEventListener('turbo:before-cache', this.onBeforeCache);
        this.reset();
    }

    move(event) {
        if (!this.enabled || event.pointerType === 'touch') {
            return;
        }

        this.pointer = { x: event.clientX, y: event.clientY };
        if (this.frame !== null) {
            return;
        }

        this.frame = requestAnimationFrame(() => {
            this.frame = null;
            const pointer = this.pointer;
            if (!this.enabled || !pointer) {
                return;
            }

            const rect = this.element.getBoundingClientRect();
            if (rect.width <= 0 || rect.height <= 0 || !Number.isFinite(pointer.x) || !Number.isFinite(pointer.y)) {
                this.reset();
                return;
            }

            const x = Math.min(1, Math.max(0, (pointer.x - rect.left) / rect.width)) - 0.5;
            const y = Math.min(1, Math.max(0, (pointer.y - rect.top) / rect.height)) - 0.5;
            const max = Number.isFinite(this.maxValue) ? Math.max(0, this.maxValue) : 9;

            this.element.style.setProperty('--ry', `${(x * max * 2).toFixed(2)}deg`);
            this.element.style.setProperty('--rx', `${(-y * max * 2).toFixed(2)}deg`);
            this.element.style.setProperty('--gx', `${((x + 0.5) * 100).toFixed(1)}%`);
            this.element.style.setProperty('--gy', `${((y + 0.5) * 100).toFixed(1)}%`);
        });
    }

    reset() {
        if (this.frame !== null) {
            cancelAnimationFrame(this.frame);
        }
        this.frame = null;
        this.pointer = null;
        for (const name of ['--rx', '--ry', '--gx', '--gy']) {
            this.element.style.removeProperty(name);
        }
    }
}
