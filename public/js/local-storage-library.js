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
    document.addEventListener('portal-library-refreshed', applyView);
    applyView();
})();
