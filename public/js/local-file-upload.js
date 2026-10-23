(() => {
    'use strict';
    const translations = window.portalTranslations || {};
    const t = (key, values = {}) => {
        let message = translations[key] || key;
        return message.replace(/:([a-z]+)/g, (match, name) => Object.hasOwn(values, name) ? String(values[name]) : match);
    };
    const form = document.getElementById('upload-form');
    if (!form) return;
    const input = document.getElementById('file');
    const folderInput = document.getElementById('folder-input');
    const relativePaths = new WeakMap();
    const relativePath = file => relativePaths.get(file) || file.webkitRelativePath || '';
    const dropzone = document.getElementById('file-dropzone');
    const queue = document.getElementById('upload-queue');
    const submit = document.getElementById('upload-submit');
    const clear = document.getElementById('upload-clear');
    const feedback = document.getElementById('upload-feedback');
    const share = document.getElementById('share_with');
    const total = document.getElementById('upload-total-progress');
    const summary = document.getElementById('upload-summary');
    const viewFiles = document.getElementById('upload-view-files');
    const report = document.getElementById('upload-report');
    const storageKey = `file-vault-upload-batch-${form.dataset.userId}`;
    let files = [];
    let directories = [];
    let busy = false;
    let recoveryAvailable = true;
    const maxBytes = 100 * 1024 * 1024;
    function uploadKey() {
        if (typeof crypto.randomUUID === 'function') return crypto.randomUUID();
        // getRandomValues also works on HTTP IP deployments, unlike randomUUID.
        const bytes = crypto.getRandomValues(new Uint8Array(16));
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;
        const hex = Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
        return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
    }
    const size = bytes => bytes < 1048576 ? `${(bytes / 1024).toFixed(1)} KB` : `${(bytes / 1048576).toFixed(1)} MB`;

    function saveBatch() {
        try {
            window.localStorage.setItem(storageKey, JSON.stringify(files.map(item => ({
                name: item.file.name, size: item.file.size, lastModified: item.file.lastModified,
                key: item.key, state: item.state, documentId: item.documentId,
                recipients: item.recipients, folderDate: item.folderDate,
                relativePath: item.relativePath, parentFolderId: item.parentFolderId,
            }))));
            window.localStorage.setItem(storageKey+'-folders', JSON.stringify(directories));
        } catch { recoveryAvailable = false; }
    }

    function update() {
        let loaded = 0;
        let bytes = 0;
        let completed = 0;
        for (const item of files) {
            item.row.dataset.state = item.state;
            item.status.textContent = `${size(item.file.size)} · ${item.message}`;
            item.progress.value = item.state === 'done' ? 100 : item.percent;
            item.remove.disabled = busy;
            item.remove.hidden = item.state === 'done';
            if (item.state !== 'invalid') {
                bytes += Math.max(1, item.file.size);
                loaded += Math.max(1, item.file.size) * (item.state === 'done' ? 1 : item.percent / 100);
            }
            if (item.state === 'done') completed++;
        }
        const percent = bytes ? Math.floor(loaded / bytes * 100) : 0;
        total.value = percent;
        document.getElementById('upload-percentage').textContent = `${percent}%`;
        const failed = files.filter(i => i.state === 'failed').length;
        const invalid = files.filter(i => i.state === 'invalid').length;
        summary.textContent = `${files.length} ${t('selected')} · ${completed} ${t('verified')} · ${failed} ${t('failed')} · ${invalid} ${t('rejected')} · ${files.length - completed - failed - invalid} ${t('waiting / uploading')}`;
        document.getElementById('upload-overall').hidden = !files.length;
        clear.hidden = !files.length;
        clear.disabled = busy;
        share.disabled = busy;
        input.disabled = busy;
        if (folderInput) folderInput.disabled = busy;
        dropzone.setAttribute('aria-disabled', String(busy));
        submit.disabled = busy || (!directories.some(directory => !directory.done) && !files.some(i => !i.needsSelection && (i.state === 'queued' || i.state === 'failed')));
        submit.textContent = busy ? t('Uploading…') : files.some(i => i.state === 'failed') ? t('Upload remaining / retry failed') : t('Upload files');
        viewFiles.hidden = !completed && !directories.some(directory => directory.done);
        viewFiles.setAttribute('aria-disabled', String(busy));
        report.hidden = !files.length;
        document.getElementById('upload-list-details').hidden = !files.length;
        document.getElementById('upload-list-title').textContent = `${files.length} ${t('files')} · ${completed} ${t('verified')}${failed ? ` · ${failed} ${t('failed')}` : ''}`;
        document.getElementById('upload-folder-summary').textContent = directories.length ? t(':count folders selected. Folder names and structure will be preserved.', {count: directories.length}) : '';
        if (directories.length) clear.hidden = false;
        const tray = document.getElementById('background-upload');
        if (tray) {
            tray.hidden = !busy;
            document.getElementById('background-upload-summary').textContent = summary.textContent;
            document.getElementById('background-upload-progress').value = percent;
        }
    }

    function addFiles(selected, persist = true, folderPaths = []) {
        if (busy) return;
        selected = Array.from(selected);
        if (persist && (selected.length || folderPaths.length) && (files.length || directories.length) && files.every(item => item.state === 'done' || item.state === 'invalid') && directories.every(directory => directory.done)) {
            files = [];
            directories = [];
            queue.replaceChildren();
            feedback.textContent = '';
        }
        const matches = (item, file) => item.file === file || (item.needsSelection && item.file.name === file.name && item.file.size === file.size && item.file.lastModified === file.lastModified && (item.relativePath || '') === relativePath(file));
        const newFiles = selected.filter(file => !files.some(i => matches(i, file)));
        const restoredParents = new Set(selected.map(file => files.find(item => item.needsSelection && matches(item, file))?.parentFolderId).filter(value => value !== undefined));
        const parentId = !newFiles.length && restoredParents.size === 1 ? [...restoredParents][0] : Number(form.dataset.folderId) || 0;
        const additions = [...folderPaths];
        if (persist) selected.forEach(file => {
            if (files.some(item => matches(item, file))) return;
            const parts = relativePath(file).split('/');
            for (let index = 1; index < parts.length; index++) additions.push(parts.slice(0, index).join('/'));
        });
        const combined = new Set(directories.map(directory => `${directory.parentId}:${directory.path}`));
        additions.forEach(path => combined.add(`${parentId}:${path}`));
        if (combined.size > 1000) { feedback.textContent = t('Select up to 1,000 folders per batch.'); return; }
        if (files.length + newFiles.length > 1000) {
            feedback.textContent = t('This selection exceeds 1,000 files per batch. No new files were added. Select a smaller batch.');
            input.value = '';
            return;
        }
        additions.forEach(path => {
            if (!directories.some(directory => directory.parentId === parentId && directory.path === path)) directories.push({path, parentId, done:false});
        });
        let duplicates = 0;
        for (const file of selected) {
            // Matching names/sizes alone do not prove identical content. Separate
            // selected files get separate receipts, even if all metadata matches.
            const existing = files.find(i => matches(i, file));
            if (existing) {
                if (existing.needsSelection) {
                    existing.file = file; existing.needsSelection = false;
                    existing.state = file.size > maxBytes ? 'invalid' : 'queued';
                    existing.message = file.size > maxBytes ? t('Too large. Maximum 100 MB per file.') : t('Ready to resume');
                } else duplicates++;
                continue;
            }
            const invalid = file.size > maxBytes;
            const item = {file, key: uploadKey(), relativePath:relativePath(file), parentFolderId:parentId, state: invalid ? 'invalid' : 'queued', percent: 0, message: invalid ? t('Too large. Maximum 100 MB per file.') : t('Ready to upload')};
            item.row = document.createElement('div');
            item.row.className = 'upload-item';
            const top = document.createElement('div');
            top.className = 'upload-item-top';
            const name = document.createElement('div');
            name.className = 'upload-item-name';
            const title = document.createElement('strong');
            title.textContent = item.relativePath || file.name;
            item.status = document.createElement('small');
            name.append(title, item.status);
            item.remove = document.createElement('button');
            item.remove.type = 'button';
            item.remove.textContent = t('Remove');
            item.remove.setAttribute('aria-label', t('Remove :name', {name: file.name}));
            item.remove.addEventListener('click', () => {
                if (busy) return;
                files = files.filter(i => i !== item);
                item.row.remove();
                saveBatch();
                update();
            });
            item.progress = document.createElement('progress');
            item.progress.max = 100;
            item.progress.value = 0;
            item.progress.setAttribute('aria-label', t('Upload progress for :name', {name: file.name}));
            top.append(name, item.remove);
            item.row.append(top, item.progress);
            queue.append(item.row);
            files.push(item);
        }
        input.value = '';
        feedback.textContent = duplicates ? t(':count duplicate selection(s) skipped.', {count: duplicates}) : t('Files selected. Click Upload files to start.');
        if (persist) saveBatch();
        document.getElementById('upload-list-details').open = true;
        update();
    }

    function upload(item, recipients) {
        return new Promise(resolve => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', form.action);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-CSRF-TOKEN', form.querySelector('[name="_token"]').value);
            xhr.timeout = 30 * 60 * 1000;
            const body = new FormData();
            body.append('file', item.file);
            body.append('upload_key', item.key);
            body.append('relative_path', item.relativePath || '');
            body.append('parent_folder_id', item.parentFolderId || 0);
            item.recipients ??= recipients;
            item.recipients.forEach(id => body.append('share_with[]', id));
            item.state = 'uploading';
            item.percent = 0;
            item.message = t('Uploading 0%');
            item.retryable = false;
            saveBatch();
            update();
            xhr.upload.addEventListener('progress', event => {
                if (!event.lengthComputable) return;
                item.percent = Math.min(100, Math.floor(event.loaded / event.total * 100));
                item.message = item.percent === 100 ? t('Saving on server…') : t('Uploading :percent%', {percent: item.percent});
                update();
            });
            function fail(message, retryable = false) {
                item.state = 'failed';
                item.percent = 0;
                item.message = message;
                item.retryable = retryable;
                saveBatch();
                update();
                resolve();
            }
            xhr.addEventListener('load', () => {
                let data;
                try { data = JSON.parse(xhr.responseText); } catch { data = null; }
                if (xhr.status === 201 && data?.document?.id) {
                    item.state = 'done';
                    item.percent = 100;
                    item.documentId = data.document.id;
                    item.folderDate = data.document.folder_date;
                    item.message = t('Verified on server · File #:id', {id: item.documentId});
                    viewFiles.href = `/files?date=${encodeURIComponent(data.document.folder_date)}${data.document.folder_id ? `&folder=${data.document.folder_id}` : ''}`;
                    saveBatch();
                    update();
                    resolve();
                } else {
                    const errors = data?.errors ? Object.values(data.errors).flat().join(' ') : null;
                    fail(xhr.status === 419 || xhr.status === 401 || xhr.status === 200 ? t('Session expired. Refresh and sign in again.') : errors || data?.message || t('Upload failed (:status). Try again.', {status: xhr.status}), xhr.status >= 500 || xhr.status === 408 || xhr.status === 429);
                }
            });
            xhr.addEventListener('error', () => fail(t('Connection lost. Safe to retry with the same upload reference.'), true));
            xhr.addEventListener('timeout', () => fail(t('Upload timed out. Safe to retry with the same upload reference.'), true));
            xhr.addEventListener('abort', () => fail(t('Upload interrupted.')));
            try { xhr.send(body); } catch { fail(t('Upload could not start. Please retry.'), true); }
        });
    }

    input.addEventListener('change', () => addFiles(input.files));
    folderInput?.addEventListener('change', () => {
        if (!folderInput.files.length) { feedback.textContent = t('No files found. Use New folder to create an empty folder.'); return; }
        addFiles(folderInput.files);folderInput.value = '';
    });
    dropzone.addEventListener('click', () => { if (!busy) input.click(); });
    dropzone.addEventListener('keydown', event => {
        if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); if (!busy) input.click(); }
    });
    let dragDepth = 0;
    dropzone.addEventListener('dragenter', event => { event.preventDefault(); dragDepth++; if (!busy) dropzone.classList.add('drag-over'); });
    dropzone.addEventListener('dragover', event => { event.preventDefault(); event.dataTransfer.dropEffect = busy ? 'none' : 'copy'; });
    dropzone.addEventListener('dragleave', event => { event.preventDefault(); if (--dragDepth <= 0) dropzone.classList.remove('drag-over'); });
    dropzone.addEventListener('drop', async event => {
        event.preventDefault(); dragDepth = 0; dropzone.classList.remove('drag-over');
        if (busy) return;
        const entries = Array.from(event.dataTransfer.items || [], item => item.webkitGetAsEntry?.()).filter(Boolean);
        if (!entries.some(entry => entry.isDirectory)) { addFiles(event.dataTransfer.files); return; }
        const selected = [], paths = [];
        async function visit(entry, parent = '') {
            const path = parent ? `${parent}/${entry.name}` : entry.name;
            if (selected.length > 1000 || paths.length >= 1000) throw Error(t('Select up to 1,000 files and 1,000 folders per batch.'));
            if (entry.isFile) {
                const file = await new Promise((resolve, reject) => entry.file(resolve, reject));
                relativePaths.set(file, path);selected.push(file);
            } else if (entry.isDirectory) {
                paths.push(path);
                const reader = entry.createReader();
                while (true) {
                    const children = await new Promise((resolve, reject) => reader.readEntries(resolve, reject));
                    if (!children.length) break;
                    for (const child of children) await visit(child, path);
                }
            }
        }
        try { for (const entry of entries) await visit(entry); addFiles(selected, true, paths); }
        catch (error) { feedback.textContent = error.message; }
    });
    // Prevent accidentally navigating away when files are dropped outside the box.
    document.addEventListener('dragover', event => { if (Array.from(event.dataTransfer.types).includes('Files')) event.preventDefault(); });
    document.addEventListener('drop', event => { if (Array.from(event.dataTransfer.types).includes('Files')) event.preventDefault(); });
    clear.addEventListener('click', () => { if (!busy) {
        if (files.some(i => i.state !== 'done') && !window.confirm(t('Clear this batch, including incomplete files? Download the batch report first if you need its references.'))) return;
        files = []; directories = []; queue.replaceChildren(); feedback.textContent = ''; saveBatch(); update();
    } });
    document.getElementById('background-upload-open')?.addEventListener('click', () => document.getElementById('tab-upload')?.click());
    window.addEventListener('beforeunload', event => { if (busy || files.some(i => i.state !== 'done' && i.state !== 'invalid')) { event.preventDefault(); event.returnValue = ''; } });
    report.addEventListener('click', () => {
        const csvValue = value => {
            let text = String(value ?? '');
            if (/^[=+@\-\t\r\n]/.test(text)) text = "'" + text;
            return '"' + text.replaceAll('"', '""') + '"';
        };
        const rows = [[t('File name'), t('Bytes'), t('Status'), t('Details'), t('File ID'), t('Upload reference'), t('Folder date')]];
        files.forEach(item => rows.push([item.relativePath || item.file.name, item.file.size, item.needsSelection ? t('Reselect original file') : item.state, item.message, item.documentId, item.key, item.folderDate]));
        const url = URL.createObjectURL(new Blob([rows.map(row => row.map(csvValue).join(',')).join('\r\n')], {type: 'text/csv;charset=utf-8'}));
        const link = document.createElement('a'); link.href = url; link.download = 'upload-batch-report.csv'; link.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (busy) return;
        const pending = files.filter(i => !i.needsSelection && (i.state === 'queued' || i.state === 'failed'));
        if (!pending.length && !directories.some(directory => !directory.done)) return;
        const recipients = Array.from(share.querySelectorAll('input:checked'), option => option.value);
        busy = true;
        feedback.textContent = t('Uploads continue while you browse this website. Keep this browser tab open.');
        update();
        try {
            const parents = new Set(directories.filter(directory => !directory.done).map(directory => directory.parentId));
            for (const parentId of parents) {
                const group = directories.filter(directory => directory.parentId === parentId && !directory.done);
                const response = await fetch('/files/folders/tree', {method:'POST', headers:{'Content-Type':'application/json', Accept:'application/json', 'X-CSRF-TOKEN':form.querySelector('[name="_token"]').value}, body:JSON.stringify({parent_id:parentId, paths:group.map(directory => directory.path)})});
                if (!response.ok) { const data = await response.json(); throw Error(data.message || t('Could not create folders. Please retry.')); }
                group.forEach(directory => { directory.done = true; });saveBatch();
            }
        } catch (error) { busy = false;feedback.textContent = error.message;update();return; }
        // Separate requests give each file independent progress and failure handling.
        const worker = async () => {
            while (pending.length) {
                const item = pending.shift();
                for (let attempt = 0; attempt < 3; attempt++) {
                    await upload(item, recipients);
                    if (item.state === 'done' || !item.retryable) break;
                    if (attempt < 2) {
                        item.message = t('Connection/server issue. Retrying :attempt/2…', {attempt: attempt + 1});
                        update();
                        await new Promise(resolve => setTimeout(resolve, (attempt + 1) * 1000));
                    }
                }
            }
        };
        await Promise.all(Array.from({length: Math.min(3, pending.length)}, worker));
        busy = false;
        const done = files.filter(i => i.state === 'done').length;
        const failed = files.filter(i => i.state === 'failed').length;
        const waiting = files.filter(i => i.needsSelection).length;
        const rejected = files.filter(i => i.state === 'invalid').length;
        feedback.textContent = [t(':done file(s) uploaded.', {done}), directories.length ? t(':count folders created.', {count:directories.length}) : '', failed ? t(':failed failed. Retry failed files; confirmed files will not be uploaded again.', {failed}) : '', waiting ? t(':waiting need the original files reselected.', {waiting}) : '', rejected ? t(':rejected rejected (over size limit).', {rejected}) : '', t('View uploaded files or download the batch report.'), !recoveryAvailable ? t('Browser recovery storage is unavailable; save your batch report.') : ''].filter(Boolean).join(' ');
        update();
        if (done === files.length) document.getElementById('upload-list-details').open = false;
        await refreshListing();
    });

    async function refreshListing() {
        if (typeof fetch !== 'function' || typeof DOMParser === 'undefined') return;
        try {
            const url = new URL('/files', window.location.origin);
            if (window.location.pathname === '/files') url.search = window.location.search;
            ['snapshot', 'cursor', 'requests_page'].forEach(key => url.searchParams.delete(key));
            const response = await fetch(url, {headers: {'Accept': 'text/html'}, cache: 'no-store'});
            if (!response.ok) return;
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            for (const id of ['file-library', 'daily-upload-register', 'library-stats', 'daily-folders']) {
                const old = document.getElementById(id), fresh = page.getElementById(id);
                if (old && fresh) { fresh.hidden = old.hidden; old.replaceWith(fresh); }
            }
            document.dispatchEvent(new Event('portal-library-refreshed'));
            if (window.location.pathname === '/files') window.history.replaceState(null, '', url);
        } catch { /* The explicit View uploaded files link is still available. */ }
    }

    async function restoreBatch() {
        let saved;
        try { saved = JSON.parse(window.localStorage.getItem(storageKey) || '[]'); } catch { return; }
        try {
            const savedFolders = JSON.parse(window.localStorage.getItem(storageKey+'-folders') || '[]');
            if (Array.isArray(savedFolders)) directories = savedFolders.slice(0,1000).filter(directory => typeof directory.path === 'string' && Number.isInteger(directory.parentId) && directory.parentId >= 0);
        } catch {}
        if (!Array.isArray(saved) || !saved.length) { update();return; }
        const valid = saved.slice(0, 1000).filter(i => typeof i.name === 'string' && Number.isFinite(i.size) && typeof i.key === 'string' && /^[0-9a-f-]{36}$/i.test(i.key));
        // Metadata is recoverable; browsers require reselecting local file bytes.
        for (const entry of valid) {
            addFiles([{name: entry.name, size: entry.size, lastModified: entry.lastModified, webkitRelativePath:entry.relativePath || ''}], false);
            const item = files[files.length - 1];
            item.key = entry.key;
            item.recipients = Array.isArray(entry.recipients) ? entry.recipients : undefined;
            item.documentId = entry.documentId;
            item.folderDate = entry.folderDate;
            item.parentFolderId = Number(entry.parentFolderId) || 0;
            item.needsSelection = true;
            item.state = item.file.size > maxBytes ? 'invalid' : 'waiting';
            item.message = t('Checking server receipt…');
        }
        busy = true; update();
        try {
            const response = await fetch('/files/upload-status', {method: 'POST', headers: {
                'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value,
            }, body: JSON.stringify({keys: files.map(i => i.key)})});
            if (!response.ok) throw new Error('Receipt lookup failed');
            const data = await response.json();
            for (const item of files) {
                const receipt = data.documents.find(doc => doc.upload_key === item.key);
                if (receipt && (receipt.available || receipt.deleted)) {
                    item.state = 'done'; item.needsSelection = false; item.percent = 100;
                    item.documentId = receipt.id; item.folderDate = receipt.folder_date;
                    item.message = receipt.deleted ? t('Previously uploaded · File #:id · Deleted with approval', {id: receipt.id}) : t('Verified on server · File #:id', {id: receipt.id});
                } else if (receipt) {
                    item.state = 'failed'; item.message = t('File #:id needs administrator storage recovery. Keep your original.', {id: receipt.id});
                } else item.message = item.state === 'invalid' ? t('Too large. Maximum 100 MB per file.') : t('Reselect the original file to resume safely');
            }
            feedback.textContent = t('Previous batch restored and checked against server receipts. Reselect only incomplete original files to resume.');
        } catch {
            files.forEach(item => { item.message = t('Reselect original file; server receipt will be checked during upload'); });
            feedback.textContent = t('Batch restored. Server verification unavailable. Reselect the originals to retry safely with the same references.');
        }
        busy = false; saveBatch(); update();
        document.getElementById('upload-list-details').open = files.some(item => item.state !== 'done');
    }
    update();
    restoreBatch();
})();
