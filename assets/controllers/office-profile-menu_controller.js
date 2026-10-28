import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['details'];

    connect() {
        const label = this.element.querySelector('#office-profile-name');
        if (!label) return;
        this.identityObserver = new MutationObserver(() => {
            const name = label.textContent.replace('▾', '').trim();
            window.dispatchEvent(new CustomEvent('office-profile:changed', {detail: {name}}));
        });
        this.identityObserver.observe(label, {childList: true, subtree: true, characterData: true});
    }

    disconnect() {
        this.identityObserver?.disconnect();
    }

    openProfile() {
        this.detailsTarget.open = true;
    }

    outside(event) {
        if (!this.element.contains(event.target)) this.close();
    }

    escape(event) {
        if (event.key === 'Escape' && this.detailsTarget.open) {
            this.close();
            this.detailsTarget.querySelector('summary').focus();
        }
    }

    loaded(event) {
        if (event.target.id !== 'office-profile-panel') return;
        if (this.element.classList.contains('account-profile-chip')) {
            const name = this.element.querySelector('.user-chip-name').textContent.trim();
            this.detailsTarget.querySelector('summary').setAttribute('aria-label', `View profile of ${name}`);
            return;
        }
        this.detailsTarget.open = true;
        const name = this.element.querySelector('.user-chip-name').textContent.replace('▾', '').trim();
        this.element.querySelector('a.office-avatar-link')?.setAttribute('aria-label', `View profile of ${name}`);
        event.target.querySelector('[data-office-profile-back]')?.focus();
    }

    close() {
        this.detailsTarget.open = false;
    }
}
