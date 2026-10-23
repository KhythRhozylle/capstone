import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = readFileSync(new URL('../../assets/controllers/logout-confirmation_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", '')
    .replace('export default class', 'return class');
const LogoutConfirmation = new Function('Controller', source)(class {});

test('sign out opens the dialog and No closes it without navigating', () => {
    const controller = new LogoutConfirmation();
    controller.dialogTarget = {
        open: false,
        showModal() { this.open = true; },
        close() { this.open = false; },
    };
    controller.open();
    assert.equal(controller.dialogTarget.open, true);
    controller.close();
    assert.equal(controller.dialogTarget.open, false);
    controller.open();
    controller.disconnect();
    assert.equal(controller.dialogTarget.open, false);
});
