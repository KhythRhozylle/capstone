/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { actor: String, new: Boolean };

    connect() {
        this.originalReleaser = this.field('releasedBy')?.value || '';
        this.update();
    }

    field(name) {
        return Array.from(this.element.elements).find((field) => field.name.endsWith('[' + name + ']'));
    }

    profileChanged(event) {
        this.actorValue = event.detail.name;
        const receivedBy = this.field('receivedBy');
        if (this.newValue && receivedBy) receivedBy.value = this.actorValue;
        this.update();
    }

    update(event) {
        if (event && event.target.form !== this.element) return;
        const releasedBy = this.field('releasedBy');
        if (!releasedBy) return;
        const releasing = Boolean(this.field('releasedAt')?.value) || this.field('status')?.value === 'released';
        releasedBy.value = this.originalReleaser || (releasing ? this.actorValue : '');
    }
}
