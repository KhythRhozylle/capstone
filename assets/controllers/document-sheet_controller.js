/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    stateFor(row) {
        this.rows ??= new Map();
        if (!this.rows.has(row)) this.rows.set(row, {revision: 0, dirty: false, saving: false});
        return this.rows.get(row);
    }

    disconnect() {
        this.rows?.forEach((state) => clearTimeout(state.timer));
    }

    dirty(event) {
        const row = event.target.closest('[data-document-sheet-row]');
        if (!row || event.target.readOnly) return;
        const state = this.stateFor(row);
        state.revision++;
        state.dirty = true;
        row.classList.add('is-dirty');
        this.message(row, state.saving ? 'Saving latest changes...' : 'Waiting to save...');
        this.schedule(row, event.type === 'input' ? 800 : 100);
    }

    schedule(row, delay = 800) {
        const state = this.stateFor(row);
        clearTimeout(state.timer);
        state.timer = setTimeout(() => this.flush(row), delay);
    }

    flush(row) {
        const state = this.stateFor(row);
        if (!state.dirty || state.saving || state.uncertain) return;
        const form = row.querySelector('form');
        if (!form.checkValidity()) {
            this.message(row, 'Complete the required fields to save automatically.');
            return;
        }
        return this.save({target: form, preventDefault() {}});
    }

    keyboard(event) {
        if (event.key !== 'Enter' || ['SELECT', 'BUTTON'].includes(event.target.tagName)) return;
        if (event.target.tagName === 'TEXTAREA' && !event.ctrlKey && !event.metaKey) return;
        const row = event.target.closest('[data-document-sheet-row]');
        if (!row) return;
        event.preventDefault();
        this.flush(row);
    }

    async save(event) {
        event.preventDefault();
        const form = event.target;
        const row = form.closest('[data-document-sheet-row]');
        const state = this.stateFor(row);
        if (state.saving || state.uncertain) return;
        clearTimeout(state.timer);
        const revision = state.revision;
        const wasNew = row.dataset.new === 'true';
        const body = new FormData(form);
        state.saving = true;
        this.busy(row, true);
        const request = new AbortController();
        const timeout = setTimeout(() => request.abort(), 15000);
        try {
            const response = await fetch(form.action, {method: 'POST', credentials: 'same-origin', headers: {Accept: 'application/json'}, body, signal: request.signal});
            const data = await response.json();
            if (!response.ok && response.status !== 422) throw new Error('Unable to save. Your entries are still here.');
            const changed = state.revision !== revision;
            const values = changed ? this.values(form) : null;
            const focus = this.focusedField(row);
            const replacement = this.rowFrom(data.html);
            if (values) this.restoreValues(replacement.querySelector('form'), values);
            clearTimeout(state.timer);
            this.element?.querySelectorAll('thead [data-column-id]').forEach((header) => {
                if (replacement.querySelector('[data-column-id="' + header.dataset.columnId + '"]')) return;
                const current = row.querySelector('[data-column-id="' + header.dataset.columnId + '"]');
                if (current) {
                    const targetForm = replacement.querySelector('form');
                    current.querySelectorAll('input, textarea, select').forEach((field) => {
                        const key = this.fieldKey(field);
                        field.name = targetForm.name + '[' + key + ']';
                        field.id = targetForm.id + '_' + key;
                        field.setAttribute('form', targetForm.id);
                    });
                    replacement.insertBefore(current, replacement.lastElementChild);
                }
            });
            row.replaceWith(replacement);
            this.rows.delete(row);
            const next = this.stateFor(replacement);
            next.dirty = changed || response.status === 422;
            next.revision = state.revision;
            replacement.classList.toggle('is-dirty', next.dirty);
            this.restoreFocus(replacement, focus);
            if (response.ok && wasNew && data.newRow && !replacement.parentElement.querySelector?.('[data-new="true"]')) replacement.parentElement.append(this.rowFrom(data.newRow));
            this.message(replacement, response.status === 422 ? 'Please correct the highlighted fields.' : (changed ? 'Saving latest changes...' : 'Saved automatically'));
            if (changed) this.schedule(replacement, 100);
        } catch (error) {
            state.dirty = true;
            state.uncertain = wasNew;
            this.message(row, wasNew ? 'Save could not be confirmed. Reload to check before entering this record again.' : 'Not saved. Your entries are still here; editing the row will retry.');
        } finally {
            clearTimeout(timeout);
            state.saving = false;
            this.busy(row, false);
        }
    }

    values(form) {
        return Object.fromEntries(Array.from(form.elements).filter((field) => field.name && !field.readOnly && field.type !== 'hidden').map((field) => [this.fieldKey(field), field.value]));
    }

    fieldKey(field) {
        return field.name?.match(/\[([^\]]+)\]$/)?.[1];
    }

    restoreValues(form, values) {
        Array.from(form.elements).forEach((field) => {
            const key = this.fieldKey(field);
            if (Object.hasOwn(values, key)) field.value = values[key];
        });
    }

    focusedField(row) {
        const field = document.activeElement;
        if (!row.contains(field)) return null;
        return {key: this.fieldKey(field), start: field.selectionStart, end: field.selectionEnd};
    }

    restoreFocus(row, focus) {
        if (!focus) return;
        const field = Array.from(row.querySelector('form').elements).find((item) => this.fieldKey(item) === focus.key);
        field?.focus({preventScroll: true});
        if (focus.start !== null && focus.start !== undefined && field?.setSelectionRange) field.setSelectionRange(focus.start, focus.end);
    }

    toolbarMessage(text) {
        this.element.querySelector('[data-sheet-message]').textContent = text;
    }

    async addRow(event) {
        const button = event.currentTarget;
        const menu = button.closest('details');
        const count = Number(menu.querySelector?.('[data-row-count]')?.value || 1);
        menu.open = false;
        if (button.disabled) return;
        button.disabled = true;
        this.toolbarMessage('Adding rows...');
        try {
            const response = await fetch(button.dataset.url + '?count=' + count, {credentials: 'same-origin', headers: {Accept: 'application/json'}, signal: AbortSignal.timeout(15000)});
            if (!response.ok) throw new Error('Unable to add a row. Please try again.');
            const data = await response.json();
            const rows = (data.rows || [data.html]).map((html) => this.rowFrom(html));
            const body = this.element.querySelector('tbody');
            rows.forEach((row) => body.append(row));
            rows[0]?.querySelector('textarea')?.focus();
            rows[0]?.scrollIntoView({behavior: 'smooth', block: 'nearest'});
            this.toolbarMessage(rows.length + ' row' + (rows.length === 1 ? '' : 's') + ' added. Enter details to save automatically.');
        } catch (error) {
            this.toolbarMessage(error.message);
        } finally {
            button.disabled = false;
        }
    }

    showColumn(event) {
        event.currentTarget.closest('details').open = false;
        const editor = this.element.querySelector('[data-column-editor]');
        editor.hidden = false;
        editor.querySelector('input:not([type="hidden"])').focus();
    }

    hideColumn() {
        this.element.querySelector('[data-column-editor]').hidden = true;
    }

    async addColumn(event) {
        event.preventDefault();
        const form = event.target;
        const button = form.querySelector('button[type="submit"]');
        if (button.disabled) return;
        const body = new FormData(form);
        button.disabled = true;
        this.toolbarMessage('Adding column...');
        try {
            const response = await fetch(form.action, {method: 'POST', credentials: 'same-origin', headers: {Accept: 'application/json'}, body, signal: AbortSignal.timeout(15000)});
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Unable to add the column. Please try again.');
            const columns = data.columns || [data];
            columns.forEach((column) => this.insertColumn(column));
            form.reset();
            this.hideColumn();
            this.toolbarMessage(columns.length + ' column' + (columns.length === 1 ? '' : 's') + ' added. Values save automatically.');
        } catch (error) {
            this.toolbarMessage(error.message);
        } finally {
            button.disabled = false;
        }
    }

    insertColumn(column) {
        const header = this.element.querySelector('thead tr');
        if (header.querySelector('[data-column-id="' + column.id + '"]')) return;
        const heading = document.createElement('th');
        heading.scope = 'col';
        heading.dataset.columnId = column.id;
        heading.textContent = column.label;
        header.insertBefore(heading, header.lastElementChild);
        this.element.querySelectorAll('[data-document-sheet-row]').forEach((row) => {
            if (row.querySelector('[data-column-id="' + column.id + '"]')) return;
            const cell = document.createElement('td');
            cell.dataset.columnId = column.id;
            if (String(column.unitId) === row.dataset.unitId) {
                const form = row.querySelector('form');
                const input = document.createElement('input');
                input.type = 'text';
                input.name = form.name + '[column_' + column.id + ']';
                input.id = form.id + '_column_' + column.id;
                input.setAttribute('form', form.id);
                input.setAttribute('aria-label', column.label);
                input.maxLength = 2000;
                cell.append(input);
            } else {
                cell.textContent = '—';
            }
            row.insertBefore(cell, row.lastElementChild);
        });
    }

    async remove(event) {
        const button = event.currentTarget;
        const row = button.closest('[data-document-sheet-row]');
        const state = this.stateFor(row);
        if (state.saving || !window.confirm('Delete this office document record?')) return;
        clearTimeout(state.timer);
        state.saving = true;
        this.busy(row, true);
        this.message(row, 'Deleting...');
        const request = new AbortController();
        const timeout = setTimeout(() => request.abort(), 15000);
        try {
            const body = new FormData();
            body.set('_token', button.dataset.token);
            const response = await fetch(button.dataset.url, {method: 'POST', credentials: 'same-origin', headers: {Accept: 'application/json'}, body, signal: request.signal});
            if (!response.ok) throw new Error('Unable to delete this record. Please try again.');
            row.remove();
            this.rows.delete(row);
        } catch (error) {
            this.message(row, error.name === 'AbortError' ? 'Request timed out. Refresh to check whether it was deleted.' : error.message);
        } finally {
            clearTimeout(timeout);
            state.saving = false;
            this.busy(row, false);
        }
    }

    rowFrom(html) {
        const table = document.createElement('table');
        table.innerHTML = '<tbody>' + html + '</tbody>';
        return table.querySelector('tr');
    }

    message(row, text) {
        row.querySelector('[data-row-message]').textContent = text;
    }

    busy(row, active) {
        row.dataset.busy = String(active);
        row.setAttribute('aria-busy', String(active));
        row.querySelectorAll('button').forEach((button) => { button.disabled = active; });
        if (active) this.message(row, 'Saving...');
    }
}
