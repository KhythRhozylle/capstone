/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';

const DURATION = 60; // appointments have no end time in the system, so each one shows as a one-hour block
const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const HOUR_PX = 60;

const pad = (n) => String(n).padStart(2, '0');
const ymd = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const addDays = (d, n) => new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);
const minutes = (t) => Number(t.slice(0, 2)) * 60 + Number(t.slice(3, 5));
const clock = (m) => {
    const h = Math.floor(m / 60) % 24;
    return `${(h % 12) || 12}:${pad(m % 60)} ${h < 12 ? 'AM' : 'PM'}`;
};
const hourLabel = (h) => `${(h % 12) || 12} ${h < 12 ? 'AM' : 'PM'}`;

function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
}

// Staff week/day/month appointment calendar with a detail panel for the selected visit.
export default class extends Controller {
    static targets = ['range', 'viewButton', 'statusFilter', 'unitFilter', 'kpis', 'board', 'detail', 'availabilityPopup', 'availabilityLabel', 'availabilityAction', 'availabilityError', 'availabilityScope', 'availabilityTime', 'availabilityTimeField'];
    static values = { events: Array, statuses: Array, unavailable: Array, officeLabel: String, editable: Boolean, periods: Array, availabilityUrl: String, csrf: String };

    connect() {
        this.view = 'week';
        this.cursor = this.initialDate();
        this.selectedId = null;
        this.status = '';
        this.unit = '';
        this.fillFilters();
        this.render();
        this.timer = setInterval(() => this.updateNowLine(), 60000);
    }

    initialDate() {
        const now = new Date();
        const start = this.weekStart(now);
        const end = addDays(start, 7);
        const dates = this.eventsValue.map((event) => new Date(event.date + 'T' + event.time)).sort((a, b) => a - b);
        if (dates.some((date) => date >= start && date < end)) return now;
        return dates.find((date) => date >= now) || now;
    }

    disconnect() {
        clearInterval(this.timer);
        this.availabilityRequest?.abort();
    }

    fillFilters() {
        this.statusFilterTarget.replaceChildren();
        this.unitFilterTarget.replaceChildren();
        this.statusFilterTarget.append(new Option('All status', ''));
        this.statusesValue.forEach((s) => this.statusFilterTarget.append(new Option(s.label, s.value)));
        this.unitFilterTarget.append(new Option('All units', ''));
        [...new Set(this.eventsValue.map((e) => e.unit))].sort().forEach((u) => this.unitFilterTarget.append(new Option(u, u)));
        this.unitFilterTarget.hidden = this.unitFilterTarget.options.length <= 2;
    }

    // ----- navigation -----
    today() {
        this.cursor = new Date();
        this.render();
    }

    prev() {
        this.step(-1);
    }

    next() {
        this.step(1);
    }

    step(dir) {
        if (this.view === 'month') {
            this.cursor = new Date(this.cursor.getFullYear(), this.cursor.getMonth() + dir, 1);
        } else {
            this.cursor = addDays(this.cursor, dir * (this.view === 'week' ? 7 : 1));
            while (this.view === 'day' && [0, 6].includes(this.cursor.getDay())) this.cursor = addDays(this.cursor, dir);
        }
        this.render();
    }

    setView(event) {
        this.view = event.currentTarget.dataset.view;
        this.render();
    }

    filter() {
        this.status = this.statusFilterTarget.value;
        this.unit = this.unitFilterTarget.value;
        this.render();
    }

    select(event) {
        this.selectedId = Number(event.currentTarget.dataset.id);
        const picked = this.eventsValue.find((e) => e.id === this.selectedId);
        if (picked) {
            this.cursor = new Date(`${picked.date}T00:00`);
        }
        this.renderBoard();
        this.renderDetail();
    }

    openDay(event) {
        this.cursor = new Date(`${event.currentTarget.dataset.date}T00:00`);
        this.view = 'day';
        this.render();
    }

