/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

// Month calendar that marks days having appointments and lists the selected day's schedule.
export default class extends Controller {
    static targets = ['title', 'grid', 'list', 'listTitle'];
    static values = { events: { type: Array, default: [] } };

    connect() {
        const today = new Date();
        this.today = this.key(today.getFullYear(), today.getMonth(), today.getDate());
        this.year = today.getFullYear();
        this.month = today.getMonth();
        this.selected = this.today;
        this.render();
    }

    key(y, m, d) {
        return `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
    }

    prev() {
        this.shift(-1);
    }

    next() {
        this.shift(1);
    }

    shift(step) {
        const date = new Date(this.year, this.month + step, 1);
        this.year = date.getFullYear();
        this.month = date.getMonth();
        this.render();
    }

    pick(event) {
        this.selected = event.currentTarget.dataset.date;
        this.render();
    }

    render() {
        this.titleTarget.textContent = `${MONTHS[this.month]}, ${this.year}`;
        const counts = {};
        this.eventsValue.forEach((e) => {
            counts[e.date] = (counts[e.date] || 0) + 1;
        });
        const lead = new Date(this.year, this.month, 1).getDay();
        const days = new Date(this.year, this.month + 1, 0).getDate();
        this.gridTarget.replaceChildren();
        for (let i = 0; i < lead; i++) {
            this.gridTarget.append(document.createElement('span'));
        }
        for (let d = 1; d <= days; d++) {
            const key = this.key(this.year, this.month, d);
            const cell = document.createElement('button');
            cell.type = 'button';
            cell.textContent = d;
            cell.dataset.date = key;
            cell.dataset.action = 'mini-calendar#pick';
            cell.className = 'cal-day';
            if (key === this.today) cell.classList.add('is-today');
            if (key === this.selected) cell.classList.add('is-selected');
            if (counts[key]) {
                cell.classList.add('has-events');
                cell.setAttribute('aria-label', `${d} ${MONTHS[this.month]}, ${counts[key]} scheduled`);
            }
            this.gridTarget.append(cell);
        }
        this.renderList();
    }

    renderList() {
        const [, m, d] = this.selected.split('-').map(Number);
        this.listTitleTarget.textContent = this.selected === this.today ? 'Today' : `${MONTHS[m - 1]} ${d}`;
        this.listTarget.replaceChildren();
        const items = this.eventsValue.filter((e) => e.date === this.selected);
        if (!items.length) {
            const empty = document.createElement('li');
            empty.className = 'schedule-empty';
            empty.textContent = 'Nothing scheduled for this day.';
            this.listTarget.append(empty);
            return;
        }
        items.forEach((e) => {
            const li = document.createElement('li');
            const link = document.createElement('a');
            link.href = e.url;
            const time = document.createElement('b');
            time.textContent = e.time;
            const body = document.createElement('span');
            const title = document.createElement('strong');
            title.textContent = e.title;
            const sub = document.createElement('small');
            sub.textContent = `${e.sub} · ${e.status}`;
            body.append(title, sub);
            link.append(time, body);
            li.append(link);
            this.listTarget.append(li);
        });
    }
}
