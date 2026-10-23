import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = readFileSync(new URL('../../assets/controllers/chat-file_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", '')
    .replace('export default class', 'return class');
const Picker = new Function('Controller', source)(class {});

function picker() {
    const controller = new Picker();
    controller.inputTarget = { files: [], value: '' };
    controller.previewTarget = { hidden: true };
    controller.filenameTarget = { textContent: '' };
    controller.sizeTarget = { textContent: '' };
    controller.statusTarget = { textContent: '' };
    return controller;
}

test('all file types show the filename and size without restricting extensions', () => {
    const controller = picker();
    for (const name of ['report.pdf', 'archive.zip', 'script.php', 'page.html', 'file.unknown', 'README']) {
        controller.inputTarget.files = [{ name, type: '', size: 2048 }];
        controller.select();
        assert.equal(controller.filenameTarget.textContent, name);
        assert.equal(controller.sizeTarget.textContent, '2.0 KB');
        assert.equal(controller.previewTarget.hidden, false);
        assert.equal(controller.statusTarget.textContent, '');
    }
});

test('removing a selected file clears its preview and upload field', () => {
    const controller = picker();
    controller.inputTarget.files = [{ name: 'report.pdf', size: 20 }];
    controller.inputTarget.value = 'report.pdf';
    controller.select();
    controller.clear();
    assert.equal(controller.inputTarget.value, '');
    assert.equal(controller.previewTarget.hidden, true);
    assert.equal(controller.filenameTarget.textContent, '');
    assert.equal(controller.sizeTarget.textContent, '');
});

test('oversized files display an error and are removed before sending', () => {
    const controller = picker();
    controller.inputTarget.files = [{ name: 'big.zip', size: 2_000_001 }];
    controller.inputTarget.value = 'big.zip';
    controller.select();
    assert.equal(controller.inputTarget.value, '');
    assert.equal(controller.previewTarget.hidden, true);
    assert.match(controller.statusTarget.textContent, /up to 2 MB/);
});
