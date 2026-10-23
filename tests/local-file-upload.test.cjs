// Run: node --test tests/local-file-upload.test.cjs
const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

class Element {
    constructor() { this.events = {}; this.children = []; this.dataset = {}; this.classList = {add() {}, remove() {}}; this.value = ''; }
    addEventListener(type, callback) { (this.events[type] ??= []).push(callback); }
    emit(type, data = {}) { return Promise.all((this.events[type] || []).map(fn => fn({preventDefault() {}, ...data}))); }
    append(...children) { children.forEach(child => child.parent = this); this.children.push(...children); }
    replaceChildren() { this.children = []; }
    setAttribute(name, value) { this[name] = value; }
    remove() { this.parent.children = this.parent.children.filter(child => child !== this); }
}

function setup(options = {}) {
    const elements = new Map();
    const get = id => { if (!elements.has(id)) elements.set(id, new Element()); return elements.get(id); };
    get('upload-form').action = '/files/upload';
    get('upload-form').querySelector = () => ({value: 'csrf-token'});
    get('share_with').querySelectorAll = () => [{value: '2'}, {value: '3'}];
    const requests = [];
    class XHR extends Element {
        constructor() { super(); this.upload = new Element(); }
        open(method, url) { this.method = method; this.url = url; }
        setRequestHeader() {}
        send(body) { this.body = body; requests.push(this); }
        finish(status, data) { this.status = status; this.responseText = JSON.stringify(data); return this.emit('load'); }
    }
    const document = new Element();
    document.getElementById = get;
    document.createElement = () => new Element();
    const window = new Element();
    window.portalTranslations = options.translations || {};
    const saved = new Map(options.saved ? [['file-vault-upload-batch-undefined', JSON.stringify(options.saved)]] : []);
    window.localStorage = {getItem: key => saved.get(key), setItem: (key, value) => saved.set(key, value)};
    window.confirm = () => true;
    vm.runInNewContext(fs.readFileSync('public/js/local-file-upload.js', 'utf8'), {
        document, window, XMLHttpRequest: XHR, fetch: options.fetch,
        setTimeout: callback => setImmediate(callback),
        crypto: require('node:crypto').webcrypto,
        FormData: class { constructor() { this.values = []; } append(...args) { this.values.push(args); } },
    });
    return {get, requests, saved};
}
const file = (name, size = 1024) => ({name, size, lastModified: 123});
const flush = () => new Promise(resolve => setImmediate(resolve));

test('Arabic labels preserve upload states and receipts when changing languages', async () => {
    const translations = JSON.parse(fs.readFileSync('resources/lang/ar.json', 'utf8'));
    const {get, requests, saved} = setup({translations});
    get('file').files = [file('report.pdf')];
    await get('file').emit('change');
    assert.equal(get('upload-submit').textContent, translations['Upload files']);
    const finished = get('upload-form').emit('submit');
    await requests[0].finish(422, {message: 'Rejected'});
    await finished;
    const batch = JSON.parse(saved.values().next().value);
    assert.equal(batch[0].state, 'failed', 'Persist machine states independently of locale');
    assert.equal(get('upload-submit').textContent, translations['Upload remaining / retry failed']);
    const retry = get('upload-form').emit('submit');
    await requests[1].finish(201, {document: {id: 99, folder_date: '2026-10-23'}});
    await retry;
    assert.ok(get('upload-feedback').textContent.includes('تم رفع 1 ملف.'));
    assert.equal(JSON.parse(saved.values().next().value)[0].state, 'done');
});

test('mixed file queue, bounded concurrency, progress, errors and selective retry', async () => {
    const {get, requests} = setup();
    get('file').files = ['report.pdf', 'photo.png', 'audio.mp3', 'video.mp4'].map(name => file(name));
    await get('file').emit('change');
    assert.equal(get('upload-queue').children.length, 4);
    const finished = get('upload-form').emit('submit');
    assert.equal(requests.length, 3, 'At most three uploads start together');
    assert.equal(get('upload-submit').disabled, true);
    assert.equal(requests[0].body.values.filter(([key]) => key === 'share_with[]').length, 2);
    await requests[0].upload.emit('progress', {lengthComputable: true, loaded: 50, total: 100});
    assert.equal(get('upload-queue').children[0].children[1].value, 50);
    assert.equal(get('upload-total-progress').value, 12);
    await requests[0].finish(422, {errors: {file: ['Server rejected this file.']}});
    await flush();
    assert.equal(requests.length, 4, 'Next queued file starts when a slot is free');
    for (let i = 1; i < 4; i++) await requests[i].finish(201, {document: {id: i, folder_date: '2026-10-23'}});
    await finished;
    assert.equal(get('upload-submit').disabled, false);
    assert.match(get('upload-feedback').textContent, /3 file\(s\) uploaded.*1 failed/);
    const retried = get('upload-form').emit('submit');
    assert.equal(requests.length, 5, 'Only the failed file is retried');
    assert.equal(requests[4].body.values[0][1].name, 'report.pdf');
    await requests[4].finish(201, {document: {id: 5, folder_date: '2026-10-23'}});
    await retried;
    assert.equal(get('upload-total-progress').value, 100);
    assert.equal(get('upload-submit').disabled, true);
    assert.equal(get('upload-view-files').hidden, false);
});

