// Run in PowerShell:
// $env:FILEVAULT_TEST_PASSWORD='your-password'; node tests/local-storage-responsive.cjs
// Uses an isolated headless Chrome profile; does not upload or change server records.
const {spawn} = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const assert = require('node:assert/strict');

const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
const base = process.env.FILEVAULT_TEST_URL || 'http://127.0.0.1:8015';
const password = process.env.FILEVAULT_TEST_PASSWORD;
if (!password) throw new Error('Set FILEVAULT_TEST_PASSWORD before running the browser check.');
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'filevault-responsive-'));
const browser = spawn(process.env.BROWSER_EXE || 'C:/Program Files/Google/Chrome/Application/chrome.exe', [
    '--headless=new', '--no-first-run', '--no-default-browser-check', '--disable-gpu',
    '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank',
], {windowsHide: true, stdio: 'ignore'});
let socket;
let sequence = 0;
const pending = new Map();

async function send(method, params = {}) {
    const id = ++sequence;
    return new Promise((resolve, reject) => {
        const timer = setTimeout(() => { pending.delete(id); reject(new Error(`Timed out: ${method}`)); }, 20000);
        pending.set(id, {resolve, reject, timer});
        socket.send(JSON.stringify({id, method, params}));
    });
}
async function evaluate(expression) {
    const result = await send('Runtime.evaluate', {expression, returnByValue: true, awaitPromise: true});
    if (result.exceptionDetails) throw new Error(result.exceptionDetails.text);
    return result.result.value;
}
async function waitFor(expression) {
    for (let i = 0; i < 100; i++) { if (await evaluate(expression)) return; await delay(100); }
    throw new Error(`Page state not reached: ${expression}`);
}
async function viewport(width) {
    await send('Emulation.setDeviceMetricsOverride', {width, height: 1000, deviceScaleFactor: 1, mobile: width <= 760});
    await delay(80);
}
async function checkWidth(width, pageName) {
    await viewport(width);
    const result = await evaluate(`(() => {
        const width = document.documentElement.clientWidth;
        const overflow = Array.from(document.querySelectorAll('input:not([hidden]),select,textarea,button,.card,.responsive-table'))
            .filter(el => el.getClientRects().length && !el.closest('.share-options'))
            .filter(el => { const rect = el.getBoundingClientRect(); return rect.left < -1 || rect.right > width + 1; })
            .map(el => el.id || el.className || el.tagName);
        return {width, scroll: document.documentElement.scrollWidth, overflow,
            fileTableDisplay: document.querySelector('.file-table') ? getComputedStyle(document.querySelector('.file-table')).display : null,
            inputSize: getComputedStyle(document.querySelector('input:not([hidden])')).fontSize};
    })()`);
    assert.ok(result.scroll <= result.width + 1, `${pageName} ${width}px: page overflow ${JSON.stringify(result)}`);
    assert.deepEqual(result.overflow, [], `${pageName} ${width}px: controls overflow`);
    if (pageName.startsWith('dashboard') && width <= 760) assert.equal(result.fileTableDisplay, 'block');
    if (width <= 760) assert.ok(parseFloat(result.inputSize) >= 16, 'Mobile form font avoids automatic input zoom');
    console.log(`PASS: ${pageName} ${width}px — no horizontal overflow, controls fit`);
}

