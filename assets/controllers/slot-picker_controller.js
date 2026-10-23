/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';

// Shows the open appointment slots of the chosen unit and day under the date field.
// Clicking a slot fills the date and time field. The field still works without JavaScript.
export default class extends Controller {
    static targets = ['unit', 'when'];
    static values = {
        url: String,
        unit: Number,
        campus: Number,
        exclude: Number,
    };

    connect() {
        this.grid = document.createElement('div');
        this.grid.className = 'slot-picker';
        this.grid.setAttribute('aria-live', 'polite');
        const anchor = this.whenTarget.closest('div') ?? this.whenTarget.parentElement;
        anchor.append(this.grid);
        this.onChange = () => this.load();
        this.whenTarget.addEventListener('change', this.onChange);
        if (this.hasUnitTarget) {
            this.unitTarget.addEventListener('change', this.onChange);
        }
        this.load();
    }

    disconnect() {
        this.whenTarget.removeEventListener('change', this.onChange);
        if (this.hasUnitTarget) {
            this.unitTarget.removeEventListener('change', this.onChange);
        }
        this.controller?.abort();
        this.grid?.remove();
    }

    get unitId() {
        return this.hasUnitTarget ? Number(this.unitTarget.value) : this.unitValue;
    }

    get day() {
        return this.whenTarget.value ? this.whenTarget.value.slice(0, 10) : '';
    }

    message(text) {
        this.grid.replaceChildren();
        const note = document.createElement('p');
        note.className = 'slot-note';
        note.textContent = text;
        this.grid.append(note);
    }

    async load() {
        this.controller?.abort();
        const controller = this.controller = new AbortController();
        if (!this.unitId) {
            this.message('Choose a SAS unit to see its open slots.');
            return;
        }
        if (!this.day) {
            this.message('Pick a date to see the open slots.');
            return;
        }

        const params = new URLSearchParams({ unit: this.unitId, date: this.day });
        if (this.hasCampusValue && this.campusValue) params.set('campus', this.campusValue);
        if (this.hasExcludeValue && this.excludeValue) params.set('exclude', this.excludeValue);

        const timeout = setTimeout(() => controller.abort(new Error('Slot request timed out')), 10000);
        this.message('Checking open slots...');
        try {
            const response = await fetch(`${this.urlValue}?${params}`, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });
            if (!response.ok) throw new Error('Slot request failed');
            const data = await response.json();
            if (controller !== this.controller || controller.signal.aborted) return;
            this.render(data);
        } catch (error) {
            if (controller === this.controller && error.name !== 'AbortError') {
                this.message('Could not load the slots. You can still type a date and time.');
            }
        } finally {
            clearTimeout(timeout);
        }
    }

    render(data) {
        if (!data.slots.length) {
            this.message(data.message || 'No slots are open on this day.');
            return;
        }

        const selected = this.whenTarget.value.slice(11, 16);
        const list = document.createElement('div');
        list.className = 'slot-grid';
        data.slots.forEach((slot) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'slot-chip';
            button.textContent = slot.available ? slot.label : `${slot.label} · ${slot.reason ?? 'Unavailable'}`;
            button.disabled = !slot.available;
            if (slot.reason) button.title = slot.reason;
            if (slot.available && slot.time === selected) button.classList.add('is-selected');
            button.addEventListener('click', () => {
                this.whenTarget.value = `${this.day}T${slot.time}`;
                list.querySelectorAll('.slot-chip').forEach((chip) => chip.classList.toggle('is-selected', chip === button));
            });
            list.append(button);
        });

        const hint = document.createElement('p');
        hint.className = 'slot-note';
        hint.textContent = 'Greyed-out times are unavailable, already booked or have passed.';
        this.grid.replaceChildren(list, hint);
    }
}
