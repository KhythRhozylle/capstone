import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'status'];

    choose() {
        this.inputTarget.click();
    }

    upload() {
        if (this.inputTarget.files.length === 0) return;
        this.statusTarget.textContent = 'Uploading photo...';
        this.element.requestSubmit();
    }
}
