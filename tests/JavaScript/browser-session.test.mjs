import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { setImmediate } from 'node:timers/promises';
import test from 'node:test';

const source = readFileSync(new URL('../../assets/browser_session.js', import.meta.url), 'utf8');
const storageKey = 'norsu.browser-session';
const tabProof = 'ab'.repeat(32);

function storage(initial, blocked = false) {
    const values = new Map(initial ? [[storageKey, initial]] : []);
    return {
        values,
        getItem(key) {
            if (blocked) throw new Error('Storage disabled');
            return values.get(key) ?? null;
        },
        setItem(key, value) {
            if (blocked) throw new Error('Storage disabled');
            values.set(key, value);
        },
        removeItem(key) {
            if (blocked) throw new Error('Storage disabled');
            values.delete(key);
        },
    };
}

function browser({ proof, active = false, bridge = false, blockedStorage = false, href = 'https://example.test/portal', respond } = {}) {
    const documentHandlers = new Map();
    const windowHandlers = new Map();
    const requests = [];
    const navigations = [];
    const assignedNavigations = [];
    const historyChanges = [];
    const openedWindows = [];
    const sessionStorage = storage(proof, blockedStorage);
    const message = { textContent: 'Checking your browser session...' };
    const bridgeElement = bridge ? {
        dataset: { continueUrl: '/session/continue', target: '/portal?filter=open' },
        querySelector: () => message,
    } : null;
    let currentUrl = new URL(href);
    let reloads = 0;
    const document = {
        readyState: 'loading',
        addEventListener(name, handler, options = false) {
            const handlers = documentHandlers.get(name) ?? [];
            handlers.push({ handler, capture: options === true || options.capture === true });
            documentHandlers.set(name, handlers);
        },
        querySelector(selector) {
            if (selector === 'meta[name="browser-session-logout"]') return { content: '/logout' };
            if (selector === 'meta[name="browser-session-continue"]') return { content: '/session/continue' };
            if (selector === 'meta[name="browser-session-active"]') return active ? {} : null;
            if (selector === '[data-browser-session-bridge]') return bridgeElement;
            return null;
        },
        createElement(tag) {
            return { tag, attributes: {}, setAttribute(name, value) { this.attributes[name] = value; } };
        },
    };
    const window = {
        sessionStorage,
        crypto: { getRandomValues(bytes) { bytes.forEach((_, index) => { bytes[index] = index; }); return bytes; } },
        location: {
            get href() { return currentUrl.href; },
            get origin() { return currentUrl.origin; },
            replace(url) { navigations.push(String(url)); },
            assign(url) { assignedNavigations.push(String(url)); },
            reload() { reloads++; },
        },
        history: {
            state: { position: 3 },
            replaceState(state, title, url) {
                historyChanges.push({ state, title, url: String(url) });
                currentUrl = new URL(url, currentUrl);
            },
        },
        addEventListener(name, handler) {
            const handlers = windowHandlers.get(name) ?? [];
            handlers.push(handler);
            windowHandlers.set(name, handlers);
        },
        fetch(input, options) {
            requests.push({ input, options });
            return Promise.resolve(respond ? respond(input, options) : new Response('{}', { headers: { 'Content-Type': 'application/json' } }));
        },
        open(url, target) {
            const child = {
                sessionStorage: storage(),
                opener: window,
                navigations: [],
                location: { replace(url) { child.navigations.push(url); } },
                close() { child.closed = true; },
            };
            openedWindows.push({ url, target, child });
            return child;
        },
    };
    new Function('window', 'document', source)(window, document);
    return {
        window, document, requests, navigations, assignedNavigations, historyChanges, openedWindows, sessionStorage, message,
        get reloads() { return reloads; },
        emitDocument(name, event = {}) {
            for (const listener of documentHandlers.get(name) ?? []) {
                // formdata does not bubble from its form to document.
                if (name === 'formdata' && event.target !== document && !listener.capture) continue;
                listener.handler(event);
            }
        },
        emitWindow(name, event = {}) { for (const handler of windowHandlers.get(name) ?? []) handler(event); },
    };
}

