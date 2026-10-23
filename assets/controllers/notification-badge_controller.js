import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['badge', 'link'];
    static values = { url: String };

    connect() {
        // The initial count is already rendered on the server. Refresh cached previews only.
        this.lastRefresh = Date.now();
        if (document.documentElement.hasAttribute('data-turbo-preview')) this.lastRefresh = 0;
        this.timer = setInterval(() => this.refresh(), 15000);
    }

    disconnect() {
        clearInterval(this.timer);
        this.request?.abort();
    }

    async refresh() {
        if (document.hidden || this.loading || Date.now() - (this.lastRefresh ?? 0) < 15000) return;
        this.lastRefresh = Date.now();
        this.loading = true;
        this.request = new AbortController();
        const timeout = setTimeout(() => this.request.abort(), 10000);
        try {
            const response = await fetch(this.urlValue, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin', cache: 'no-store', signal: this.request.signal,
            });
            if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) return;
            const data = await response.json();
            if (Number.isSafeInteger(data.unreadCount) && data.unreadCount >= 0) this.update(data.unreadCount);
        } catch {
            // Keep the last database count when the connection is unavailable.
        } finally {
            clearTimeout(timeout);
            this.loading = false;
        }
    }

    update(count) {
        this.badgeTargets.forEach((badge) => {
            badge.textContent = count > 0 ? String(count) : '';
            badge.hidden = count === 0;
        });
        this.linkTargets.forEach((link) => {
            link.setAttribute('aria-label', count > 0 ? `Notifications, ${count} unread` : 'Notifications');
        });
    }
}
