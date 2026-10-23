import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = readFileSync(new URL('../../assets/controllers/slot-picker_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", '').replace('export default class', 'return class');
const Picker = new Function('Controller', source)(class {});
const picker = () => {
    const controller = new Picker();
    controller.unitValue = 1;
    controller.whenTarget = { value: '2026-11-02T09:00' };
    controller.urlValue = '/slots';
    controller.message = (text) => { controller.note = text; };
    controller.render = (data) => { controller.data = data; };
    return controller;
};

test('changing to an empty date cancels the previous slot request and ignores its late response', async () => {
    const original = globalThis.fetch;
    let resolve;
    let signal;
    globalThis.fetch = (_url, options) => { signal = options.signal; return new Promise((done) => { resolve = done; }); };
    try {
        const controller = picker();
        const old = controller.load();
        controller.whenTarget.value = '';
        await controller.load();
        assert.equal(signal.aborted, true);
        resolve({ ok: true, json: async () => ({ slots: [{time: '09:00'}] }) });
        await old;
        assert.equal(controller.data, undefined);
        assert.equal(controller.note, 'Pick a date to see the open slots.');
    } finally { globalThis.fetch = original; }
});

test('an unsuccessful slot response shows a recoverable error and the next request can succeed', async () => {
    const original = globalThis.fetch;
    try {
        const controller = picker();
        globalThis.fetch = async () => ({ ok: false });
        await controller.load();
        assert.match(controller.note, /Could not load/);
        globalThis.fetch = async () => ({ ok: true, json: async () => ({ slots: [] }) });
        await controller.load();
        assert.deepEqual(controller.data, { slots: [] });
    } finally { globalThis.fetch = original; }
});

test('a slot timeout leaves the date field usable and displays a retry message', async () => {
    const originalFetch = globalThis.fetch;
    const originalTimeout = globalThis.setTimeout;
    const originalClear = globalThis.clearTimeout;
    let expire;
    globalThis.setTimeout = (callback) => { expire = callback; return 1; };
    globalThis.clearTimeout = () => {};
    globalThis.fetch = (_url, { signal }) => new Promise((_resolve, reject) => signal.addEventListener('abort', () => reject(signal.reason)));
    try {
        const controller = picker();
        const request = controller.load();
        expire();
        await request;
        assert.match(controller.note, /Could not load/);
        assert.equal(controller.whenTarget.value, '2026-11-02T09:00');
    } finally {
        globalThis.fetch = originalFetch;
        globalThis.setTimeout = originalTimeout;
        globalThis.clearTimeout = originalClear;
    }
});