function form({ login = false, method = 'post', action = 'https://example.test/profile' } = {}) {
    return {
        method, action, field: { value: '' }, messages: [],
        hasAttribute(name) { return login && name === 'data-browser-session-login'; },
        querySelector() { return this.field; },
        prepend(message) { this.messages.unshift(message); },
    };
}

function anchor({ href, target = '', turbo, download = false }) {
    return {
        href, target,
        hasAttribute(name) { return name === 'download' && download; },
        getAttribute(name) { return name === 'data-turbo' ? turbo ?? null : null; },
    };
}

function event(target, extra = {}) {
    return {
        target, defaultPrevented: false, button: 0,
        preventDefault() { this.defaultPrevented = true; },
        ...extra,
    };
}

test('submitting login creates and submits a fresh 256-bit tab proof', () => {
    const page = browser({ proof: tabProof });
    const login = form({ login: true });
    const submission = event(login);

    page.emitDocument('submit', submission);

    assert.equal(submission.defaultPrevented, false);
    assert.match(login.field.value, /^[a-f0-9]{64}$/);
    assert.notEqual(login.field.value, tabProof);
    assert.equal(page.sessionStorage.getItem(storageKey), login.field.value);
    assert.deepEqual(page.requests, []);
});

test('login is blocked with an accessible explanation when session storage is unavailable', () => {
    const page = browser({ blockedStorage: true });
    const login = form({ login: true });
    const submission = event(login);

    page.emitDocument('submit', submission);

    assert.equal(submission.defaultPrevented, true);
    assert.equal(login.field.value, '');
    assert.equal(login.messages[0].attributes.role, 'alert');
    assert.match(login.messages[0].textContent, /session storage/i);
    assert.deepEqual(page.requests, []);
});

test('same-origin fetch carries the proof without losing request headers, body, or options', async () => {
    const page = browser({ proof: tabProof });
    const controller = new AbortController();
    const originalHeaders = { Accept: 'application/json', 'X-CSRF-Token': 'csrf' };
    const options = { method: 'POST', body: '{"title":"Updated"}', credentials: 'same-origin', cache: 'no-store', signal: controller.signal, headers: originalHeaders };

    await page.window.fetch('/profile', options);

    const sent = page.requests[0];
    assert.equal(sent.input, '/profile');
    assert.equal(sent.options.headers.get('X-Browser-Session'), tabProof);
    assert.equal(sent.options.headers.get('Accept'), 'application/json');
    assert.equal(sent.options.headers.get('X-CSRF-Token'), 'csrf');
    for (const key of ['method', 'body', 'credentials', 'cache', 'signal']) assert.equal(sent.options[key], options[key]);
    assert.deepEqual(originalHeaders, { Accept: 'application/json', 'X-CSRF-Token': 'csrf' });
    assert.equal(options.headers, originalHeaders);
});

test('fetch preserves Request headers and never adds the proof to external requests', async () => {
    const page = browser({ proof: tabProof });
    const request = new Request('https://example.test/notifications', { headers: { Accept: 'text/html' } });

    await page.window.fetch(request);
    await page.window.fetch('https://external.test/collect', { headers: { Accept: 'application/json' } });

    assert.equal(page.requests[0].input, request);
    assert.equal(page.requests[0].options.headers.get('Accept'), 'text/html');
    assert.equal(page.requests[0].options.headers.get('X-Browser-Session'), tabProof);
    assert.equal(request.headers.has('X-Browser-Session'), false);
    assert.equal(new Headers(page.requests[1].options.headers).has('X-Browser-Session'), false);
});

test('opening a protected page after the tab closes logs out without fetching protected content', async () => {
    const page = browser({ bridge: true });

    page.emitDocument('DOMContentLoaded');
    await setImmediate();

    assert.deepEqual(page.requests, []);
    assert.deepEqual(page.navigations, ['/logout']);
});

