(() => {
    'use strict';
    if (!document.querySelector('.drive-workspace')) return;
    const t = key => window.portalTranslations?.[key] || key;
    let sequence = 0;
    const portalUrl = url => url.origin === location.origin && (url.pathname === '/files' || /^\/files\/\d+\/preview$/.test(url.pathname));
    function notices(page) {
        let box = document.getElementById('portal-navigation-notices');
        if (!box) {
            box = document.createElement('div');
            box.id = 'portal-navigation-notices';
            document.querySelector('main.shell').prepend(box);
        }
        document.querySelectorAll('main.shell > .notice').forEach(node => node.remove());
        box.replaceChildren(...page.querySelectorAll('main.shell > .notice'));
        return box;
    }
    async function navigate(url, {method = 'GET', body, history = true} = {}) {
        const request = ++sequence;
        const main = document.querySelector('.drive-main');
        main.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, {method, body, headers: {Accept:'text/html'}, cache:'no-store'});
            if (request !== sequence) return;
            if (!response.ok) throw Error(t('Could not load this page. Please try again.') + ` (${response.status})`);
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            if (request !== sequence) return;
            if (page.querySelector('.login')) throw Error(t('Your session expired. Finish or retry uploads after signing in again.'));
            if (page.querySelector('.drive-workspace')) {
                for (const id of ['file-library', 'daily-upload-register', 'library-stats', 'daily-folders', 'panel-requests', 'panel-users']) {
                    const old = document.getElementById(id), fresh = page.getElementById(id);
                    if (old && fresh) old.replaceWith(fresh);
                }
                const tab = page.querySelector('[data-portal-tabs]')?.dataset.activeTab || 'files';
                const uploader = document.getElementById('upload-form'), freshUploader = page.getElementById('upload-form');
                if (uploader && freshUploader) {
                    uploader.dataset.folderId = freshUploader.dataset.folderId;
                    document.getElementById('upload-destination').textContent = page.getElementById('upload-destination').textContent;
                }
                document.dispatchEvent(new CustomEvent('portal-navigation-complete', {detail:{tab}}));
                document.dispatchEvent(new Event('portal-library-refreshed'));
            } else if (page.querySelector('.preview-stage')) {
                document.querySelectorAll('[data-portal-panel], .portal-heading, #library-stats').forEach(node => { node.hidden = true; });
                document.getElementById('portal-preview-outlet')?.remove();
                const outlet = document.createElement('section');
                outlet.id = 'portal-preview-outlet';
                outlet.append(...page.querySelector('main.shell').children);
                outlet.querySelectorAll('script').forEach(node => node.remove());
                main.append(outlet);
            } else throw Error(t('Could not load this page. Please try again.'));
            notices(page);
            if (history) window.history.pushState(null, '', response.url || url);
            const hash = new URL(response.url || url, location.href).hash;
            if (hash) document.getElementById(hash.slice(1))?.scrollIntoView({block:'start'});
        } catch (error) {
            if (request !== sequence) return;
            const empty = new DOMParser().parseFromString('<html><body></body></html>', 'text/html');
            const box = notices(empty);
            const notice = document.createElement('div');
            notice.className = 'notice error';notice.setAttribute('role','alert');notice.textContent = error.message;
            box.append(notice);
        } finally { if (request === sequence) main.removeAttribute('aria-busy'); }
    }
    document.addEventListener('click', event => {
        if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const link = event.target.closest('a[href]');
        if (!link || link.target || link.hasAttribute('download')) return;
        const url = new URL(link.href, location.href);
        if (!portalUrl(url)) return;
        event.preventDefault();navigate(url);
    });
    document.addEventListener('submit', event => {
        if (event.defaultPrevented || event.target.id === 'upload-form') return;
        const form = event.target;
        const url = new URL(form.action, location.href);
        if (url.origin !== location.origin || !url.pathname.startsWith('/files') || /\/(login|logout|language)$/.test(url.pathname)) return;
        event.preventDefault();
        const body = new FormData(form);
        if (event.submitter?.name) body.append(event.submitter.name, event.submitter.value);
        const method = form.method.toUpperCase();
        if (method === 'GET') {
            url.search = new URLSearchParams(body).toString();
            navigate(url);
        } else {
            if (form.dataset.sending) return;
            form.dataset.sending = 'true';
            navigate(url, {method, body}).finally(() => { delete form.dataset.sending; });
        }
    });
    window.addEventListener('popstate', () => { if (portalUrl(new URL(location.href))) navigate(location.href, {history:false}); });
    document.addEventListener('portal-tab-changed', event => {
        if (event.detail.refresh && event.detail.tab !== 'upload') navigate(location.href, {history:false});
    });
})();
