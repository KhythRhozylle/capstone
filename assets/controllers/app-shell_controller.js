import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['sidebar', 'toggle', 'content', 'navTooltip'];

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
        this.hideNavLabel();
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
        if (event.key === 'Escape') this.hideNavLabel();
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
        this.hideNavLabel();
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

    showNavLabel(event) {
        if (this.mobile.matches || !this.collapsed || !this.hasNavTooltipTarget) return;
        const link = event.target.closest?.('.side-nav-link');
        if (!link || !this.sidebarTarget.contains(link)) return;
        const label = link.querySelector('.nav-label')?.textContent.trim();
        if (!label) return;
        this.keepNavLabel();
        this.activeNavLink = link;
        this.navTooltipTarget.textContent = label;
        this.navTooltipTarget.hidden = false;
        this.positionNavLabel();
    }

    positionNavLabel() {
        if (!this.activeNavLink || !this.hasNavTooltipTarget || this.navTooltipTarget.hidden) return;
        const link = this.activeNavLink.getBoundingClientRect();
        const tooltip = this.navTooltipTarget.getBoundingClientRect();
        const right = this.sidebarTarget.getBoundingClientRect().right;
        this.navTooltipTarget.style.left = `${Math.max(8, Math.min(right + 12, window.innerWidth - tooltip.width - 8))}px`;
        this.navTooltipTarget.style.top = `${Math.max(8, Math.min(link.top + (link.height - tooltip.height) / 2, window.innerHeight - tooltip.height - 8))}px`;
    }

    keepNavLabel() {
        window.clearTimeout(this.navLabelTimer);
    }

    queueNavLabelHide(event) {
        if (!this.hasNavTooltipTarget) return;
        if (this.activeNavLink?.contains(event.relatedTarget) || this.navTooltipTarget.contains(event.relatedTarget)) return;
        if (event.type.startsWith('pointer') && this.activeNavLink === document.activeElement) return;
        if (event.type === 'focusout' && this.activeNavLink?.matches(':hover')) return;
        this.keepNavLabel();
        this.navLabelTimer = window.setTimeout(() => this.hideNavLabel(), 120);
    }

    hideNavLabel() {
        this.keepNavLabel();
        if (this.hasNavTooltipTarget) this.navTooltipTarget.hidden = true;
        this.activeNavLink = null;
    }

    onNavScroll() {
        if (this.activeNavLink === document.activeElement) this.positionNavLabel();
        else this.hideNavLabel();
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