(async () => {
    try {
        let port;
        const activePortFile = path.join(profile, 'DevToolsActivePort');
        for (let i = 0; i < 100; i++) {
            if (fs.existsSync(activePortFile)) { port = Number(fs.readFileSync(activePortFile, 'utf8').split('\n')[0]); break; }
            await delay(100);
        }
        if (!port) throw new Error('Headless Chrome did not start.');
        const tabs = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
        socket = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
        await new Promise((resolve, reject) => { socket.addEventListener('open', resolve, {once: true}); socket.addEventListener('error', reject, {once: true}); });
        socket.addEventListener('message', event => {
            const message = JSON.parse(event.data), entry = pending.get(message.id);
            if (!entry) return;
            clearTimeout(entry.timer); pending.delete(message.id);
            message.error ? entry.reject(new Error(message.error.message)) : entry.resolve(message.result);
        });
        await send('Page.enable');
        await send('Page.navigate', {url: `${base}/files/login`});
        await waitFor('!!document.getElementById("email")');
        for (const width of [320, 390, 768, 1440]) await checkWidth(width, 'login');
        await evaluate(`document.getElementById('email').value = ${JSON.stringify(process.env.FILEVAULT_TEST_EMAIL || 'admin@filevault.local')}; document.getElementById('password').value = ${JSON.stringify(password)}; document.querySelector('.login form').requestSubmit();`);
        await waitFor('!!document.getElementById("upload-form")');
        await waitFor('document.readyState === "complete"');
        assert.equal(await evaluate('document.querySelectorAll("[data-portal-panel]:not([hidden])").length'), 1);
        assert.equal(await evaluate('document.getElementById("panel-files").hidden'), false, 'Default opens files, not the upload form');
        await evaluate('document.getElementById("tab-upload").click()');
        await evaluate(`(() => {
            const selection = new DataTransfer();
            selection.items.add(new File(['example'], 'Very-long-upload-name-' + 'scan_'.repeat(20) + '.pdf', {type: 'application/pdf'}));
            selection.items.add(new File(['image'], 'photo.png', {type: 'image/png'}));
            document.getElementById('file-dropzone').dispatchEvent(new DragEvent('drop', {dataTransfer: selection, bubbles: true, cancelable: true}));
            const firstName = document.querySelector('.file-table tbody td strong');
            if (firstName) firstName.textContent = 'Very-long-file-name-' + 'document_'.repeat(20) + '.pdf';
            const table = document.querySelector('.request-table tbody');
            table.innerHTML = '<tr><td><strong>Long pending deletion filename.pdf</strong><br><small>Requested by user</small></td><td data-label="Reason">' + 'Long review reason '.repeat(15) + '</td><td data-label="Status"><span class="badge pending">Pending</span></td><td data-label="Review"><input placeholder="Review note"><div class="actions"><button>Approve deletion</button><button>Reject</button></div></td></tr>';
        })()`);
        for (const width of [320, 390, 768, 1024, 1440]) {
            for (const name of ['files', 'upload', 'requests', 'users']) {
                await evaluate(`document.getElementById('tab-${name}').click()`);
                assert.equal(await evaluate('document.querySelectorAll("[data-portal-panel]:not([hidden])").length'), 1);
                await checkWidth(width, `dashboard ${name}`);
            }
        }
        assert.equal(await evaluate('document.getElementById("upload-queue").children.length'), 2, 'Tab switching retains selected upload queue');
        await evaluate('document.getElementById("tab-upload").click(); document.getElementById("tab-upload").dispatchEvent(new KeyboardEvent("keydown", {key: "ArrowRight", bubbles: true}));');
        assert.equal(await evaluate('document.getElementById("panel-requests").hidden'), false, 'Keyboard arrows activate the adjacent tab');
        console.log('PASS: one panel visible, upload queue retained, keyboard tab navigation works');
        await evaluate('document.getElementById("tab-upload").click()');
        const options = await evaluate('document.querySelectorAll("#share_with input[type=checkbox]").length');
        if (options) {
            await evaluate('document.querySelector("#share_with input[type=checkbox]").click()');
            assert.equal(await evaluate('document.querySelectorAll("#share_with input:checked").length'), 1);
            console.log('PASS: touch-friendly sharing checkboxes select a recipient');
        }
        await viewport(390);
        await evaluate('document.getElementById("tab-files").click()');
        const screenshots = path.resolve('storage/app/responsive-qa');
        fs.mkdirSync(screenshots, {recursive: true});
        await evaluate('window.scrollTo({top: 0, behavior: "instant"})');
        let screenshot = await send('Page.captureScreenshot', {format: 'png'});
        fs.writeFileSync(path.join(screenshots, 'mobile-dashboard.png'), Buffer.from(screenshot.data, 'base64'));
        await evaluate('document.getElementById("file-library").scrollIntoView({behavior: "instant", block: "start"})');
        await delay(100);
        screenshot = await send('Page.captureScreenshot', {format: 'png'});
        fs.writeFileSync(path.join(screenshots, 'mobile-files.png'), Buffer.from(screenshot.data, 'base64'));
        console.log('Responsive browser checks passed. Screenshots: storage/app/responsive-qa');
    } finally {
        if (socket) socket.close();
        browser.kill();
        for (const entry of pending.values()) clearTimeout(entry.timer);
    }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
