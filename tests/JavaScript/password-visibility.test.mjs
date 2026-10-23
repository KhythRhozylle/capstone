import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = readFileSync(new URL('../../assets/controllers/password-visibility_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", '')
    .replace('export default class', 'return class');
const PasswordVisibility = new Function('Controller', source)(class {});

function field() {
    const controller = new PasswordVisibility();
    controller.inputTarget = { type: 'password', value: 'MyTypedPassword!' };
    controller.buttonTarget = { attributes: {}, setAttribute(key, value) { this.attributes[key] = value; } };
    controller.connect();
    return controller;
}

test('eye button reveals and conceals only its own password without changing the value', () => {
    const first = field();
    const second = field();
    first.toggle();
    assert.equal(first.inputTarget.type, 'text');
    assert.equal(first.buttonTarget.attributes['aria-pressed'], 'true');
    assert.equal(first.buttonTarget.attributes['aria-label'], 'Hide password');
    assert.equal(second.inputTarget.type, 'password');
    assert.equal(first.inputTarget.value, 'MyTypedPassword!');
    first.toggle();
    assert.equal(first.inputTarget.type, 'password');
    assert.equal(first.buttonTarget.attributes['aria-label'], 'Show password');
});

test('navigation and cache cleanup conceal visible passwords', () => {
    const controller = field();
    controller.toggle();
    controller.hide();
    assert.equal(controller.inputTarget.type, 'password');
    controller.toggle();
    controller.disconnect();
    assert.equal(controller.inputTarget.type, 'password');
});
