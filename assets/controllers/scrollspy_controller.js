import { Controller } from '@hotwired/stimulus';

// Highlights the nav link whose section is currently in view.
// Links without an in-page section on this document (e.g. Contact) are left alone.
export default class extends Controller {
    connect() {
        this.entries = [...this.element.querySelectorAll('a[href*="#"]')]
            .map((link) => ({ link, section: document.getElementById(link.hash.slice(1)) }))
            .filter(({ link, section }) => section && link.pathname === window.location.pathname);

        if (this.entries.length === 0) {
            return;
        }

        this.pageActive = this.entries.filter(({ link }) => link.classList.contains('is-active')).map(({ link }) => link);
        this.onScroll = () => {
            if (!this.frame) {
                this.frame = requestAnimationFrame(() => {
                    this.frame = null;
                    this.update();
                });
            }
        };
        window.addEventListener('scroll', this.onScroll, { passive: true });
        window.addEventListener('resize', this.onScroll, { passive: true });
        this.update();
    }

    disconnect() {
        if (!this.onScroll) {
            return;
        }
        window.removeEventListener('scroll', this.onScroll);
        window.removeEventListener('resize', this.onScroll);
        cancelAnimationFrame(this.frame);
    }

    update() {
        // A section becomes current once it crosses a line 35% down the viewport.
        const line = window.innerHeight * 0.35;
        const atBottom = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2;
        let current = this.entries[0];

        for (const entry of this.entries) {
            if (entry.section.getBoundingClientRect().top <= line) {
                current = entry;
            }
        }
        if (atBottom) {
            current = this.entries[this.entries.length - 1];
        }

        for (const { link } of this.entries) {
            const active = link === current.link;
            link.classList.toggle('is-active', active);
            if (active) {
                link.setAttribute('aria-current', 'location');
            } else {
                link.removeAttribute('aria-current');
            }
        }
    }
}