test('refreshing an existing tab exchanges its proof for a native-navigation ticket once', async () => {
    const ticketUrl = '/portal?filter=open&_browser_continue=one-time-ticket';
    const page = browser({
        proof: tabProof, bridge: true,
        respond: () => new Response(JSON.stringify({ url: ticketUrl }), { headers: { 'Content-Type': 'application/json' } }),
    });

    page.emitDocument('DOMContentLoaded');
    await setImmediate();
    page.emitDocument('turbo:load');
    await setImmediate();

    assert.equal(page.requests.length, 1);
    assert.equal(page.requests[0].input, '/session/continue');
    assert.equal(page.requests[0].options.method, 'POST');
    assert.equal(page.requests[0].options.headers.get('X-Browser-Session'), tabProof);
    assert.equal(page.requests[0].options.headers.get('Content-Type'), 'application/json');
    assert.deepEqual(JSON.parse(page.requests[0].options.body), { target: '/portal?filter=open' });
    assert.deepEqual(page.navigations, [ticketUrl]);
});

test('the continuation ticket is removed from the address bar while preserving the query and fragment', () => {
    const page = browser({ proof: tabProof, active: true, href: 'https://example.test/portal?filter=open&_browser_continue=ticket#appointments' });

    page.emitDocument('DOMContentLoaded');

    assert.equal(page.window.location.href, 'https://example.test/portal?filter=open#appointments');
    assert.equal(page.historyChanges.length, 1);
    assert.deepEqual(page.historyChanges[0].state, { position: 3 });
    assert.deepEqual(page.navigations, []);
    assert.equal(page.sessionStorage.getItem(storageKey), tabProof);
});

test('intentional same-origin new windows inherit the proof before authorized navigation and lose the opener', async () => {
    for (const extra of [{ target: '_blank' }, { target: '', ctrlKey: true }, { target: '', metaKey: true }, { target: '', shiftKey: true }]) {
        const ticket = '/print?_browser_continue=ticket';
        const page = browser({ proof: tabProof, respond: () => new Response(JSON.stringify({ url: ticket })) });
        const link = anchor({ href: 'https://example.test/print', target: extra.target });
        const click = event({ closest: () => link }, { ctrlKey: extra.ctrlKey, metaKey: extra.metaKey, shiftKey: extra.shiftKey });

        page.emitDocument('click', click);
        assert.equal(page.openedWindows[0].child.navigations.length, 0);
        await setImmediate();

        assert.equal(click.defaultPrevented, true);
        assert.equal(page.openedWindows.length, 1);
        assert.equal(page.openedWindows[0].url, 'about:blank');
        assert.equal(page.openedWindows[0].child.sessionStorage.getItem(storageKey), tabProof);
        assert.equal(page.openedWindows[0].child.opener, null);
        assert.deepEqual(page.openedWindows[0].child.navigations, [ticket]);
        assert.equal(page.requests[0].options.headers.get('X-Browser-Session'), tabProof);
        assert.deepEqual(JSON.parse(page.requests[0].options.body), { target: '/print' });
    }
});

test('external links and ordinary same-tab links do not copy the proof to a new window', () => {
    const page = browser({ proof: tabProof });
    for (const link of [
        anchor({ href: 'https://external.test/print', target: '_blank' }),
        anchor({ href: 'https://example.test/profile', target: '' }),
    ]) {
        const click = event({ closest: () => link });
        page.emitDocument('click', click);
        assert.equal(click.defaultPrevented, false);
    }
    assert.deepEqual(page.openedWindows, []);
});

test('native POST forms carry the proof and preserve form fields', () => {
    const page = browser({ proof: tabProof });
    const formData = new FormData();
    formData.set('_token', 'csrf');
    formData.set('title', 'Updated');

    page.emitDocument('formdata', { target: form(), formData });

    assert.equal(formData.get('_browser_session'), tabProof);
    assert.equal(formData.get('_token'), 'csrf');
    assert.equal(formData.get('title'), 'Updated');
});

