// Turbo disables the clicked submitter. Also guard Enter and other submit buttons
// on the same form until that submission finishes, without changing submitted values.
const pending = new Map();

document.addEventListener('submit', (event) => {
    if (pending.has(event.target)) event.preventDefault();
}, true);

document.addEventListener('turbo:submit-start', (event) => {
    const form = event.target;
    pending.set(form, form.getAttribute('aria-busy'));
    form.setAttribute('aria-busy', 'true');
});

function release(form) {
    if (!pending.has(form)) return;
    const previous = pending.get(form);
    if (previous === null) form.removeAttribute('aria-busy');
    else form.setAttribute('aria-busy', previous);
    pending.delete(form);
}

document.addEventListener('turbo:submit-end', (event) => release(event.target));
document.addEventListener('turbo:before-cache', () => [...pending.keys()].forEach(release));
window.addEventListener('pageshow', () => [...pending.keys()].forEach(release));
