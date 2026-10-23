import { Controller } from '@hotwired/stimulus';

// Floating unit head profile. The card content comes from a <template> next to each head,
// so opening another head just swaps the content; the page and the open director never change.
export default class extends Controller {
    static targets = ['modal', 'card', 'body', 'close'];

    disconnect() {
        document.body.classList.remove('profile-open');
    }

    show(event) {
        const template = document.getElementById(event.params.template);
        if (!template) {
            return;
        }

        this.opener = event.currentTarget;
        this.bodyTarget.replaceChildren(template.content.cloneNode(true));
        const title = this.bodyTarget.querySelector('[data-profile-title]');
        this.modalTarget.setAttribute('aria-label', `Profile of ${title ? title.textContent.trim() : 'unit head'}`);
        this.cardTarget.scrollTop = 0;
        this.modalTarget.hidden = false;
        document.body.classList.add('profile-open');
        this.closeTarget.focus({ preventScroll: true });
    }

    close() {
        if (this.modalTarget.hidden || this.closing) {
            return;
        }

        const finish = () => {
            clearTimeout(this.closeTimer);
            if (!this.closing && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return; // reopened while the exit animation was playing
            }
            this.closing = false;
            this.modalTarget.classList.remove('is-closing');
            this.modalTarget.hidden = true;
            document.body.classList.remove('profile-open');
            this.opener?.focus({ preventScroll: true });
        };

        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            finish();
            return;
        }

        // Play the exit animation first; the timeout is a fallback if animationend never fires.
        this.closing = true;
        this.modalTarget.classList.add('is-closing');
        this.cardTarget.addEventListener('animationend', finish, { once: true });
        this.closeTimer = setTimeout(finish, 350);
    }

    open(event) {
        clearTimeout(this.closeTimer);
        this.closing = false;
        this.modalTarget.classList.remove('is-closing');
        this.show(event);
    }

    // Only a click on the overlay itself closes the card; clicks inside it have the card as their target.
    backdrop(event) {
        if (event.target === this.modalTarget) {
            this.close();
        }
    }
}