test('restores a lost-response batch from server receipts and resumes unconfirmed originals with the same key', async () => {
    const crypto = require('node:crypto').webcrypto;
    const confirmedKey = crypto.randomUUID(), pendingKey = crypto.randomUUID();
    const {get, requests} = setup({
        saved: [
            {name: 'already-saved.pdf', size: 1024, lastModified: 123, key: confirmedKey, state: 'uploading'},
            {name: 'pending.mp4', size: 1024, lastModified: 123, key: pendingKey, state: 'queued'},
        ],
        fetch: async () => ({ok: true, json: async () => ({documents: [{upload_key: confirmedKey, id: 10, folder_date: '2026-10-23', available: true, deleted: false}]})}),
    });
    await flush();
    assert.equal(get('upload-queue').children.length, 2);
    assert.match(get('upload-queue').children[0].children[0].children[0].children[1].textContent, /File #10/);
    assert.equal(get('upload-submit').disabled, true, 'Missing original bytes cannot be uploaded accidentally');
    get('file').files = [file('pending.mp4')];
    await get('file').emit('change');
    const finished = get('upload-form').emit('submit');
    assert.equal(requests.length, 1, 'Server-confirmed file is not sent again after refresh');
    assert.equal(requests[0].body.values.find(([key]) => key === 'upload_key')[1], pendingKey);
    await requests[0].finish(201, {document: {id: 11, folder_date: '2026-10-23'}});
    await finished;
    assert.equal(get('upload-total-progress').value, 100);
});

test('transient failure is retried with a stable upload key and confirmed receipt', async () => {
    const {get, requests} = setup();
    get('file').files = [file('audio.mp3')];
    await get('file').emit('change');
    const finished = get('upload-form').emit('submit');
    await requests[0].emit('error');
    await flush(); await flush();
    assert.equal(requests.length, 2);
    const key = req => req.body.values.find(([name]) => name === 'upload_key')[1];
    assert.equal(key(requests[0]), key(requests[1]), 'Retry retains original identity after response loss');
    await requests[1].finish(201, {document: {id: 12, folder_date: '2026-10-23'}});
    await finished;
    assert.match(get('upload-feedback').textContent, /1 file\(s\) uploaded/);
});

test('distinct files with identical names, sizes and dates are never silently omitted', async () => {
    const {get, requests} = setup();
    get('file').files = [file('scan.pdf'), file('scan.pdf')];
    await get('file').emit('change');
    assert.equal(get('upload-queue').children.length, 2);
    const finished = get('upload-form').emit('submit');
    assert.equal(requests.length, 2);
    const keys = requests.map(req => req.body.values.find(([name]) => name === 'upload_key')[1]);
    assert.notEqual(keys[0], keys[1]);
    for (const [i, req] of requests.entries()) await req.finish(201, {document: {id: i + 1, folder_date: '2026-10-23'}});
    await finished;
});

test('drop selection, duplicate suppression, oversize rejection and queue removal', async () => {
    const {get, requests} = setup();
    const selected = file('<script>alert(1)</script>.pdf');
    await get('file-dropzone').emit('drop', {dataTransfer: {files: [selected, selected, file('large.mp4', 101 * 1024 * 1024)]}});
    assert.equal(get('upload-queue').children.length, 2);
    assert.equal(get('upload-queue').children[0].children[0].children[0].children[0].textContent, selected.name);
    const finished = get('upload-form').emit('submit');
    assert.equal(requests.length, 1, 'Oversize file is not sent');
    await requests[0].finish(201, {document: {id: 1, folder_date: '2026-10-23'}});
    await finished;
    await get('upload-queue').children[1].children[0].children[1].emit('click');
    assert.equal(get('upload-queue').children.length, 1);
    await get('upload-clear').emit('click');
    assert.equal(get('upload-queue').children.length, 0);
});
