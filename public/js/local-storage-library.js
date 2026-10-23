(() => {
    'use strict';
    let mode = 'grid';
    try { mode = localStorage.getItem('file-vault-view') === 'list' ? 'list' : 'grid'; } catch {}
    function applyView() {
        const library = document.getElementById('file-library');
        if (!library) return;
        library.dataset.view = mode;
        library.querySelectorAll('[data-library-view]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.libraryView === mode)));
    }
    function selection(group) {
        const items = [...document.querySelectorAll(`[data-select-item="${group}"]`)];
        const count = new Set(items.filter(item => item.checked).map(item => item.value)).size;
        const total = new Set(items.map(item => item.value)).size;
        document.querySelectorAll(`[data-selection-count="${group}"]`).forEach(label => { label.textContent = count; });
        document.querySelectorAll(`[data-selection-action="${group}"]`).forEach(button => { button.disabled = count === 0; });
        document.querySelectorAll(`[data-select-all="${group}"]`).forEach(input => { input.checked = total > 0 && count === total; input.indeterminate = count > 0 && count < total; });
    }
    document.addEventListener('change', event => {
        const group = event.target.dataset.selectAll || event.target.dataset.selectItem;
        if (!group) return;
        document.querySelectorAll(`[data-select-item="${group}"]`).forEach(item => {
            if (event.target.dataset.selectAll || item.value === event.target.value) item.checked = event.target.checked;
        });
        selection(group);
    });
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-library-view]');
        if (!button) return;
        mode = button.dataset.libraryView;
        try { localStorage.setItem('file-vault-view', mode); } catch {}
        applyView();
    });
    document.addEventListener('submit', event => {
        const message = event.target.dataset.confirm;
        if (message && !window.confirm(message)) event.preventDefault();
    });
    document.addEventListener('portal-library-refreshed', () => { applyView(); selection('files'); selection('requests'); });
    applyView();
})();
