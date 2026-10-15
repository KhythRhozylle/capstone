import { Controller } from '@hotwired/stimulus';

// Fades [data-reveal] elements in as they scroll into view.
// Elements stay visible if JavaScript or IntersectionObserver is unavailable.
export default class extends Controller {
    connect() {
        this.items = [...this.element.querySelectorAll('[data-reveal]')];
        this.media = window.matchMedia('(prefers-reduced-motion: reduce)');
        this.onMediaChange = () => this.observe();
        this.onBeforeCache = () => this.showAll();
        this.media.addEventListener('change', this.onMediaChange);
        document.addEventListener('turbo:before-cache', this.onBeforeCache);
        this.observe();
    }

    disconnect() {
        this.media.removeEventListener('change', this.onMediaChange);
        document.removeEventListener('turbo:before-cache', this.onBeforeCache);
        this.showAll();
    }

    observe() {
        this.stopObserver();
        if (this.media.matches || !('IntersectionObserver' in window) || document.documentElement.hasAttribute('data-turbo-preview')) {
            this.showAll();
            return;
        }

        const items = this.items.filter((item) => !item.classList.contains('is-visible'));
        if (items.length === 0) {
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            if (this.observer !== observer) {
                return;
            }
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            }
        }, { threshold: 0, rootMargin: '0px 0px -24px 0px' });

        this.observer = observer;
        this.element.classList.add('reveal-ready');
        items.forEach((item) => observer.observe(item));
    }

    stopObserver() {
        this.observer?.disconnect();
        this.observer = null;
        this.element.classList.remove('reveal-ready');
    }

    showAll() {
        this.stopObserver();
        this.items.forEach((item) => item.classList.add('is-visible'));
    }
}
