import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = readFileSync(new URL('../../assets/controllers/document-sheet_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", '').replace('export default class', 'return class');
const Sheet = new Function('Controller', source)(class {});

function row(id = 'new', value = 'Entered particular') {
    const root = 'office_record_' + id;
    const message = {textContent: ''};
    const button = {disabled: false};
    const item = {dataset: {new: String(id === 'new')}, classList: {add() {}, toggle() {}}, setAttribute() {}};
    const field = {name: root + '[particular]', value, type: 'textarea', tagName: 'TEXTAREA', readOnly: false, selectionStart: 3, selectionEnd: 3,
        closest: () => item, focus() { globalThis.document.activeElement = this; }, setSelectionRange(start, end) { this.selectionStart = start; this.selectionEnd = end; }};
    const token = {name: root + '[_token]', value: 'csrf', type: 'hidden'};
    const form = {action: id === 'new' ? '/new?inline=1' : '/' + id + '/edit?inline=1', elements: [field, token], valid: true, checkValidity() { return this.valid; }, closest: () => item};
    item.querySelector = (selector) => selector === 'form' ? form : message;
    item.querySelectorAll = () => [button];
    item.contains = (value) => value === field;
    item.parentElement = {appended: [], append(value) { this.appended.push(value); }};
    item.replaceWith = (replacement) => { item.replacement = replacement; replacement.parentElement = item.parentElement; };
    return {row: item, form, field, message, button};
}

async function environment(run) {
    const previous = {fetch: globalThis.fetch, FormData: globalThis.FormData, document: globalThis.document};
    globalThis.FormData = class {constructor(form) { this.values = form?.elements.map((field) => [field.name, field.value]); } set() {}};
    globalThis.document = {activeElement: null};
    try { await run(); } finally { Object.assign(globalThis, previous); }
}

const submit = (controller, form) => controller.save({target: form, preventDefault() {}});

test('typing debounces automatic saves and clears earlier timers', () => {
    const previous = {setTimeout: globalThis.setTimeout, clearTimeout: globalThis.clearTimeout};
    const timers = new Map();
    let counter = 0;
    globalThis.setTimeout = (callback) => { timers.set(++counter, callback); return counter; };
    globalThis.clearTimeout = (id) => timers.delete(id);
    try {
        const controller = new Sheet();
        const entry = row();
        let saves = 0;
        controller.flush = (value) => { assert.equal(value, entry.row); saves++; };
        controller.dirty({target: entry.field, type: 'input'});
        controller.dirty({target: entry.field, type: 'input'});
        assert.equal(timers.size, 1);
        [...timers.values()][0]();
        assert.equal(saves, 1);
        assert.equal(controller.stateFor(entry.row).dirty, true);
        controller.disconnect();
    } finally { Object.assign(globalThis, previous); }
});

test('incomplete new rows wait for required fields instead of creating partial records', () => {
    const controller = new Sheet();
    const entry = row();
    entry.form.valid = false;
    controller.stateFor(entry.row).dirty = true;
    let saved = false;
    controller.save = () => { saved = true; };
    controller.flush(entry.row);
    assert.equal(saved, false);
    assert.match(entry.message.textContent, /Complete the required fields/);
    entry.form.valid = true;
    controller.flush(entry.row);
    assert.equal(saved, true);
});

test('autosaving keeps inputs editable and queues newer changes using the saved record ID', async () => environment(async () => {
    const controller = new Sheet();
    const entry = row();
    const saved = row(12, 'Entered particular');
    const blank = row();
    let complete;
    let requests = 0;
    globalThis.fetch = (_url, options) => {
        requests++;
        assert.equal(options.headers.Accept, 'application/json');
        return new Promise((resolve) => { complete = resolve; });
    };
    controller.rowFrom = (html) => html === 'saved' ? saved.row : blank.row;
    const scheduled = [];
    controller.schedule = (value) => scheduled.push(value);
    globalThis.document.activeElement = entry.field;
    const pending = submit(controller, entry.form);
    await submit(controller, entry.form);
    assert.equal(requests, 1);
    assert.equal(entry.field.disabled, undefined);
    entry.field.value = 'Latest edits while saving';
    controller.dirty({target: entry.field, type: 'input'});
    complete({ok: true, status: 200, json: async () => ({html: 'saved', newRow: 'blank'})});
    await pending;
    assert.equal(saved.field.value, 'Latest edits while saving');
    assert.equal(entry.row.parentElement.appended.length, 1);
    assert.equal(entry.row.parentElement.appended[0], blank.row);
    assert.equal(scheduled.at(-1), saved.row);
    assert.equal(saved.form.action, '/12/edit?inline=1');
    assert.equal(globalThis.document.activeElement, saved.field);
    const latest = row(12, saved.field.value);
    controller.rowFrom = () => latest.row;
    globalThis.fetch = async (url) => {
        assert.equal(url, '/12/edit?inline=1');
        return {ok: true, status: 200, json: async () => ({html: 'latest', newRow: null})};
    };
    await controller.flush(saved.row);
    assert.equal(latest.message.textContent, 'Saved automatically');
    assert.equal(controller.stateFor(latest.row).dirty, false);
    assert.equal(latest.row.parentElement.appended.length, 1);
}));

test('validation errors remain in the row without moving focus to another cell', async () => environment(async () => {
    const controller = new Sheet();
    const entry = row(1);
    const invalid = row(1);
    controller.rowFrom = () => invalid.row;
    globalThis.fetch = async () => ({ok: false, status: 422, json: async () => ({html: 'invalid'})});
    await submit(controller, entry.form);
    assert.equal(entry.row.replacement, invalid.row);
    assert.equal(controller.stateFor(invalid.row).dirty, true);
    assert.match(invalid.message.textContent, /correct the highlighted fields/);
    assert.equal(globalThis.document.activeElement, null);
}));

test('an unconfirmed new-record request retains entries and prevents duplicate creation', async () => environment(async () => {
    const controller = new Sheet();
    const entry = row();
    let requests = 0;
    globalThis.fetch = async () => { requests++; throw new Error('Network unavailable'); };
    await submit(controller, entry.form);
    await submit(controller, entry.form);
    assert.equal(entry.row.replacement, undefined);
    assert.equal(entry.field.value, 'Entered particular');
    assert.equal(requests, 1);
    assert.equal(entry.row.dataset.busy, 'false');
    assert.match(entry.message.textContent, /Save could not be confirmed/);
}));

test('existing records retain entries on failure and retry on the next edit', async () => environment(async () => {
    const controller = new Sheet();
    const entry = row(2);
    globalThis.fetch = async () => { throw new Error('Offline'); };
    await submit(controller, entry.form);
    assert.match(entry.message.textContent, /Not saved/);
    assert.equal(controller.stateFor(entry.row).uncertain, false);
    const saved = row(2);
    controller.rowFrom = () => saved.row;
    globalThis.fetch = async () => ({ok: true, status: 200, json: async () => ({html: 'saved', newRow: null})});
    await controller.flush(entry.row);
    assert.equal(saved.message.textContent, 'Saved automatically');
}));

test('Insert Add row appends a blank row without replacing entries already being edited', async () => environment(async () => {
    const controller = new Sheet();
    const existing = row(1, 'Unsaved existing entry');
    const inserted = row('new');
    let focused = false;
    let scrolled = false;
    inserted.row.querySelector = () => ({focus() { focused = true; }});
    inserted.row.scrollIntoView = () => { scrolled = true; };
    controller.rowFrom = () => inserted.row;
    const body = {rows: [existing.row], append(value) { this.rows.push(value); }};
    controller.element = {querySelector: () => body};
    controller.toolbarMessage = () => {};
    const details = {open: true};
    const button = {disabled: false, dataset: {url: '/staff/document-records/row'}, closest: () => details};
    globalThis.fetch = async () => ({ok: true, json: async () => ({html: 'blank row'})});
    await controller.addRow({currentTarget: button});
    assert.equal(body.rows.length, 2);
    assert.equal(body.rows[0], existing.row);
    assert.equal(existing.field.value, 'Unsaved existing entry');
    assert.equal(details.open, false);
    assert.equal(button.disabled, false);
    assert.equal(focused, true);
    assert.equal(scrolled, true);
}));

test('Insert Add column creates cells associated with each matching office row form', async () => environment(async () => {
    const controller = new Sheet();
    const owned = row(1);
    const foreign = row(2);
    owned.row.dataset.unitId = '7';
    foreign.row.dataset.unitId = '8';
    owned.form.name = owned.form.id = 'office_record_1';
    for (const entry of [owned, foreign]) {
        entry.row.insertBefore = (cell) => { entry.cell = cell; };
        entry.row.querySelector = (selector) => selector === 'form' ? entry.form : null;
    }
    const header = {querySelector: () => null, insertBefore(value) { this.heading = value; }};
    controller.element = {querySelector: () => header, querySelectorAll: () => [owned.row, foreign.row]};
    globalThis.document.createElement = (tag) => ({tag, dataset: {}, children: [], append(value) { this.children.push(value); }, setAttribute(name, value) { this[name] = value; }});
    controller.insertColumn({id: 4, label: 'Reference number', unitId: 7});
    assert.equal(header.heading.textContent, 'Reference number');
    assert.equal(owned.cell.children[0].name, 'office_record_1[column_4]');
    assert.equal(owned.cell.children[0].form, 'office_record_1');
    assert.equal(owned.cell.children[0].maxLength, 2000);
    assert.equal(foreign.cell.textContent, '—');
}));

test('invalid column insertion preserves the name input and reports the server validation error', async () => environment(async () => {
    const controller = new Sheet();
    const button = {disabled: false};
    let reset = false;
    const form = {elements: [], action: '/columns/new', querySelector: () => button, reset() { reset = true; }};
    let message;
    controller.toolbarMessage = (value) => { message = value; };
    globalThis.fetch = async () => ({ok: false, status: 422, json: async () => ({message: 'This column already exists in your office.'})});
    await controller.addColumn({target: form, preventDefault() {}});
    assert.match(message, /already exists/);
    assert.equal(reset, false);
    assert.equal(button.disabled, false);
}));

test('saving an inserted row keeps existing blank rows without adding duplicates', async () => environment(async () => {
    const controller = new Sheet();
    const entry = row();
    const saved = row(5);
    entry.row.parentElement.querySelector = () => ({dataset: {new: 'true'}});
    controller.rowFrom = () => saved.row;
    globalThis.fetch = async () => ({ok: true, status: 200, json: async () => ({html: 'saved', newRow: 'another blank'})});
    await submit(controller, entry.form);
    assert.equal(entry.row.parentElement.appended.length, 0);
    assert.equal(saved.message.textContent, 'Saved automatically');
}));

test('row insertion sends the selected quantity and appends every returned row', async () => environment(async () => {
    const controller = new Sheet();
    const rows = [row(), row(), row()];
    rows.forEach((entry) => { entry.row.querySelector = () => ({focus() {}}); entry.row.scrollIntoView = () => {}; });
    controller.rowFrom = (html) => rows[Number(html)].row;
    const body = {rows: [], append(value) { this.rows.push(value); }};
    controller.element = {querySelector: () => body};
    controller.toolbarMessage = () => {};
    const menu = {open: true, querySelector: () => ({value: '3'})};
    const button = {dataset: {url: '/staff/document-records/row'}, closest: () => menu};
    globalThis.fetch = async (url) => {
        assert.equal(url, '/staff/document-records/row?count=3');
        return {ok: true, json: async () => ({rows: ['0', '1', '2']})};
    };
    await controller.addRow({currentTarget: button});
    assert.equal(body.rows.length, 3);
}));

test('column insertion displays every column returned by a batch', async () => environment(async () => {
    const controller = new Sheet();
    const button = {disabled: false};
    const form = {elements: [], action: '/columns/new', querySelector: () => button, reset() {}};
    const columns = [{id: 1, label: 'Field 1'}, {id: 2, label: 'Field 2'}];
    const inserted = [];
    controller.insertColumn = (column) => inserted.push(column);
    controller.hideColumn = () => {};
    let message;
    controller.toolbarMessage = (value) => { message = value; };
    globalThis.fetch = async () => ({ok: true, json: async () => ({columns})});
    await controller.addColumn({target: form, preventDefault() {}});
    assert.deepEqual(inserted, columns);
    assert.match(message, /2 columns added/);
}));
