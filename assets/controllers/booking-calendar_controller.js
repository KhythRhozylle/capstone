/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';

// A month calendar for one official. Choosing a day lists that day's open time slots (from the
// appointment-slots endpoint); choosing a slot fills the form's date-and-time field.
// Without JavaScript the plain date-and-time field stays visible and still works.
const MONTHS_AHEAD = 3;

export default class extends Controller {
    static targets = ['when', 'month', 'days', 'times', 'previous', 'next', 'summary', 'submit'];
    static values = { url: String, unit: Number, submitted: Boolean };

    connect() {
        this.element.classList.add('is-enhanced');
        const today = new Date();
        this.today = new Date(today.getFullYear(), today.getMonth(), today.getDate());
        this.limit = new Date(this.today.getFullYear(), this.today.getMonth() + MONTHS_AHEAD, 1);

        // A fresh form carries a placeholder date; only keep the value when the form was submitted with errors.
        const existing = this.submittedValue ? this.whenTarget.value : '';
        this.selectedDay = existing ? existing.slice(0, 10) : null;
        this.selectedTime = existing ? existing.slice(11, 16) : null;
        if (!existing) {
            this.whenTarget.value = '';
        }

        const start = this.selectedDay ? this.parse(this.selectedDay) : this.today;
        this.view = new Date(start.getFullYear(), start.getMonth(), 1);
        this.renderMonth();
        this.updateSummary();
        if (this.selectedDay) {
            this.loadSlots(this.selectedDay);
        }
    }

    disconnect() {
        this.controller?.abort();
        this.element.classList.remove('is-enhanced');
    }

    previous() {
        this.view = new Date(this.view.getFullYear(), this.view.getMonth() - 1, 1);
        this.renderMonth();
    }

    next() {
        this.view = new Date(this.view.getFullYear(), this.view.getMonth() + 1, 1);
        this.renderMonth();
    }

    pad(value) {
        return String(value).padStart(2, '0');
    }

    iso(date) {
        return `${date.getFullYear()}-${this.pad(date.getMonth() + 1)}-${this.pad(date.getDate())}`;
    }

    parse(iso) {
        const [y, m, d] = iso.split('-').map(Number);
        return new Date(y, m - 1, d);
    }

    renderMonth() {
        const year = this.view.getFullYear();
        const month = this.view.getMonth();
        this.monthTarget.textContent = this.view.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
        this.previousTarget.disabled = this.view <= new Date(this.today.getFullYear(), this.today.getMonth(), 1);
        this.nextTarget.disabled = new Date(year, month + 1, 1) >= this.limit;

        const first = new Date(year, month, 1);
        const count = new Date(year, month + 1, 0).getDate();
        const cells = [];
        for (let i = 0; i < first.getDay(); i += 1) {
            cells.push(document.createElement('span'));
        }
        for (let day = 1; day <= count; day += 1) {
            const date = new Date(year, month, day);
            const iso = this.iso(date);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'booking-day';
            button.textContent = String(day);
            button.dataset.date = iso;
            button.disabled = date < this.today;
            button.setAttribute('aria-label', date.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }));
            if (iso === this.iso(this.today)) button.classList.add('is-today');
            const selected = iso === this.selectedDay;
            button.classList.toggle('is-selected', selected);
            button.setAttribute('aria-pressed', String(selected));
            button.addEventListener('click', () => this.chooseDay(iso));
            cells.push(button);
        }
        this.daysTarget.replaceChildren(...cells);
    }

    chooseDay(iso) {
        this.selectedDay = iso;
        this.selectedTime = null;
        this.whenTarget.value = '';
        this.daysTarget.querySelectorAll('.booking-day').forEach((button) => {
            const selected = button.dataset.date === iso;
            button.classList.toggle('is-selected', selected);
            button.setAttribute('aria-pressed', String(selected));
        });
        this.updateSummary();
        this.loadSlots(iso);
    }

    heading(iso) {
        const title = document.createElement('h3');
        title.textContent = this.parse(iso).toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' });
        return title;
    }

    note(iso, text) {
        const note = document.createElement('p');
        note.className = 'booking-placeholder';
        note.textContent = text;
        this.timesTarget.replaceChildren(...(iso ? [this.heading(iso), note] : [note]));
    }

    async loadSlots(iso) {
        this.controller?.abort();
        const controller = (this.controller = new AbortController());
        this.note(iso, 'Checking open times...');
        const timeout = setTimeout(() => controller.abort(), 10000);
        try {
            const response = await fetch(`${this.urlValue}?${new URLSearchParams({ unit: this.unitValue, date: iso })}`, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });
            if (!response.ok) throw new Error('Slot request failed');
            const data = await response.json();
            if (controller !== this.controller || iso !== this.selectedDay) return;
            this.renderSlots(iso, data);
        } catch (error) {
            if (controller === this.controller && error.name !== 'AbortError') {
                this.note(iso, 'Could not load the available times. Please try another day.');
            }
        } finally {
            clearTimeout(timeout);
        }
    }

    renderSlots(iso, data) {
        if (!data.slots.length) {
            this.note(iso, data.message || 'No times are open on this day.');
            return;
        }

        const list = document.createElement('div');
        list.className = 'booking-slots';
        data.slots.forEach((slot) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'booking-slot';
            button.textContent = slot.available ? slot.label : `${slot.label} · ${slot.reason ?? 'Unavailable'}`;
            button.disabled = !slot.available;
            if (slot.reason) button.title = slot.reason;
            const selected = slot.available && slot.time === this.selectedTime;
            button.classList.toggle('is-selected', selected);
            button.setAttribute('aria-pressed', String(selected));
            button.addEventListener('click', () => this.chooseSlot(iso, slot, list, button));
            list.append(button);
        });
        const hint = document.createElement('p');
        hint.className = 'booking-hint';
        hint.textContent = 'Greyed-out times are booked, unavailable or have passed.';
        this.timesTarget.replaceChildren(this.heading(iso), list, hint);
    }

    chooseSlot(iso, slot, list, button) {
        this.selectedTime = slot.time;
        this.whenTarget.value = `${iso}T${slot.time}`;
        list.querySelectorAll('.booking-slot').forEach((chip) => {
            chip.classList.toggle('is-selected', chip === button);
            chip.setAttribute('aria-pressed', String(chip === button));
        });
        this.updateSummary(slot.label);
    }

    format(time) {
        const [hours, minutes] = time.split(':').map(Number);
        return `${hours % 12 || 12}:${this.pad(minutes)} ${hours < 12 ? 'AM' : 'PM'}`;
    }

    updateSummary(label) {
        const ready = Boolean(this.selectedDay && this.selectedTime);
        if (this.hasSubmitTarget) {
            this.submitTarget.disabled = !ready;
        }
        if (!this.hasSummaryTarget) return;
        this.summaryTarget.hidden = !ready;
        if (ready) {
            const time = label ?? this.format(this.selectedTime);
            this.summaryTarget.textContent = `Selected: ${this.parse(this.selectedDay).toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })} at ${time}`;
        }
    }
}
