import { Controller } from '@hotwired/stimulus';

// Client-side numbered pagination for a table whose rows are marked with the "row" target.
export default class extends Controller {
    static targets = ['row', 'nav'];
    static values = { perPage: { type: Number, default: 5 } };

    connect() {
        this.page = 1;
        this.render();
    }

    go(event) {
        this.page = Number(event.currentTarget.dataset.page);
        this.render();
    }

    render() {
        const pages = Math.max(1, Math.ceil(this.rowTargets.length / this.perPageValue));
        this.page = Math.min(this.page, pages);
        const from = (this.page - 1) * this.perPageValue;
        this.rowTargets.forEach((row, index) => {
            const show = index >= from && index < from + this.perPageValue;
            row.hidden = !show;
            if (show) {
                row.style.animationDelay = `${(index - from) * 40}ms`;
                row.classList.remove('is-entering');
                void row.offsetWidth;
                row.classList.add('is-entering');
            }
        });
        this.navTarget.replaceChildren();
        this.navTarget.hidden = pages === 1;
        for (let i = 1; i <= pages; i++) {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = i;
            button.dataset.page = i;
            button.dataset.action = 'paged-table#go';
            button.setAttribute('aria-label', `Page ${i}`);
            if (i === this.page) {
                button.classList.add('is-active');
                button.setAttribute('aria-current', 'page');
            }
            this.navTarget.append(button);
        }
    }
}