test('GET, external, and login forms do not receive an injected proof', () => {
    const page = browser({ proof: tabProof });
    for (const target of [
        form({ method: 'get' }),
        form({ method: 'GET' }),
        form({ action: 'https://external.test/collect' }),
        form({ login: true }),
    ]) {
        const formData = new FormData();
        formData.set('query', 'student');
        page.emitDocument('formdata', { target, formData });
        assert.equal(formData.has('_browser_session'), false);
        assert.equal(formData.get('query'), 'student');
    }
});

test('Turbo adds the proof to same-origin navigation headers while retaining existing headers', () => {
    const page = browser({ proof: tabProof });
    const request = { detail: { url: new URL('/portal', page.window.location.href), fetchOptions: { headers: { Accept: 'text/html', 'Turbo-Frame': 'calendar' } } } };

    page.emitDocument('turbo:before-fetch-request', request);

    const headers = new Headers(request.detail.fetchOptions.headers);
    assert.equal(headers.get('X-Browser-Session'), tabProof);
    assert.equal(headers.get('Accept'), 'text/html');
    assert.equal(headers.get('Turbo-Frame'), 'calendar');
    // Symfony's CSRF controller also writes headers with property assignment.
    request.detail.fetchOptions.headers['X-CSRF-Token'] = 'csrf';
    assert.equal(new Headers(request.detail.fetchOptions.headers).get('X-CSRF-Token'), 'csrf');

    const headerObject = new Headers({ Accept: 'text/html' });
    const requestWithHeaders = { detail: { url: new URL('/portal', page.window.location.href), fetchOptions: { headers: headerObject } } };
    page.emitDocument('turbo:before-fetch-request', requestWithHeaders);
    assert.equal(requestWithHeaders.detail.fetchOptions.headers, headerObject);
    assert.equal(headerObject.get('X-Browser-Session'), tabProof);

    const external = { detail: { url: new URL('https://external.test/'), fetchOptions: { headers: { Accept: 'text/html' } } } };
    page.emitDocument('turbo:before-fetch-request', external);
    assert.equal(new Headers(external.detail.fetchOptions.headers).has('X-Browser-Session'), false);
});

test('restoring a protected page from the browser cache reloads it for server validation', () => {
    const page = browser({ proof: tabProof, active: true });

    page.emitWindow('pageshow', { persisted: true });

    assert.equal(page.reloads, 1);
    assert.deepEqual(page.requests, []);
    assert.deepEqual(page.navigations, []);
});

test('an expired server response clears the tab proof and navigates to logout', async () => {
    const page = browser({ proof: tabProof, respond: () => new Response('{}', { status: 401, headers: { 'X-Browser-Session-Expired': '1' } }) });

    const response = await page.window.fetch('/notifications');

    assert.equal(response.status, 401);
    assert.equal(page.sessionStorage.getItem(storageKey), null);
    assert.deepEqual(page.navigations, ['/logout']);
});

test('authenticated pages without proof log out and public pages clear old tab proof', () => {
    const activePage = browser({ active: true });
    activePage.emitDocument('DOMContentLoaded');
    assert.deepEqual(activePage.navigations, ['/logout']);

    const publicPage = browser({ proof: tabProof });
    publicPage.emitDocument('DOMContentLoaded');
    assert.equal(publicPage.sessionStorage.getItem(storageKey), null);
    assert.deepEqual(publicPage.navigations, []);
});

test('an unavailable continuation service shows a retry message without releasing protected content', async () => {
    const page = browser({ proof: tabProof, bridge: true, respond: () => Promise.reject(new Error('Network unavailable')) });

    page.emitDocument('DOMContentLoaded');
    await setImmediate();

    assert.match(page.message.textContent, /Refresh the page to try again/);
    assert.deepEqual(page.navigations, []);
    assert.equal(page.requests.length, 1);
});



