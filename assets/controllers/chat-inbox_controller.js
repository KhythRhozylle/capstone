/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['search', 'results', 'status', 'messages'];
    static values = { searchUrl: String, view: String };

    connect() {
        this.originalResults = this.searchTarget.value.trim() ? null : this.resultsTarget.innerHTML;
        this.requestVersion = 0;
        if (this.hasMessagesTarget) this.messagesTarget.scrollTop = this.messagesTarget.scrollHeight;
    }

    disconnect() {
        clearTimeout(this.timer);
        this.pending?.abort();
        this.requestVersion++;
    }

    submitSearch(event) {
        event.preventDefault();
        clearTimeout(this.timer);
        this.load();
    }

    search() {
        clearTimeout(this.timer);
        this.pending?.abort();
        this.requestVersion++;
        this.timer = setTimeout(() => this.load(), 200);
    }

    clear() {
        this.searchTarget.value = '';
        clearTimeout(this.timer);
        this.load();
    }

    async load() {
        this.pending?.abort();
        const version = ++this.requestVersion;
        const query = this.searchTarget.value.trim();
        if (!query && this.originalResults !== null) {
            this.resultsTarget.innerHTML = this.originalResults;
            this.resultsTarget.removeAttribute('aria-busy');
            this.statusTarget.textContent = '';
            return;
        }
        this.pending = new AbortController();
        const url = new URL(this.searchUrlValue, window.location.origin);
        url.searchParams.set('q', query);
        url.searchParams.set('view', this.viewValue);
        this.resultsTarget.setAttribute('aria-busy', 'true');
        this.statusTarget.textContent = 'Searching accounts…';
        const request = this.pending;
        const timeout = setTimeout(() => request.abort(new Error('Search timed out')), 10000);
        try {
            const response = await fetch(url, { signal: this.pending.signal, headers: { Accept: 'text/html' }, credentials: 'same-origin', cache: 'no-store' });
            if (!response.ok || response.redirected) throw new Error('Search unavailable');
            const html = await response.text();
            if (version !== this.requestVersion) return;
            this.resultsTarget.innerHTML = html;
            if (!query) this.originalResults = html;
            this.resultsTarget.querySelectorAll('.chat-contact').forEach((link) => {
                if (new URL(link.href, window.location.origin).pathname === window.location.pathname) {
                    link.classList.add('is-selected');
                    link.setAttribute('aria-current', 'page');
                }
            });
            const count = this.resultsTarget.querySelectorAll('.chat-contact').length;
            this.statusTarget.textContent = count ? `${count} matching accounts.` : 'No matching accounts found.';
        } catch (error) {
            if (error.name === 'AbortError' || version !== this.requestVersion) return;
            this.resultsTarget.replaceChildren();
            const notice = document.createElement('p');
            notice.className = 'chat-list-empty';
            notice.textContent = 'Search is unavailable. Please try again.';
            this.resultsTarget.append(notice);
            this.statusTarget.textContent = notice.textContent;
        } finally {
            clearTimeout(timeout);
            if (version === this.requestVersion) this.resultsTarget.removeAttribute('aria-busy');
        }
    }
}
