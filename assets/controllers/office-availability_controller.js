/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['date', 'form'];

    choose(event) {
        this.formTarget.hidden = false;
        this.dateTarget.value = event.params.day;
        this.dateTarget.scrollIntoView({ behavior: 'smooth', block: 'center' });
        this.dateTarget.focus();
    }
}
