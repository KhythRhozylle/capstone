import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = readFileSync(new URL('../../assets/controllers/chat-inbox_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", '')
    .replace('export default class', 'return class');
const Inbox = new Function('Controller', source)(class {});
globalThis.window = { location: { origin: 'https://workspace.test', pathname: '/messages' } };

function inbox(query = '') {
    const controller = new Inbox();
    controller.searchTarget = { value: query };
    controller.resultsTarget = {
        innerHTML: 'Original conversations',
        attributes: {},
        setAttribute(name, value) { this.attributes[name] = value; },
        removeAttribute(name) { delete this.attributes[name]; },
        querySelectorAll() { return []; },
    };
    controller.statusTarget = { textContent: '' };
    controller.searchUrlValue = '/messages/search';
    controller.viewValue = 'chat';
    controller.connect();
    return controller;
}

const response = (html) => ({ ok: true, redirected: false, text: async () => html });

test('typing searches the server and clearing restores the conversation list', async () => {
    const controller = inbox();
    let calledUrl;
    const oldFetch = globalThis.fetch;
    globalThis.fetch = async (url) => { calledUrl = url; return response('Lily search results'); };
    try {
        controller.searchTarget.value = 'Lily Rivera';
        await controller.load();
        assert.equal(calledUrl.pathname, '/messages/search');
        assert.equal(calledUrl.searchParams.get('q'), 'Lily Rivera');
        assert.equal(controller.resultsTarget.innerHTML, 'Lily search results');
        assert.equal(controller.resultsTarget.attributes['aria-busy'], undefined);
        controller.clear();
        assert.equal(controller.resultsTarget.innerHTML, 'Original conversations');
        assert.equal(controller.searchTarget.value, '');
    } finally { globalThis.fetch = oldFetch; controller.disconnect(); }
});

test('a slower previous search cannot overwrite newer matching accounts', async () => {
    const controller = inbox();
    const oldFetch = globalThis.fetch;
    let resolveOld;
    globalThis.fetch = () => new Promise((resolve) => { resolveOld = resolve; });
    try {
        controller.searchTarget.value = 'Li';
        const oldRequest = controller.load();
        controller.searchTarget.value = 'Lily';
        globalThis.fetch = async () => response('Latest results');
        await controller.load();
        resolveOld(response('Outdated results'));
        await oldRequest;
        assert.equal(controller.resultsTarget.innerHTML, 'Latest results');
    } finally { globalThis.fetch = oldFetch; controller.disconnect(); }
});

test('clearing a search opened from a bookmarked URL reloads unfiltered conversations', async () => {
    const controller = inbox('Lily');
    const oldFetch = globalThis.fetch;
    let url;
    globalThis.fetch = async (value) => { url = value; return response('Unfiltered chats'); };
    try {
        controller.searchTarget.value = '';
        await controller.load();
        assert.equal(url.searchParams.get('q'), '');
        assert.equal(url.searchParams.get('view'), 'chat');
        assert.equal(controller.resultsTarget.innerHTML, 'Unfiltered chats');
        assert.equal(controller.originalResults, 'Unfiltered chats');
    } finally { globalThis.fetch = oldFetch; controller.disconnect(); }
});

test('leaving the page cancels pending searches and ignores late responses', async () => {
    const controller = inbox();
    const oldFetch = globalThis.fetch;
    let resolve;
    globalThis.fetch = () => new Promise((done) => { resolve = done; });
    try {
        controller.searchTarget.value = 'Lily';
        const request = controller.load();
        const signal = controller.pending.signal;
        controller.disconnect();
        assert.equal(signal.aborted, true);
        resolve(response('Late results'));
        await request;
        assert.equal(controller.resultsTarget.innerHTML, 'Original conversations');
    } finally { globalThis.fetch = oldFetch; }
});
