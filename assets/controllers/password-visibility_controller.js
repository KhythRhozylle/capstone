import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'button'];

    connect() {
        this.hide();
    }

    toggle() {
        this.setVisible(this.inputTarget.type === 'password');
    }

    hide() {
        this.setVisible(false);
    }

    disconnect() {
        this.hide();
    }

    setVisible(visible) {
        this.inputTarget.type = visible ? 'text' : 'password';
        this.buttonTarget.setAttribute('aria-pressed', String(visible));
        this.buttonTarget.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
        this.buttonTarget.title = visible ? 'Hide password' : 'Show password';
    }
}
