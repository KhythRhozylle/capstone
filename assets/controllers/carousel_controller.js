import { Controller } from '@hotwired/stimulus';

// Native scrolling keeps the campus photos usable before Stimulus connects.
export default class extends Controller {
    static targets = ['track', 'slide', 'previous', 'next'];

    connect() {
        this.index = 0;
        this.motionPreference = window.matchMedia('(prefers-reduced-motion: reduce)');
        this.onScroll = () => this.scheduleSync();
        this.trackTarget.addEventListener('scroll', this.onScroll, { passive: true });
        this.resizeObserver = new ResizeObserver(() => this.scheduleSync());
        this.resizeObserver.observe(this.trackTarget);
        this.slideTargets.forEach((slide) => this.resizeObserver.observe(slide));

        const multiple = this.slideTargets.length > 1;
        this.previousTarget.hidden = !multiple;
        this.nextTarget.hidden = !multiple;
        this.element.classList.add('carousel-ready');
        this.sync();
    }

    disconnect() {
        cancelAnimationFrame(this.frame);
        this.trackTarget.removeEventListener('scroll', this.onScroll);
        this.resizeObserver.disconnect();
        this.previousTarget.hidden = true;
        this.nextTarget.hidden = true;
        this.element.classList.remove('carousel-ready');
    }

    next() {
        this.goTo(this.index + 1 < this.slideTargets.length ? this.index + 1 : 0);
    }

    prev() {
        this.goTo(this.index > 0 ? this.index - 1 : this.slideTargets.length - 1);
    }

    goTo(index) {
        const slide = this.slideTargets[index];
        const track = this.trackTarget;
        if (!slide) {
            return;
        }
        const trackRect = track.getBoundingClientRect();
        const slideRect = slide.getBoundingClientRect();
        const centre = trackRect.left + track.clientLeft + track.clientWidth / 2;
        const left = track.scrollLeft + slideRect.left + slideRect.width / 2 - centre;
        track.scrollTo({ left, behavior: this.motionPreference.matches ? 'instant' : 'smooth' });
    }

    scheduleSync() {
        cancelAnimationFrame(this.frame);
        this.frame = requestAnimationFrame(() => this.sync());
    }

    // Marks the slide closest to the centre of the track as active.
    sync() {
        const track = this.trackTarget;
        const trackRect = track.getBoundingClientRect();
        const centre = trackRect.left + track.clientLeft + track.clientWidth / 2;
        let best = 0;
        let bestDistance = Infinity;
        this.slideTargets.forEach((slide, i) => {
            const rect = slide.getBoundingClientRect();
            const distance = Math.abs(rect.left + rect.width / 2 - centre);
            if (distance < bestDistance) {
                bestDistance = distance;
                best = i;
            }
        });

        this.index = best;
        this.slideTargets.forEach((slide, i) => slide.classList.toggle('is-active', i === best));
    }
}
