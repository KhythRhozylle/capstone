// The login cookie is shared by tabs. Keep an additional proof in this tab only.
const storageKey = 'norsu.browser-session';
const proofHeader = 'X-Browser-Session';
const nativeFetch = window.fetch.bind(window);
const checkedBridges = new WeakSet();

function proof() {
    try { return window.sessionStorage.getItem(storageKey); } catch { return null; }
}

function endSession() {
    try { window.sessionStorage.removeItem(storageKey); } catch { /* Storage may be disabled. */ }
    const logout = document.querySelector('meta[name="browser-session-logout"]');
    if (logout) window.location.replace(logout.content);
}

window.fetch = (input, options = {}) => {
    const url = new URL(input instanceof Request ? input.url : input, window.location.href);
    const key = proof();
    if (url.origin === window.location.origin && key) {
        const headers = new Headers(options.headers ?? (input instanceof Request ? input.headers : undefined));
        headers.set(proofHeader, key);
        options = { ...options, headers };
    }
    return nativeFetch(input, options).then((response) => {
        if (response.headers.get('X-Browser-Session-Expired') === '1') endSession();
        return response;
    });
};

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!form.hasAttribute('data-browser-session-login')) return;
    try {
        const bytes = window.crypto.getRandomValues(new Uint8Array(32));
        const key = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
        window.sessionStorage.setItem(storageKey, key);
        form.querySelector('input[name="_browser_session"]').value = key;
    } catch {
        event.preventDefault();
        const message = document.createElement('p');
        message.setAttribute('role', 'alert');
        message.textContent = 'Enable browser session storage to sign in.';
        form.prepend(message);
    }
}, true);

// Native multipart forms, as well as Turbo forms, need the same tab proof.
document.addEventListener('formdata', (event) => {
    const form = event.target;
    if (form.method.toLowerCase() === 'get' || form.hasAttribute('data-browser-session-login')) return;
    if (new URL(form.action, window.location.href).origin !== window.location.origin) return;
    const key = proof();
    if (key) event.formData.set('_browser_session', key);
}, true);

document.addEventListener('turbo:before-fetch-request', (event) => {
    const key = proof();
    if (!key || event.detail.url.origin !== window.location.origin) return;
    const headers = event.detail.fetchOptions.headers;
    if (headers instanceof Headers) headers.set(proofHeader, key);
    else headers[proofHeader] = key;
});

async function continuationUrl(target, endpoint) {
    const response = await window.fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ target }),
    });
    if (!response.ok || response.redirected) {
        endSession();
        return null;
    }
    return (await response.json()).url;
}

// Authorize native downloads before navigating, so attachments leave the
// original workspace visible. Intentional new windows receive the tab proof.
document.addEventListener('click', async (event) => {
    if (event.defaultPrevented || event.button !== 0) return;
    const link = event.target.closest('a[href]');
    if (!link) return;
    const newWindow = link.target === '_blank' || event.ctrlKey || event.metaKey || event.shiftKey;
    if (!newWindow && !link.hasAttribute('download') && link.getAttribute('data-turbo') !== 'false') return;
    const url = new URL(link.href, window.location.href);
    const key = proof();
    const endpoint = document.querySelector('meta[name="browser-session-continue"]');
    const logout = document.querySelector('meta[name="browser-session-logout"]');
    if (!key || !endpoint || url.origin !== window.location.origin || url.href === new URL(logout.content, window.location.href).href) return;
    const tab = newWindow ? window.open('about:blank', '_blank') : null;
    if (newWindow && !tab) return;
    event.preventDefault();
    try {
        if (tab) {
            tab.sessionStorage.setItem(storageKey, key);
            tab.opener = null;
        }
        const target = url.pathname + url.search;
        const next = await continuationUrl(target, endpoint.content);
        if (!next) {
            tab?.close();
            return;
        }
        if (tab) tab.location.replace(next + url.hash);
        else window.location.assign(next + url.hash);
    } catch {
        tab?.close();
    }
});

async function checkPage() {
    const url = new URL(window.location.href);
    if (url.searchParams.has('_browser_continue')) {
        url.searchParams.delete('_browser_continue');
        window.history.replaceState(window.history.state, '', url);
    }
    const bridge = document.querySelector('[data-browser-session-bridge]');
    if (!bridge) {
        if (document.querySelector('meta[name="browser-session-active"]')) {
            if (!proof()) endSession();
        } else {
            try { window.sessionStorage.removeItem(storageKey); } catch { /* Already signed out. */ }
        }
        return;
    }
    if (checkedBridges.has(bridge)) return;
    checkedBridges.add(bridge);
    if (!proof()) {
        endSession();
        return;
    }
    try {
        const response = await window.fetch(bridge.dataset.continueUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ target: bridge.dataset.target }),
        });
        if (!response.ok || response.redirected) {
            endSession();
            return;
        }
        const continuation = await response.json();
        window.location.replace(continuation.url);
    } catch {
        bridge.querySelector('[data-session-message]').textContent = 'Unable to check your session. Refresh the page to try again.';
    }
}

document.addEventListener('turbo:load', checkPage);
window.addEventListener('pageshow', (event) => {
    if (event.persisted && document.querySelector('meta[name="browser-session-active"]')) window.location.reload();
    else checkPage();
});
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', checkPage);
else checkPage();
