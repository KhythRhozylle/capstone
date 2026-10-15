import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['sidebar', 'toggle', 'content'];

    connect() {
        this.mobile = window.matchMedia('(max-width: 960px)');
        this.open = false;
        this.collapsed = false;
        this.onViewportChange = () => {
            this.open = false;
            this.update();
        };
        this.mobile.addEventListener('change', this.onViewportChange);
        this.update();
    }

    disconnect() {
        this.mobile.removeEventListener('change', this.onViewportChange);
        this.releasePage();
        if (this.hasSidebarTarget) {
            this.sidebarTarget.inert = false;
        }
    }

    toggle() {
        if (this.mobile.matches) {
            if (this.open) {
                this.close();
                return;
            }
            this.open = true;
            this.update();
            requestAnimationFrame(() => {
                if (this.open) {
                    this.sidebarTarget.querySelector('button, a')?.focus();
                }
            });
        } else {
            this.collapsed = !this.collapsed;
            this.update();
        }
    }

    close() {
        if (!this.open) {
            return;
        }
        this.open = false;
        this.update();
        this.toggleTarget.focus();
    }

    onKeydown(event) {
        if (!this.mobile.matches || !this.open) {
            return;
        }
        if (event.key === 'Escape') {
            event.preventDefault();
            this.close();
            return;
        }
        if (event.key !== 'Tab') {
            return;
        }
        const focusable = [...this.sidebarTarget.querySelectorAll('a[href], button:not([disabled]), [tabindex="0"]')]
            .filter((element) => element.getClientRects().length > 0);
        if (!focusable.length) {
            event.preventDefault();
            this.sidebarTarget.focus();
            return;
        }
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && (document.activeElement === first || !this.sidebarTarget.contains(document.activeElement))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && (document.activeElement === last || !this.sidebarTarget.contains(document.activeElement))) {
            event.preventDefault();
            first.focus();
        }
    }

    beforeCache() {
        this.open = false;
        this.update();
    }

    update() {
        if (!this.hasSidebarTarget) {
            return;
        }
        const drawerOpen = this.mobile.matches && this.open;
        this.element.classList.toggle('is-sidebar-open', drawerOpen);
        this.element.classList.toggle('is-sidebar-collapsed', !this.mobile.matches && this.collapsed);
        this.toggleTarget.setAttribute('aria-expanded', String(this.mobile.matches ? this.open : !this.collapsed));
        this.toggleTarget.setAttribute('aria-label', this.mobile.matches ? (this.open ? 'Close navigation' : 'Open navigation') : (this.collapsed ? 'Expand navigation' : 'Collapse navigation'));
        this.sidebarTarget.inert = this.mobile.matches && !this.open;
        if (this.mobile.matches) {
            this.sidebarTarget.setAttribute('aria-hidden', String(!this.open));
        } else {
            this.sidebarTarget.removeAttribute('aria-hidden');
        }
        if (drawerOpen) {
            this.sidebarTarget.setAttribute('role', 'dialog');
            this.sidebarTarget.setAttribute('aria-modal', 'true');
            this.contentTarget.inert = true;
            if (this.previousOverflow === undefined) {
                this.previousOverflow = document.body.style.overflow;
            }
            document.body.style.overflow = 'hidden';
        } else {
            this.sidebarTarget.removeAttribute('role');
            this.sidebarTarget.removeAttribute('aria-modal');
            this.releasePage();
        }
    }

    releasePage() {
        if (this.hasContentTarget) {
            this.contentTarget.inert = false;
        }
        if (this.previousOverflow !== undefined) {
            document.body.style.overflow = this.previousOverflow;
            this.previousOverflow = undefined;
        }
    }
}
