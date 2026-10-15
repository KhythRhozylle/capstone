import { Controller } from '@hotwired/stimulus';

// Morphs the clicked "Sign in" button into the login card (and back again).
// Without JavaScript the buttons are plain links to /login.
export default class extends Controller {
    static targets = ['modal', 'glass', 'content', 'backdrop', 'email'];

    connect() {
        this.trigger = null;
        this.animating = false;
        this.onKeydown = (event) => {
            if (event.key === 'Escape' && !this.modalTarget.hidden) {
                this.close();
            }
        };
        document.addEventListener('keydown', this.onKeydown);
    }

    disconnect() {
        document.removeEventListener('keydown', this.onKeydown);
        document.body.classList.remove('login-open');
    }

    get duration() {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 620;
    }

    open(event) {
        event.preventDefault();
        if (this.animating || !this.modalTarget.hidden) {
            return;
        }

        this.trigger = event.currentTarget;
        this.modalTarget.hidden = false;
        document.body.classList.add('login-open');

        this.play(true);
        this.emailTarget.focus({ preventScroll: true });
    }

    close(event) {
        event?.preventDefault();
        if (this.animating || this.modalTarget.hidden) {
            return;
        }

        this.play(false).then(() => {
            this.modalTarget.hidden = true;
            document.body.classList.remove('login-open');
            if (this.trigger) {
                this.trigger.style.visibility = '';
                this.trigger.focus({ preventScroll: true });
            }
        });
    }

    closeOnBackdrop(event) {
        if (event.target === this.modalTarget || event.target === this.backdropTarget) {
            this.close(event);
        }
    }

    // Animates the card between the trigger button's rectangle and its resting place.
    play(opening) {
        const duration = this.duration;
        const glass = this.glassTarget;

        // Measure the untilted card so the morph lines up with the button.
        const tilt = glass.parentElement;
        tilt.style.transition = 'none';
        for (const name of ['--rx', '--ry', '--gx', '--gy']) {
            tilt.style.removeProperty(name);
        }
        const from = this.trigger.getBoundingClientRect();
        const to = glass.getBoundingClientRect();
        tilt.style.transition = '';

        const buttonStyle = getComputedStyle(this.trigger);
        const cardStyle = getComputedStyle(glass);
        const cardRadius = cardStyle.borderTopLeftRadius;
        const inset = (value) => Math.max(0, value).toFixed(1);
        const small = `inset(${inset(from.top - to.top)}px ${inset(to.right - from.right)}px ${inset(to.bottom - from.bottom)}px ${inset(from.left - to.left)}px round ${(from.height / 2).toFixed(1)}px)`;
        const full = `inset(0px 0px 0px 0px round ${cardRadius})`;

        const shape = [
            { clipPath: small, backgroundColor: buttonStyle.backgroundColor },
            { clipPath: full, backgroundColor: cardStyle.backgroundColor },
        ];
        const fade = [{ opacity: 0 }, { opacity: 1 }];
        const options = { duration, easing: 'cubic-bezier(0.22, 0.9, 0.24, 1)', fill: 'both' };

        this.animating = true;
        this.trigger.style.visibility = 'hidden';

        const animations = [
            this.backdropTarget.animate(fade, { duration: duration * 0.6, fill: 'both', direction: opening ? 'normal' : 'reverse' }),
            glass.animate(shape, { ...options, direction: opening ? 'normal' : 'reverse' }),
            this.contentTarget.animate(fade, {
                duration: duration * 0.5,
                delay: opening ? duration * 0.4 : 0,
                easing: 'ease-out',
                fill: 'both',
                direction: opening ? 'normal' : 'reverse',
            }),
        ];

        return Promise.all(animations.map((animation) => animation.finished)).then(() => {
            this.animating = false;
            // Drop the clip-path/opacity fills so the resting card can render in full 3D.
            animations.forEach((animation) => animation.cancel());
        });
    }
}
