/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'preview', 'image', 'filename', 'status'];

    select() {
        this.release();
        const file = this.inputTarget.files[0];
        if (!file) {
            this.clear();
            return;
        }
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 2_000_000) {
            this.clear();
            this.statusTarget.textContent = 'Choose a JPG, PNG or WebP photo up to 2 MB.';
            return;
        }
        this.previewUrl = URL.createObjectURL(file);
        this.imageTarget.src = this.previewUrl;
        this.filenameTarget.textContent = file.name;
        this.previewTarget.hidden = false;
        this.statusTarget.textContent = '';
    }

    clear() {
        this.release();
        this.inputTarget.value = '';
        this.imageTarget.removeAttribute('src');
        this.filenameTarget.textContent = '';
        this.previewTarget.hidden = true;
        this.statusTarget.textContent = '';
    }

    disconnect() {
        this.release();
    }

    release() {
        if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
        this.previewUrl = null;
    }
}
