import { Controller } from '@hotwired/stimulus';

// Jump-to-page search over the workspace navigation. Press "/" to focus it.
export default class extends Controller {
    static targets = ['input', 'results'];

    connect() {
        this.active = -1;
    }

    pages() {
        return [...document.querySelectorAll('.sidebar .side-nav-link')].map((link) => ({
            label: link.textContent.trim().replace(/\s+/g, ' '),
            href: link.getAttribute('href'),
            group: link.closest('.side-nav-group')?.querySelector('.side-nav-heading')?.textContent.trim() ?? '',
        }));
    }

    filter() {
        const query = this.inputTarget.value.trim().toLowerCase();
        const matches = this.pages().filter((page) => !query || `${page.label} ${page.group}`.toLowerCase().includes(query));
        this.resultsTarget.replaceChildren();
        this.active = matches.length && query ? 0 : -1;
        matches.forEach((page, index) => {
            const item = document.createElement('li');
            item.setAttribute('role', 'option');
            const link = document.createElement('a');
            link.href = page.href;
            link.tabIndex = -1;
            const name = document.createElement('span');
            name.textContent = page.label;
            const group = document.createElement('small');
            group.textContent = page.group;
            link.append(name, group);
            item.append(link);
            if (index === this.active) {
                item.classList.add('is-active');
            }
            this.resultsTarget.append(item);
        });
        if (!matches.length) {
            const empty = document.createElement('li');
            empty.className = 'quick-empty';
            empty.textContent = 'No matching pages';
            this.resultsTarget.append(empty);
        }
        this.resultsTarget.hidden = false;
    }

    keydown(event) {
        const items = [...this.resultsTarget.querySelectorAll('li:not(.quick-empty)')];
        if (event.key === 'Escape') {
            this.close();
            this.inputTarget.blur();
        } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (!items.length) {
                return;
            }
            this.active = (this.active + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
            items.forEach((item, index) => item.classList.toggle('is-active', index === this.active));
            items[this.active].scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'Enter' && items[Math.max(this.active, 0)]) {
            event.preventDefault();
            items[Math.max(this.active, 0)].querySelector('a').click();
        }
    }

    shortcut(event) {
        const typing = /^(input|textarea|select)$/i.test(event.target.tagName) || event.target.isContentEditable;
        if (event.key === '/' && !typing && !event.metaKey && !event.ctrlKey) {
            event.preventDefault();
            this.inputTarget.focus();
        }
    }

    outside(event) {
        if (!this.element.contains(event.target)) {
            this.close();
        }
    }

    close() {
        this.resultsTarget.hidden = true;
    }
}
