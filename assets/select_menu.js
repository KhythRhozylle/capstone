// Replaces the browser's own option list on every <select> with a styled floating menu.
// The select stays in the page (it keeps its value, validation, styles and change events); only the popup is custom.
const CHECK = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';
let open = null;

function close(restoreFocus = false) {
    if (!open) return;
    const { menu, select, cleanup } = open;
    open = null;
    cleanup();
    menu.remove();
    select.setAttribute('aria-expanded', 'false');
    if (restoreFocus) select.focus();
}

function place(menu, select) {
    const rect = select.getBoundingClientRect();
    menu.style.minWidth = `${rect.width}px`;
    menu.style.left = `${Math.max(8, Math.min(rect.left, window.innerWidth - menu.offsetWidth - 8))}px`;
    const below = window.innerHeight - rect.bottom - 12;
    const above = rect.top - 12;
    const up = below < Math.min(menu.scrollHeight, 220) && above > below;
    menu.style.maxHeight = `${Math.min(320, up ? above : below)}px`;
    menu.style.top = up ? `${rect.top - menu.offsetHeight - 6}px` : `${rect.bottom + 6}px`;
}

function openMenu(select) {
    if (open?.select === select) return close(true);
    close();
    const menu = document.createElement('div');
    menu.className = 'select-menu';
    menu.setAttribute('role', 'listbox');
    const items = [];
    const add = (option, parent) => {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'select-menu-item';
        item.setAttribute('role', 'option');
        item.disabled = option.disabled || parent?.disabled === true;
        item.dataset.index = String(option.index);
        const label = document.createElement('span');
        label.textContent = option.textContent;
        item.append(label);
        const selected = option.index === select.selectedIndex;
        item.setAttribute('aria-selected', String(selected));
        if (selected) item.insertAdjacentHTML('beforeend', `<span class="select-menu-check">${CHECK}</span>`);
        menu.append(item);
        items.push(item);
    };
    [...select.children].forEach((child) => {
        if (child.tagName === 'OPTGROUP') {
            const heading = document.createElement('div');
            heading.className = 'select-menu-group';
            heading.textContent = child.label;
            menu.append(heading);
            [...child.children].forEach((option) => add(option, child));
        } else {
            add(child);
        }
    });
    if (!items.length) return;
    document.body.append(menu);
    place(menu, select);
    select.setAttribute('aria-expanded', 'true');

    const choose = (item) => {
        if (item.disabled) return;
        const changed = select.selectedIndex !== Number(item.dataset.index);
        select.selectedIndex = Number(item.dataset.index);
        close(true);
        if (changed) {
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
    };
    const focusItem = (step) => {
        const enabled = items.filter((item) => !item.disabled);
        const at = enabled.indexOf(document.activeElement);
        enabled[(at + step + enabled.length) % enabled.length]?.focus();
    };
    const onKey = (event) => {
        if (event.key === 'Escape') { event.preventDefault(); close(true); }
        else if (event.key === 'ArrowDown') { event.preventDefault(); focusItem(1); }
        else if (event.key === 'ArrowUp') { event.preventDefault(); focusItem(-1); }
        else if (event.key === 'Tab') close();
    };
    const onOutside = (event) => { if (!menu.contains(event.target) && event.target !== select) close(); };
    const onMove = () => close();
    menu.addEventListener('click', (event) => {
        const item = event.target.closest('.select-menu-item');
        if (item) choose(item);
    });
    menu.addEventListener('keydown', onKey);
    document.addEventListener('mousedown', onOutside, true);
    window.addEventListener('resize', onMove);
    window.addEventListener('scroll', onMove, true);
    document.addEventListener('turbo:before-cache', onMove);
    open = {
        menu,
        select,
        cleanup: () => {
            document.removeEventListener('mousedown', onOutside, true);
            window.removeEventListener('resize', onMove);
            window.removeEventListener('scroll', onMove, true);
            document.removeEventListener('turbo:before-cache', onMove);
        },
    };
    (items.find((item) => item.getAttribute('aria-selected') === 'true' && !item.disabled) ?? items.find((item) => !item.disabled))?.focus();
}

const eligible = (select) => select instanceof HTMLSelectElement && !select.multiple && select.size <= 1 && !select.disabled && !('nativeMenu' in select.dataset);

document.addEventListener('mousedown', (event) => {
    const select = event.target.closest?.('select');
    if (!select || event.button !== 0 || !eligible(select)) return;
    event.preventDefault();
    select.focus();
    openMenu(select);
});

document.addEventListener('keydown', (event) => {
    const select = event.target;
    if (!eligible(select) || open) return;
    if (event.key === 'Enter' || event.key === ' ' || (event.altKey && (event.key === 'ArrowDown' || event.key === 'ArrowUp'))) {
        event.preventDefault();
        openMenu(select);
    }
});