    async saveAvailability() {
        if (!this.selectedSlot || this.availabilityActionTarget.disabled) return;
        this.availabilityActionTarget.disabled = true;
        this.availabilityErrorTarget.hidden = true;
        const request = this.availabilityRequest = new AbortController();
        const timeout = setTimeout(() => request.abort(new Error('The request timed out. Check availability before retrying.')), 15000);
        try {
            const response = await fetch(this.availabilityUrlValue, {
                method: 'POST', credentials: 'same-origin', signal: request.signal,
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': this.csrfValue},
                body: JSON.stringify(this.selectedSlot),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || data.detail || 'Unable to update availability. Please try again.');
            this.periodsValue = data.periods;
            this.unavailableValue = data.periods.filter((period) => period.start === 0 && period.end === 1440).map((period) => period.date);
            this.availabilityPopupTarget.hidePopover();
            this.renderBoard();
        } catch (error) {
            this.availabilityErrorTarget.textContent = error.message;
            this.availabilityErrorTarget.hidden = false;
        } finally {
            clearTimeout(timeout);
            this.availabilityActionTarget.disabled = false;
        }
    }

    changeAvailability() {
        if (!this.selectedSlot || this.availabilityActionTarget.disabled) return;
        const wholeDay = this.availabilityScopeTarget.value === 'day';
        const start = wholeDay ? 0 : Number(this.availabilityTimeTarget.value);
        const date = this.selectedSlot.date;
        const blocked = this.periodsValue.some((period) => period.date === date && (
            wholeDay ? period.start === 0 && period.end === 1440 : period.start < start + 60 && period.end > start
        ));
        this.selectedSlot = {date, start, wholeDay, unavailable: !blocked};
        this.availabilityTimeFieldTarget.hidden = wholeDay;
        this.availabilityLabelTarget.textContent = date + ' · ' + (wholeDay ? 'whole day' : clock(start) + ' – ' + clock(start + 60));
        this.availabilityActionTarget.textContent = blocked ? 'Make available' : 'Mark as unavailable';
        this.availabilityErrorTarget.hidden = true;
    }

    enableAvailability(cell, date, firstHour = null) {
        if (!this.editableValue) return;
        const key = ymd(date);
        cell.classList.add('calendar-editable-day');
        cell.tabIndex = 0;
        cell.setAttribute('role', 'button');
        const wholeDayBlocked = this.periodsValue.some((period) => period.date === key && period.start === 0 && period.end === 1440);
        const hint = firstHour === null
            ? (wholeDayBlocked ? 'Click to make your whole day available again' : 'Click to mark your whole day as unavailable')
            : 'Click to mark this hour as unavailable';
        cell.setAttribute('aria-label', firstHour === null ? `${hint} (${key})` : `Set availability for ${key}`);
        cell.title = hint;
        const open = (event) => {
            if (event.target.closest('button, a, input, select')) return;
            if (event.type === 'keydown' && !['Enter', ' '].includes(event.key)) return;
            if (event.type === 'keydown') event.preventDefault();
            const wholeDay = firstHour === null;
            let start = 0;
            let blocked;
            if (wholeDay) {
                blocked = this.periodsValue.some((period) => period.date === key && period.start === 0 && period.end === 1440);
                this.selectedSlot = {date: key, start, unavailable: !blocked, wholeDay: true};
                this.availabilityLabelTarget.textContent = `${date.toLocaleDateString(undefined, {weekday: 'long', month: 'long', day: 'numeric'})} · whole day`;
                this.availabilityActionTarget.textContent = blocked ? 'Make whole day available' : 'Mark whole day unavailable';
            } else {
                const offset = event.type === 'click' ? Math.max(0, event.clientY - cell.getBoundingClientRect().top) : 0;
                start = Math.min(23 * 60, (firstHour + Math.floor(offset / HOUR_PX)) * 60);
                blocked = this.periodsValue.some((period) => period.date === key && period.start < start + 60 && period.end > start);
                this.selectedSlot = {date: key, start, unavailable: !blocked};
                this.availabilityLabelTarget.textContent = `${key} · ${clock(start)} – ${clock(start + 60)}`;
                this.availabilityActionTarget.textContent = blocked ? 'Make available' : 'Mark as unavailable';
            }
            this.availabilityScopeTarget.value = wholeDay ? 'day' : 'time';
            this.availabilityTimeTarget.value = String(start);
            this.changeAvailability();
            const popup = this.availabilityPopupTarget;
            const rect = cell.getBoundingClientRect();
            popup.style.left = `${Math.max(8, Math.min(event.clientX || rect.left, window.innerWidth - 290))}px`;
            popup.style.top = `${Math.max(8, Math.min(event.clientY || rect.top, window.innerHeight - 300))}px`;
            popup.showPopover();
        };
        cell.addEventListener('click', open);
        cell.addEventListener('keydown', open);
    }

    // ----- data -----
    visible() {
        return this.eventsValue.filter((e) => (!this.status || e.status === this.status) && (!this.unit || e.unit === this.unit));
    }

    weekStart(date) {
        const offset = (date.getDay() + 6) % 7;
        return addDays(date, -offset);
    }

    visibleDays() {
        if (this.view === 'day') return [new Date(this.cursor)];
        const start = this.weekStart(this.cursor);
        return Array.from({ length: 5 }, (_, i) => addDays(start, i));
    }

    // ----- rendering -----
    render() {
        this.viewButtonTargets.forEach((b) => b.classList.toggle('is-active', b.dataset.view === this.view));
        this.renderRange();
        this.renderKpis();
        this.renderBoard();
        this.renderDetail();
    }

    renderRange() {
        const c = this.cursor;
        if (this.view === 'month') {
            this.rangeTarget.textContent = `${MONTHS[c.getMonth()]} ${c.getFullYear()}`;
        } else if (this.view === 'day') {
            this.rangeTarget.textContent = `${DAYS[c.getDay()]}, ${MONTHS[c.getMonth()]} ${c.getDate()}, ${c.getFullYear()}`;
        } else {
            const days = this.visibleDays();
            const a = days[0];
            const b = days[days.length - 1];
            this.rangeTarget.textContent = a.getMonth() === b.getMonth()
                ? `${MONTHS[a.getMonth()]} ${a.getDate()} – ${b.getDate()}, ${b.getFullYear()}`
                : `${MONTHS[a.getMonth()].slice(0, 3)} ${a.getDate()} – ${MONTHS[b.getMonth()].slice(0, 3)} ${b.getDate()}, ${b.getFullYear()}`;
        }
    }

    renderKpis() {
        const all = this.visible();
        const end = new Date();
        const startThis = addDays(end, -7);
        const startPrev = addDays(end, -14);
        const within = (e, from, to) => {
            const d = new Date(`${e.date}T00:00`);
            return d > from && d <= to;
        };
        const cards = [
            { label: 'Total appointments', pick: () => true },
            { label: 'Completed', pick: (e) => e.status === 'completed' },
            { label: 'No-show', pick: (e) => e.status === 'no_show' },
            { label: 'Pending approval', pick: (e) => e.status === 'pending' },
        ];
        this.kpisTarget.replaceChildren();
        cards.forEach((card, i) => {
            const now = all.filter((e) => card.pick(e) && within(e, startThis, addDays(end, 1))).length;
            const before = all.filter((e) => card.pick(e) && within(e, startPrev, startThis)).length;
            const item = el('article', 'appt-kpi');
            item.append(el('p', 'appt-kpi-label', card.label), el('small', 'appt-kpi-sub', 'Last 7 days'));
            const row = el('div', 'appt-kpi-row');
            row.append(el('strong', '', String(now)));
            let delta = 'No change';
            let tone = 'is-flat';
            if (before > 0) {
                const pct = Math.round(((now - before) / before) * 100);
                delta = `${pct > 0 ? '+' : ''}${pct}%`;
                tone = pct > 0 ? 'is-up' : pct < 0 ? 'is-down' : 'is-flat';
            } else if (now > 0) {
                delta = `+${now} new`;
                tone = 'is-up';
            }
            const chip = el('span', `appt-delta ${tone}`, delta);
            row.append(chip, el('small', '', 'vs last week'));
            item.append(row);
            item.style.setProperty('--i', i);
            this.kpisTarget.append(item);
        });
    }

    renderBoard() {
        this.boardTarget.replaceChildren();
        this.boardTarget.dataset.view = this.view;
        if (this.view === 'month') {
            this.renderMonth();
        } else {
            this.renderTimeGrid();
        }
    }

    renderTimeGrid() {
        const days = this.visibleDays();
        const events = this.visible();
        const keys = new Set(days.map(ymd));
        const inRange = events.filter((e) => keys.has(e.date));
        let first = 8;
        let last = 19;
        inRange.forEach((e) => {
            first = Math.min(first, Math.floor(minutes(e.time) / 60));
            last = Math.max(last, Math.ceil((minutes(e.time) + DURATION) / 60));
        });
        this.periodsValue.filter((period) => keys.has(period.date) && (period.start !== 0 || period.end !== 1440)).forEach((period) => {
            first = Math.min(first, Math.floor(period.start / 60));
            last = Math.max(last, Math.ceil(period.end / 60));
        });
        last = Math.min(last, 24);
        const hours = last - first;
        const todayKey = ymd(new Date());

        const grid = el('div', 'tg');
        grid.style.setProperty('--cols', days.length);
        grid.style.setProperty('--hour', `${HOUR_PX}px`);

        const head = el('div', 'tg-head');
        head.append(el('div', 'tg-corner'));
        days.forEach((d) => {
            const count = events.filter((e) => e.date === ymd(d)).length;
            const cell = el('div', `tg-day${ymd(d) === todayKey ? ' is-today' : ''}`);
            cell.append(el('span', 'tg-num', String(d.getDate())));
            const meta = el('span', 'tg-meta');
            meta.append(el('b', '', DAYS[d.getDay()].toUpperCase()), el('small', '', `${count} appointment${count === 1 ? '' : 's'}`));
            cell.append(meta);
            this.enableAvailability(cell, d);
            head.append(cell);
        });
        grid.append(head);

        const body = el('div', 'tg-body');
        body.style.height = `${hours * HOUR_PX}px`;
        const axis = el('div', 'tg-axis');
        for (let h = first; h < last; h++) {
            const label = el('span', '', hourLabel(h));
            label.style.top = `${(h - first) * HOUR_PX}px`;
            axis.append(label);
        }
        body.append(axis);

        days.forEach((d) => {
            const col = el('div', `tg-col${ymd(d) === todayKey ? ' is-today' : ''}`);
            col.dataset.date = ymd(d);
            this.enableAvailability(col, d, first);
            this.periodsValue.filter((period) => period.date === ymd(d) && (period.start !== 0 || period.end !== 1440)).forEach((period) => {
                const start = Math.max(first * 60, period.start);
                const end = Math.min(last * 60, period.end);
                if (end <= start) return;
                const blocked = el('div', 'calendar-unavailable-period', `Unavailable ${clock(period.start)} – ${clock(period.end)}`);
                blocked.style.top = `${((start - first * 60) / 60) * HOUR_PX}px`;
                blocked.style.height = `${((end - start) / 60) * HOUR_PX}px`;
                col.append(blocked);
            });
            if (this.unavailableValue.includes(ymd(d))) {
                col.classList.add('is-unavailable');
                col.append(el('span', 'office-unavailable-label', `${this.officeLabelValue || 'Office'} unavailable`));
            }
            this.layout(events.filter((e) => e.date === ymd(d))).forEach(({ event: e, lane, lanes }) => {
                const top = ((minutes(e.time) - first * 60) / 60) * HOUR_PX;
                const block = el('button', `tg-event st-${e.status}${e.id === this.selectedId ? ' is-selected' : ''}`);
                block.type = 'button';
                block.dataset.id = e.id;
                block.dataset.action = 'appointment-calendar#select';
                block.style.top = `${top}px`;
                block.style.height = `${(DURATION / 60) * HOUR_PX - 3}px`;
                block.style.left = `calc(${(lane / lanes) * 100}% + 2px)`;
                block.style.width = `calc(${100 / lanes}% - 4px)`;
                block.setAttribute('aria-label', `${e.student}, ${clock(minutes(e.time))}, ${e.statusLabel}`);
                block.append(el('span', 'tg-time', `${clock(minutes(e.time))} ${e.student}`), el('small', '', e.unit));
                col.append(block);
            });
            body.append(col);
        });

        const now = new Date();
        if (keys.has(todayKey)) {
            const nowMin = now.getHours() * 60 + now.getMinutes();
            if (nowMin >= first * 60 && nowMin <= last * 60) {
                const line = el('div', 'tg-now');
                line.dataset.first = first;
                line.style.top = `${((nowMin - first * 60) / 60) * HOUR_PX}px`;
                line.append(el('span', '', clock(nowMin)));
                body.append(line);
            }
        }
        grid.append(body);
        this.boardTarget.append(grid);

        if (!inRange.length) {
            this.boardTarget.append(el('p', 'tg-empty', 'No appointments in this period.'));
        }
    }

    // Splits overlapping appointments into side-by-side lanes.
    layout(list) {
        const sorted = [...list].sort((a, b) => minutes(a.time) - minutes(b.time));
        const placed = [];
        let group = [];
        let groupEnd = -1;
        const flush = () => {
            const lanes = Math.max(1, ...group.map((g) => g.lane + 1));
            group.forEach((g) => placed.push({ ...g, lanes }));
            group = [];
        };
        sorted.forEach((event) => {
            const start = minutes(event.time);
            if (group.length && start >= groupEnd) {
                flush();
                groupEnd = -1;
            }
            const used = group.filter((g) => minutes(g.event.time) + DURATION > start).map((g) => g.lane);
            let lane = 0;
            while (used.includes(lane)) lane++;
            group.push({ event, lane });
            groupEnd = Math.max(groupEnd, start + DURATION);
        });
        if (group.length) flush();
        return placed;
    }

    updateNowLine() {
        const line = this.boardTarget.querySelector('.tg-now');
        if (!line) return;
        const now = new Date();
        const first = Number(line.dataset.first);
        line.style.top = `${((now.getHours() * 60 + now.getMinutes() - first * 60) / 60) * HOUR_PX}px`;
        line.firstChild.textContent = clock(now.getHours() * 60 + now.getMinutes());
    }

    renderMonth() {
        const events = this.visible();
        const y = this.cursor.getFullYear();
        const m = this.cursor.getMonth();
        const first = new Date(y, m, 1);
        let start = this.weekStart(first);
        if ([0, 6].includes(first.getDay())) start = addDays(start, 7);
        const weeks = Math.ceil((Math.round((new Date(y, m + 1, 0) - start) / 864e5) + 1) / 7);
        const todayKey = ymd(new Date());
        const wrap = el('div', 'mg');
        ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'].forEach((d) => wrap.append(el('div', 'mg-head', d)));
        for (let i = 0; i < weeks * 7; i++) {
            const d = addDays(start, i);
            if ([0, 6].includes(d.getDay())) continue;
            const key = ymd(d);
            const items = events.filter((e) => e.date === key);
            const cell = el('div', `mg-cell${d.getMonth() !== m ? ' is-outside' : ''}${key === todayKey ? ' is-today' : ''}`);
            if (this.unavailableValue.includes(key)) {
                cell.classList.add('is-unavailable');
                cell.append(el('small', 'office-unavailable-label', `${this.officeLabelValue || 'Office'} unavailable`));
            }
            this.enableAvailability(cell, d);
            const timed = this.periodsValue.filter((period) => period.date === key && (period.start !== 0 || period.end !== 1440));
            timed.forEach((period) => cell.append(el('small', 'office-unavailable-label', `Unavailable ${clock(period.start)} – ${clock(period.end)}`)));
            const num = el('button', 'mg-num', String(d.getDate()));
            num.type = 'button';
            num.dataset.date = key;
            num.dataset.action = 'appointment-calendar#openDay';
            num.setAttribute('aria-label', `Open ${DAYS[d.getDay()]} ${MONTHS[d.getMonth()]} ${d.getDate()}`);
            cell.append(num);
            items.slice(0, 3).forEach((e) => {
                const chip = el('button', `mg-chip st-${e.status}${e.id === this.selectedId ? ' is-selected' : ''}`, `${clock(minutes(e.time))} ${e.student}`);
                chip.type = 'button';
                chip.dataset.id = e.id;
                chip.dataset.action = 'appointment-calendar#select';
                cell.append(chip);
            });
            if (items.length > 3) {
                const more = el('button', 'mg-more', `+${items.length - 3} more`);
                more.type = 'button';
                more.dataset.date = key;
                more.dataset.action = 'appointment-calendar#openDay';
                cell.append(more);
            }
            wrap.append(cell);
        }
        this.boardTarget.append(wrap);
    }

    renderDetail() {
        const panel = this.detailTarget;
        panel.replaceChildren();
        const e = this.eventsValue.find((item) => item.id === this.selectedId);
        if (!e) {
            const empty = el('div', 'appt-detail-empty');
            empty.append(el('strong', '', 'No appointment selected'), el('p', '', 'Choose a visit on the calendar to see the student, schedule and quick actions.'));
            panel.append(empty);
            return;
        }
        panel.classList.remove('is-pop');
        void panel.offsetWidth;
        panel.classList.add('is-pop');

        panel.append(el('div', `appt-detail-status st-${e.status}`, e.statusLabel));

        const student = el('div', 'appt-student');
        const initials = e.student.split(/\s+/).map((w) => w[0]).slice(0, 2).join('').toUpperCase();
        const who = el('div');
        who.append(el('strong', '', e.student), el('small', '', e.email));
        student.append(el('span', 'appt-avatar', initials), who);
        panel.append(student);

        const start = minutes(e.time);
        const when = el('div', 'appt-when');
        const times = el('div');
        times.append(el('strong', '', `${clock(start)}  →  ${clock(start + DURATION)}`), el('small', '', new Date(`${e.date}T00:00`).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' })));
        when.append(times, el('span', 'appt-until', this.until(e)));
        panel.append(when);

        const note = el('div', 'appt-note');
        note.append(el('small', '', 'Purpose'), el('p', '', e.purpose));
        panel.append(note);

        const actions = el('div', 'appt-actions');
        const act = (label, status, tone) => {
            const btn = el('button', `appt-btn ${tone}`, label);
            btn.type = 'button';
            btn.disabled = e.status === status;
            btn.addEventListener('click', () => this.submitStatus(e, status));
            return btn;
        };
        actions.append(act('Mark as Arrived', 'checked_in', 'is-primary'), act('Mark as No Show', 'no_show', 'is-ghost'), act('Complete', 'completed', 'is-ghost'));
        panel.append(actions);

        const info = el('dl', 'appt-info');
        [['Reference', e.ref], ['Unit', e.unit], ['Campus', e.campus], ['Mode', e.mode]].forEach(([k, v]) => info.append(el('dt', '', k), el('dd', '', v)));
        panel.append(el('h3', 'appt-section', 'Details'), info);

        const links = el('div', 'appt-links');
        const open = el('a', 'appt-link', 'Open full record');
        open.href = e.url;
        links.append(open);
        if (e.meetingUrl) {
            const join = el('a', 'appt-link', 'Join meeting');
            join.href = e.meetingUrl;
            join.target = '_blank';
            join.rel = 'noopener noreferrer';
            links.append(join);
        }
        panel.append(links);
    }

    until(e) {
        const diff = new Date(`${e.date}T${e.time}`) - new Date();
        if (diff < 0) return 'Past visit';
        const mins = Math.round(diff / 60000);
        if (mins < 60) return `Starts in ${mins} min`;
        const hours = Math.round(mins / 60);
        return hours < 24 ? `Starts in ${hours} hr` : `Starts in ${Math.round(hours / 24)} day${Math.round(hours / 24) === 1 ? '' : 's'}`;
    }

    submitStatus(e, status) {
        const form = document.createElement('form');
        form.method = 'post';
        form.action = e.statusUrl;
        [['_token', e.token], ['status', status], ['note', '']].forEach(([name, value]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.append(input);
        });
        document.body.append(form);
        form.submit();
    }
}
