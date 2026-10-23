import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = readFileSync(new URL('../../assets/submission_guard.js', import.meta.url), 'utf8');

test('duplicate submissions are blocked until success, failure or cached navigation releases the form', () => {
    const handlers = {};
    const pageHandlers = {};
    new Function('document', 'window', source)(
        { addEventListener: (name, handler) => { handlers[name] = handler; } },
        { addEventListener: (name, handler) => { pageHandlers[name] = handler; } },
    );
    const attrs = new Map();
    const form = { getAttribute: (name) => attrs.get(name) ?? null, setAttribute: (name, value) => attrs.set(name, value), removeAttribute: (name) => attrs.delete(name) };
    let blocked = false;
    const event = { target: form, preventDefault: () => { blocked = true; } };
    handlers.submit(event);
    assert.equal(blocked, false);
    handlers['turbo:submit-start'](event);
    assert.equal(attrs.get('aria-busy'), 'true');
    handlers.submit(event);
    assert.equal(blocked, true);
    handlers['turbo:submit-end'](event);
    assert.equal(attrs.has('aria-busy'), false);
    blocked = false;
    handlers.submit(event);
    assert.equal(blocked, false);
    for (const release of [handlers['turbo:before-cache'], pageHandlers.pageshow]) {
        handlers['turbo:submit-start'](event);
        release();
        blocked = false;
        handlers.submit(event);
        assert.equal(blocked, false);
        assert.equal(attrs.has('aria-busy'), false);
    }
});
