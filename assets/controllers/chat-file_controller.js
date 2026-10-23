/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'preview', 'filename', 'size', 'status'];

    select() {
        const file = this.inputTarget.files[0];
        if (!file) {
            this.clear();
            return;
        }
        if (file.size > 2_000_000) {
            this.clear();
            this.statusTarget.textContent = 'Choose a file up to 2 MB.';
            return;
        }
        this.filenameTarget.textContent = file.name;
        this.sizeTarget.textContent = `${(file.size / 1024).toFixed(1)} KB`;
        this.previewTarget.hidden = false;
        this.statusTarget.textContent = '';
    }

    clear() {
        this.inputTarget.value = '';
        this.filenameTarget.textContent = '';
        this.sizeTarget.textContent = '';
        this.previewTarget.hidden = true;
        this.statusTarget.textContent = '';
    }
}
