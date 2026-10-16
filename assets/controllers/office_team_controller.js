import { Controller } from '@hotwired/stimulus';

// Shows or hides the office team when the Vice President's portrait is selected.
export default class extends Controller {
    static targets = ['trigger', 'panel'];

    toggle() {
        const open = this.panelTarget.hidden;
        this.panelTarget.hidden = !open;
        this.triggerTarget.setAttribute('aria-expanded', String(open));
        this.element.classList.toggle('is-team-open', open);
    }
}
