import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = readFileSync(new URL('../../assets/controllers/office-document-identity_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", '').replace('export default class', 'return class');
const Identity = new Function('Controller', source)(class {});

function setup(original = '') {
    const controller = new Identity();
    controller.actorValue = 'Dianne Futalan';
    const fields = Object.fromEntries(Object.entries({releasedAt: '', releasedBy: original, status: 'pending'}).map(([key, value]) => [key, {name: 'office_record_1[' + key + ']', value}]));
    controller.element = {elements: Object.values(fields)};
    controller.connect();
    return {controller, fields};
}

test('the active profile automatically fills the releaser when a release date or released status is entered', () => {
    const {controller, fields} = setup();
    assert.equal(fields.releasedBy.value, '');
    fields.releasedAt.value = '2026-10-24';
    controller.update();
    assert.equal(fields.releasedBy.value, 'Dianne Futalan');
    fields.releasedAt.value = '';
    controller.update();
    assert.equal(fields.releasedBy.value, '');
    fields.status.value = 'released';
    controller.update();
    assert.equal(fields.releasedBy.value, 'Dianne Futalan');
});

test('editing under a different profile keeps the original releaser and ignores other row events', () => {
    const {controller, fields} = setup('Cesar Estrope');
    fields.status.value = 'released';
    controller.update();
    assert.equal(fields.releasedBy.value, 'Cesar Estrope');
    const other = setup();
    other.fields.status.value = 'released';
    other.controller.update({target: {form: {}}});
    assert.equal(other.fields.releasedBy.value, '');
});

test('switching profiles in the menu updates an unsaved row without changing recorded names', () => {
    const {controller, fields} = setup();
    const receiver = {name: 'office_record_new[receivedBy]', value: 'Dianne Futalan'};
    controller.element.elements.push(receiver);
    controller.newValue = true;
    fields.status.value = 'released';
    controller.profileChanged({detail: {name: 'Mark Raymond P. Baldado'}});
    assert.equal(receiver.value, 'Mark Raymond P. Baldado');
    assert.equal(fields.releasedBy.value, 'Mark Raymond P. Baldado');
    controller.newValue = false;
    controller.originalReleaser = 'Cesar Estrope';
    controller.profileChanged({detail: {name: 'Dianne Futalan'}});
    assert.equal(receiver.value, 'Mark Raymond P. Baldado');
    assert.equal(fields.releasedBy.value, 'Cesar Estrope');
});

test('profile menu announces the name after the profile label updates and releases its observer', () => {
    const menuSource = readFileSync(new URL('../../assets/controllers/office-profile-menu_controller.js', import.meta.url), 'utf8')
        .replace("import { Controller } from '@hotwired/stimulus';", '').replace('export default class', 'return class');
    const Menu = new Function('Controller', menuSource)(class {});
    const previous = {MutationObserver: globalThis.MutationObserver, window: globalThis.window, CustomEvent: globalThis.CustomEvent};
    let changed;
    let announced;
    let disconnected = false;
    globalThis.MutationObserver = class {constructor(callback) { changed = callback; } observe() {} disconnect() { disconnected = true; }};
    globalThis.CustomEvent = class {constructor(type, data) { this.type = type; this.detail = data.detail; }};
    globalThis.window = {dispatchEvent(event) { announced = event; }};
    try {
        const label = {textContent: 'Cesar Estrope ▾'};
        const menu = new Menu();
        menu.element = {querySelector: () => label};
        menu.connect();
        label.textContent = 'Dianne Futalan ▾';
        changed();
        assert.equal(announced.type, 'office-profile:changed');
        assert.equal(announced.detail.name, 'Dianne Futalan');
        menu.disconnect();
        assert.equal(disconnected, true);
    } finally { Object.assign(globalThis, previous); }
});
