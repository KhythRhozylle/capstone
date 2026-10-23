import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = readFileSync(new URL('../../assets/controllers/chat-photo_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", '')
    .replace('export default class', 'return class');
const Photo = new Function('Controller', source)(class {});

function picker() {
    const controller = new Photo();
    controller.inputTarget = { files: [], value: '' };
    controller.previewTarget = { hidden: true };
    controller.imageTarget = { removeAttribute(name) { delete this[name]; } };
    controller.filenameTarget = { textContent: '' };
    controller.statusTarget = { textContent: '' };
    return controller;
}

test('choosing and removing a photo updates the preview and clears the selected file', () => {
    const controller = picker();
    controller.inputTarget.files = [new File(['image'], 'campus.png', { type: 'image/png' })];
    controller.inputTarget.value = 'campus.png';
    controller.select();
    assert.equal(controller.previewTarget.hidden, false);
    assert.equal(controller.filenameTarget.textContent, 'campus.png');
    assert.match(controller.imageTarget.src, /^blob:/);
    controller.clear();
    assert.equal(controller.previewTarget.hidden, true);
    assert.equal(controller.inputTarget.value, '');
    assert.equal(controller.imageTarget.src, undefined);
    assert.equal(controller.previewUrl, null);
});

test('unsupported and oversized files show an error without keeping an attachment', () => {
    const controller = picker();
    for (const file of [{ name: 'script.svg', type: 'image/svg+xml', size: 50 }, { name: 'big.jpg', type: 'image/jpeg', size: 2_000_001 }]) {
        controller.inputTarget.files = [file];
        controller.inputTarget.value = file.name;
        controller.select();
        assert.equal(controller.previewTarget.hidden, true);
        assert.equal(controller.inputTarget.value, '');
        assert.match(controller.statusTarget.textContent, /up to 2 MB/);
    }
});

test('leaving the page releases the temporary image URL', () => {
    const controller = picker();
    controller.inputTarget.files = [new File(['image'], 'campus.webp', { type: 'image/webp' })];
    controller.select();
    controller.disconnect();
    assert.equal(controller.previewUrl, null);
});
