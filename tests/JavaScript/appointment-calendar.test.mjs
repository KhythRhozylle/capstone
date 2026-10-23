import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = readFileSync(new URL('../../assets/controllers/appointment-calendar_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", '').replace('export default class', 'return class');
const Calendar = new Function('Controller', source)(class {});

test('reconnecting the calendar does not duplicate its filters', () => {
    const oldOption = globalThis.Option;
    globalThis.Option = class { constructor(label, value) { this.label = label; this.value = value; } };
    const select = () => ({ options: [], replaceChildren() { this.options = []; }, append(option) { this.options.push(option); } });
    try {
        const controller = new Calendar();
        controller.statusFilterTarget = select();
        controller.unitFilterTarget = select();
        controller.statusesValue = [{ label: 'Approved', value: 'approved' }];
        controller.eventsValue = [{ unit: 'CARE' }];
        controller.fillFilters();
        controller.fillFilters();
        assert.equal(controller.statusFilterTarget.options.length, 2);
        assert.equal(controller.unitFilterTarget.options.length, 2);
    } finally { globalThis.Option = oldOption; }
});

test('availability submissions block repeat clicks and release the button on timeout', async () => {
    const oldFetch = globalThis.fetch;
    const oldTimeout = globalThis.setTimeout;
    const oldClear = globalThis.clearTimeout;
    let expire;
    let requests = 0;
    globalThis.setTimeout = (callback) => { expire = callback; return 1; };
    globalThis.clearTimeout = () => {};
    globalThis.fetch = (_url, { signal }) => {
        requests++;
        return new Promise((_resolve, reject) => signal.addEventListener('abort', () => reject(signal.reason)));
    };
    try {
        const controller = new Calendar();
        controller.selectedSlot = { date: '2026-11-02', start: 540, unavailable: true };
        controller.availabilityActionTarget = { disabled: false };
        controller.availabilityErrorTarget = { hidden: true };
        const first = controller.saveAvailability();
        await controller.saveAvailability();
        assert.equal(requests, 1);
        assert.equal(controller.availabilityActionTarget.disabled, true);
        expire();
        await first;
        assert.equal(controller.availabilityActionTarget.disabled, false);
        assert.equal(controller.availabilityErrorTarget.hidden, false);
        assert.match(controller.availabilityErrorTarget.textContent, /Check availability before retrying/);
    } finally {
        globalThis.fetch = oldFetch;
        globalThis.setTimeout = oldTimeout;
        globalThis.clearTimeout = oldClear;
    }
});

test('admins choose whole-day or hourly availability', () => {
    const c = new Calendar();
    c.selectedSlot = {date: '2026-11-02'};
    c.periodsValue = [{date: '2026-11-02', start: 540, end: 600}];
    c.availabilityScopeTarget = {value: 'day'};
    c.availabilityTimeTarget = {value: '540'};
    c.availabilityTimeFieldTarget = {};
    c.availabilityLabelTarget = {};
    c.availabilityActionTarget = {disabled: false};
    c.availabilityErrorTarget = {};
    c.changeAvailability();
    assert.deepEqual(c.selectedSlot, {date: '2026-11-02', start: 0, wholeDay: true, unavailable: true});
    assert.equal(c.availabilityTimeFieldTarget.hidden, true);
    c.availabilityScopeTarget.value = 'time';
    c.changeAvailability();
    assert.deepEqual(c.selectedSlot, {date: '2026-11-02', start: 540, wholeDay: false, unavailable: false});
    assert.equal(c.availabilityTimeFieldTarget.hidden, false);
    c.availabilityTimeTarget.value = '660';
    c.changeAvailability();
    assert.equal(c.selectedSlot.start, 660);
    assert.equal(c.selectedSlot.unavailable, true);
});

test('calendar opens the next booking week when the current week has no records', () => {
    const c = new Calendar();
    const future = new Date();
    future.setDate(future.getDate() + 21);
    const date = future.getFullYear() + '-' + String(future.getMonth() + 1).padStart(2, '0') + '-' + String(future.getDate()).padStart(2, '0');
    c.eventsValue = [{date, time: '10:00'}];
    assert.equal(c.initialDate().getTime(), new Date(date + 'T10:00').getTime());
    c.eventsValue = [];
    assert.ok(Math.abs(c.initialDate() - new Date()) < 1000);
});
