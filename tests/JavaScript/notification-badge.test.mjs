import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = readFileSync(new URL('../../assets/controllers/notification-badge_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", '')
    .replace('export default class', 'return class');
const Badge = new Function('Controller', source)(class {});

test('all badges display the exact count and disappear at zero', () => {
    const controller = new Badge();
    controller.badgeTargets = [{}, {}];
    const link = { attributes: {}, setAttribute(key, value) { this.attributes[key] = value; } };
    controller.linkTargets = [link];
    controller.update(105);
    controller.badgeTargets.forEach((badge) => {
        assert.equal(badge.textContent, '105');
        assert.equal(badge.hidden, false);
    });
    assert.equal(link.attributes['aria-label'], 'Notifications, 105 unread');
    controller.update(1);
    assert.equal(controller.badgeTargets[0].textContent, '1');
    controller.update(0);
    controller.badgeTargets.forEach((badge) => {
        assert.equal(badge.textContent, '');
        assert.equal(badge.hidden, true);
    });
    assert.equal(link.attributes['aria-label'], 'Notifications');
});


test('server-rendered badges do not issue another request on connect or immediate focus', async () => {
    const oldDocument = globalThis.document;
    const oldFetch = globalThis.fetch;
    globalThis.document = { hidden: false, documentElement: { hasAttribute: () => false } };
    let requests = 0;
    globalThis.fetch = async () => { requests++; return { ok: false }; };
    const controller = new Badge();
    try {
        controller.connect();
        await controller.refresh();
        assert.equal(requests, 0);
        controller.lastRefresh = Date.now() - 16000;
        await controller.refresh();
        await controller.refresh();
        assert.equal(requests, 1);
    } finally {
        controller.disconnect();
        globalThis.document = oldDocument;
        globalThis.fetch = oldFetch;
    }
});
