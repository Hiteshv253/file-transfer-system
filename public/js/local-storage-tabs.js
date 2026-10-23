(() => {
    'use strict';
    const list = document.querySelector('[data-portal-tabs]');
    if (!list) return;
    const tabs = Array.from(list.querySelectorAll('[role="tab"]'));
    function select(name, historyMode = null, focus = false) {
        const selected = tabs.find(tab => tab.dataset.tab === name) || tabs[0];
        if (!selected) return;
        for (const tab of tabs) {
            const active = tab === selected;
            tab.setAttribute('aria-selected', String(active));
            tab.tabIndex = active ? 0 : -1;
        }
        document.querySelectorAll('[data-portal-panel]').forEach(panel => { panel.hidden = panel.dataset.portalPanel !== selected.dataset.tab; });
        document.getElementById('portal-preview-outlet')?.remove();
        document.querySelector('.portal-heading')?.removeAttribute('hidden');
        document.getElementById('library-stats')?.removeAttribute('hidden');
        if (historyMode) {
            const url = new URL(window.location.href);
            url.pathname = '/files';
            url.searchParams.set('tab', selected.dataset.tab);
            url.hash = '';
            window.history[historyMode](null, '', url);
        }
        if (focus) { selected.focus(); selected.scrollIntoView({block: 'nearest', inline: 'nearest'}); }
        document.dispatchEvent(new CustomEvent('portal-tab-changed', {detail: {tab: selected.dataset.tab, refresh: !!historyMode}}));
    }
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', event => {
            if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            event.preventDefault(); select(tab.dataset.tab, 'pushState');
        });
        tab.addEventListener('keydown', event => {
            let next;
            if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
            else if (event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
            else if (event.key === 'Home') next = 0;
            else if (event.key === 'End') next = tabs.length - 1;
            else return;
            event.preventDefault(); select(tabs[next].dataset.tab, 'pushState', true);
        });
    });
    window.addEventListener('popstate', () => select(new URL(window.location.href).searchParams.get('tab') || 'files'));
    document.addEventListener('portal-navigation-complete', event => select(event.detail.tab || 'files'));
    select(new URL(window.location.href).searchParams.get('tab') || list.dataset.activeTab || 'files');
})();