test('native download links authorize a ticket before navigation and retain the original workspace', async () => {
    for (const options of [{ turbo: 'false' }, { download: true }]) {
        let release;
        const response = new Promise((resolve) => { release = resolve; });
        const page = browser({ proof: tabProof, respond: () => response });
        const link = anchor({ href: 'https://example.test/documents/export?year=2026#register', ...options });
        const click = event({ closest: () => link });

        page.emitDocument('click', click);

        assert.equal(click.defaultPrevented, true);
        assert.equal(page.window.location.href, 'https://example.test/portal');
        assert.deepEqual(page.assignedNavigations, []);
        assert.equal(page.requests[0].input, '/session/continue');
        assert.equal(page.requests[0].options.headers.get('X-Browser-Session'), tabProof);
        assert.deepEqual(JSON.parse(page.requests[0].options.body), { target: '/documents/export?year=2026' });

        release(new Response(JSON.stringify({ url: '/documents/export?year=2026&_browser_continue=ticket' })));
        await setImmediate();

        assert.deepEqual(page.assignedNavigations, ['/documents/export?year=2026&_browser_continue=ticket#register']);
        assert.deepEqual(page.navigations, []);
        assert.equal(page.window.location.href, 'https://example.test/portal');
    }
});

test('explicit logout bypasses native-link continuation', async () => {
    const page = browser({ proof: tabProof });
    const click = event({ closest: () => anchor({ href: 'https://example.test/logout', turbo: 'false' }) });

    page.emitDocument('click', click);
    await setImmediate();

    assert.equal(click.defaultPrevented, false);
    assert.deepEqual(page.requests, []);
    assert.deepEqual(page.assignedNavigations, []);
});

test('a rejected new-window continuation logs out and closes the unused blank window', async () => {
    const page = browser({ proof: tabProof, respond: () => new Response('{}', { status: 401 }) });
    const click = event({ closest: () => anchor({ href: 'https://example.test/print', target: '_blank' }) });

    page.emitDocument('click', click);
    await setImmediate();

    assert.deepEqual(page.navigations, ['/logout']);
    assert.equal(page.openedWindows[0].child.closed, true);
    assert.deepEqual(page.openedWindows[0].child.navigations, []);
});

test('Turbo never fabricates a proof for a newly opened tab', () => {
    const page = browser();
    const request = { detail: { url: new URL('/portal', page.window.location.href), fetchOptions: { headers: { Accept: 'text/html' } } } };

    page.emitDocument('turbo:before-fetch-request', request);

    assert.equal(new Headers(request.detail.fetchOptions.headers).has('X-Browser-Session'), false);
});


test('a redirected native download continuation logs out without parsing login HTML as a ticket', async () => {
    let parsed = false;
    const page = browser({
        proof: tabProof,
        respond: () => ({
            ok: true, redirected: true, headers: new Headers(),
            json() { parsed = true; throw new Error('Login HTML is not a continuation ticket'); },
        }),
    });
    const click = event({ closest: () => anchor({ href: 'https://example.test/documents/export', turbo: 'false' }) });

    page.emitDocument('click', click);
    await setImmediate();

    assert.equal(click.defaultPrevented, true);
    assert.equal(parsed, false);
    assert.equal(page.sessionStorage.getItem(storageKey), null);
    assert.deepEqual(page.navigations, ['/logout']);
    assert.deepEqual(page.assignedNavigations, []);
});

test('a redirected bridge continuation logs out before parsing the expired-session login response', async () => {
    let parsed = false;
    const page = browser({
        proof: tabProof, bridge: true,
        respond: () => ({
            ok: true, redirected: true, headers: new Headers(),
            json() { parsed = true; throw new Error('Login HTML is not a continuation ticket'); },
        }),
    });

    page.emitDocument('DOMContentLoaded');
    await setImmediate();

    assert.equal(parsed, false);
    assert.equal(page.sessionStorage.getItem(storageKey), null);
    assert.deepEqual(page.navigations, ['/logout']);
    assert.deepEqual(page.assignedNavigations, []);
    assert.equal(page.requests.length, 1);
});

