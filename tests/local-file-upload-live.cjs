// Explicit live integration check: uploads two generated files, verifies their
// bytes/previews, then deletes only those newly created documents via the app.
// Set FILEVAULT_TEST_URL, FILEVAULT_TEST_EMAIL and FILEVAULT_TEST_PASSWORD.
const {spawn} = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const assert = require('node:assert/strict');
const base = process.env.FILEVAULT_TEST_URL;
const email = process.env.FILEVAULT_TEST_EMAIL;
const password = process.env.FILEVAULT_TEST_PASSWORD;
assert.ok(base && email && password, 'Set live test URL and credentials');
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'filevault-upload-live-'));
const browser = spawn(process.env.BROWSER_EXE || 'C:/Program Files/Google/Chrome/Application/chrome.exe',
    ['--headless=new', '--disable-gpu', '--no-first-run', '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank'], {windowsHide:true, stdio:'ignore'});
const pending = new Map();
const exceptions = [];
let socket, sequence = 0, receipts = [];
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
function send(method, params = {}) {
    return new Promise((resolve, reject) => {
        const id = ++sequence;
        const timer = setTimeout(() => { pending.delete(id); reject(Error(`Timed out: ${method}`)); }, 30000);
        pending.set(id, {resolve, reject, timer});
        socket.send(JSON.stringify({id, method, params}));
    });
}
async function evaluate(expression) {
    const data = await send('Runtime.evaluate', {expression, returnByValue:true, awaitPromise:true});
    if (data.exceptionDetails) throw Error(data.exceptionDetails.exception?.description || data.exceptionDetails.text);
    return data.result.value;
}
async function waitFor(expression) {
    for (let i=0;i<100;i++) { if (await evaluate(expression)) return; await delay(200); }
    throw Error(`Page state not reached: ${expression}`);
}
async function cleanup() {
    if (!receipts.length || !socket) return;
    if (process.env.FILEVAULT_TEST_BULK_UI === '1') {
        const visible = await evaluate(`Array.from(new Set([...document.querySelectorAll('[data-select-item="files"]')].map(input=>Number(input.value)))).sort((a,b)=>a-b)`);
        assert.deepEqual(visible, receipts.map(item=>item.documentId).sort((a,b)=>a-b), 'Bulk UI must contain only our generated test files');
        await evaluate(`window.confirm=()=>true;document.querySelector('[data-select-all="files"]').click()`);
        assert.equal(await evaluate(`Number(document.querySelector('[data-selection-count="files"]').textContent)`), receipts.length);
        await evaluate(`document.querySelector('#bulk-files-form button').click()`);
        await waitFor(`document.getElementById('portal-navigation-notices')?.textContent.includes('Deleted ${receipts.length} files. 0 failed.')`);
        const removed = await evaluate(`Promise.all(${JSON.stringify(receipts)}.map(async item=>(await fetch('/files/'+item.documentId+'/download')).status))`);
        assert.ok(removed.every(status=>status===404));
        assert.equal(await evaluate('window.__qaUploadForm === document.getElementById("upload-form")'),true);
        receipts = [];
        console.log('PASS: live Select-all + bulk-delete form removes only generated files without reloading uploader');
        return;
    }
    const result = await evaluate(`(async () => {
        const csrf = document.querySelector('[name="_token"]').value;
        const result = [];
        for (const receipt of ${JSON.stringify(receipts)}) {
            const response = await fetch('/files/'+receipt.documentId+'/delete', {method:'POST', headers:{'X-CSRF-TOKEN':csrf}});
            result.push({id:receipt.documentId, status:response.status});
        }
        return result;
    })()`);
    assert.ok(result.every(item=>item.status===200), 'Generated test files cleaned up');
    receipts = [];
    console.log('PASS: generated test files removed');
}
(async () => {
    try {
        const portFile = path.join(profile,'DevToolsActivePort');
        for (let i=0;!fs.existsSync(portFile)&&i<100;i++) await delay(100);
        const port = Number(fs.readFileSync(portFile,'utf8').split('\n')[0]);
        const tabs = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
        socket = new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);
        await new Promise((resolve,reject)=>{socket.addEventListener('open',resolve,{once:true});socket.addEventListener('error',reject,{once:true});});
        socket.addEventListener('message',event=>{
            const data=JSON.parse(event.data);
            if(data.method==='Runtime.exceptionThrown') exceptions.push(data.params.exceptionDetails.exception?.description || data.params.exceptionDetails.text);
            const entry=pending.get(data.id);if(!entry)return;
            clearTimeout(entry.timer);pending.delete(data.id);
            data.error?entry.reject(Error(data.error.message)):entry.resolve(data.result);
        });
        await send('Page.enable');await send('Runtime.enable');
        await send('Page.navigate',{url:base+'/files/login'});
        await waitFor('!!document.getElementById("email")');
        await evaluate(`document.getElementById('email').value=${JSON.stringify(email)};document.getElementById('password').value=${JSON.stringify(password)};document.querySelector('.login form').requestSubmit()`);
        await waitFor('!!document.getElementById("upload-form") && document.readyState === "complete"');
        console.log('PASS: live super-admin login');
        const context = await evaluate('({secure:isSecureContext, randomUUID:typeof crypto.randomUUID})');
        if (base.startsWith('http://') && !base.includes('localhost') && !base.includes('127.0.0.1')) {
            assert.equal(context.secure,false);assert.equal(context.randomUUID,'undefined');
            console.log('PASS: reproducing real HTTP-IP browser environment');
        }
        const prefix = `filevault-qa-${Date.now()}`;
        const navigation = process.env.FILEVAULT_TEST_NAVIGATION === '1';
        await evaluate(`(() => {
            window.__qaUploadForm = document.getElementById('upload-form');
            window.__qaExpectedText = 'FileVault live upload verification' + 'x'.repeat(${navigation ? 524288 : 0});
            document.getElementById('tab-upload').click();
            const files = new DataTransfer();
            files.items.add(new File([window.__qaExpectedText],${JSON.stringify(prefix+'.txt')},{type:'text/plain'}));
            const png=Uint8Array.from(atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='), c=>c.charCodeAt(0));
            files.items.add(new File([png],${JSON.stringify(prefix+'.png')},{type:'image/png'}));
            document.getElementById('file').files=files.files;
            document.getElementById('file').dispatchEvent(new Event('change'));
        })()`);
        assert.equal(await evaluate('document.getElementById("upload-queue").children.length'),2);
        assert.equal(await evaluate('document.getElementById("upload-submit").disabled'),false);
        console.log('PASS: text/image selection creates queue without JavaScript errors');
        if (navigation) {
            await send('Network.enable');
            await send('Network.emulateNetworkConditions', {offline:false, latency:20, downloadThroughput:4*1024*1024, uploadThroughput:64*1024});
        }
        await evaluate('document.getElementById("upload-form").requestSubmit()');
        if (navigation) {
            assert.equal(await evaluate('document.getElementById("background-upload").hidden'),false);
            await evaluate('document.getElementById("tab-users").click()');
            await waitFor('document.querySelector(".drive-main").getAttribute("aria-busy") !== "true"');
            assert.equal(await evaluate('window.__qaUploadForm === document.getElementById("upload-form")'),true);
            await evaluate(`document.getElementById('tab-files').click()`);
            await waitFor('document.querySelector(".drive-main").getAttribute("aria-busy") !== "true"');
            await evaluate(`document.querySelector('.library-filters [name="q"]').value=${JSON.stringify(prefix)};document.querySelector('.library-filters').requestSubmit()`);
            await waitFor(`location.search.includes(${JSON.stringify(prefix)}) && document.querySelector('.drive-main').getAttribute('aria-busy') !== 'true'`);
            assert.equal(await evaluate('window.__qaUploadForm === document.getElementById("upload-form")'),true);
            console.log('PASS: admin tab and file-filter navigation retain the live upload form');
        }
        await waitFor('document.getElementById("upload-feedback").textContent.includes("2 file(s) uploaded.")');
        receipts = await evaluate(`JSON.parse(localStorage.getItem('file-vault-upload-batch-'+document.getElementById('upload-form').dataset.userId))`);
        assert.equal(receipts.length,2);assert.ok(receipts.every(item=>item.state==='done' && item.documentId));
        console.log('PASS: live browser upload receives two verified server receipts');
        const checked = await evaluate(`(async () => {
            const result=[];
            for(const receipt of ${JSON.stringify(receipts)}){
                const download=await fetch('/files/'+receipt.documentId+'/download');
                const bytes=await download.arrayBuffer();
                const preview=await fetch('/files/'+receipt.documentId+'/preview');
                const stream=await fetch('/files/'+receipt.documentId+'/download?preview=1');
                const exact = receipt.name.endsWith('.txt')
                    ? new TextDecoder().decode(bytes) === window.__qaExpectedText
                    : btoa(String.fromCharCode(...new Uint8Array(bytes))) === 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=';
                result.push({name:receipt.name,download:download.status,bytes:bytes.byteLength,exact,preview:preview.status,stream:stream.status,contentType:stream.headers.get('Content-Type')});
            }
            return result;
        })()`);
        assert.ok(checked.every(file=>file.download===200 && file.preview===200 && file.stream===200 && file.bytes>0 && file.exact));
        assert.ok(checked.find(file=>file.name.endsWith('.png')).contentType.startsWith('image/png'));
        assert.ok(checked.find(file=>file.name.endsWith('.txt')).contentType.startsWith('text/plain'));
        assert.deepEqual(exceptions,[]);
        console.log('PASS: live text/image download and inline previews');
        if (navigation) {
            await send('Network.emulateNetworkConditions', {offline:false, latency:0, downloadThroughput:-1, uploadThroughput:-1});
            await waitFor(`!!document.querySelector('.file-gallery a[href="/files/${receipts[0].documentId}/preview"]')`);
            await evaluate(`document.querySelector('.file-gallery a[href="/files/${receipts[0].documentId}/preview"]').click()`);
            await waitFor('!!document.querySelector("#portal-preview-outlet .preview-stage")');
            assert.equal(await evaluate('window.__qaUploadForm === document.getElementById("upload-form")'),true);
            await evaluate(`document.getElementById('tab-upload').click();const files=new DataTransfer();files.items.add(new File(['next batch'],'next-batch.txt',{type:'text/plain'}));document.getElementById('file').files=files.files;document.getElementById('file').dispatchEvent(new Event('change'));`);
            assert.equal(await evaluate('document.getElementById("upload-queue").children.length'),1);
            console.log('PASS: preview navigation retains uploader; next batch hides completed process');
        }
        await cleanup();
    } catch(error) { console.error(error.message);process.exitCode=1; }
    finally {
        try { await cleanup(); } catch(error) { console.error('Test-file cleanup failed:',error.message);process.exitCode=1; }
        if(socket)socket.close();browser.kill();for(const entry of pending.values())clearTimeout(entry.timer);
    }
})();
