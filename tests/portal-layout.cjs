// First generate rollback-only fixtures:
// $env:FILEVAULT_QA_DIR="$env:TEMP/filevault-portal-qa"; php tests/local-storage-smoke.php
// Then: node tests/portal-layout.cjs
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const {spawn} = require('node:child_process');
const {pathToFileURL} = require('node:url');
const assert = require('node:assert/strict');
const root = process.env.FILEVAULT_QA_DIR || path.join(os.tmpdir(), 'filevault-portal-qa');
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'filevault-layout-chrome-'));
const chrome = spawn(process.env.CHROME_EXE || 'C:/Program Files/Google/Chrome/Application/chrome.exe',
    ['--headless', '--disable-gpu', '--no-first-run', '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank'], {stdio: 'ignore', windowsHide: true});
let socket, counter = 0;
const pending = new Map();
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
function send(method, params = {}) {
    return new Promise((resolve, reject) => {
        const id = ++counter;
        const timer = setTimeout(() => { pending.delete(id); reject(Error(`Timed out: ${method}`)); }, 10000);
        pending.set(id, {resolve, reject, timer});
        socket.send(JSON.stringify({id, method, params}));
    });
}
async function evaluate(expression) {
    const result = await send('Runtime.evaluate', {expression, returnByValue: true});
    if (result.exceptionDetails) throw Error(JSON.stringify(result.exceptionDetails));
    return result.result.value;
}
(async () => {
    try {
        const portFile = path.join(profile, 'DevToolsActivePort');
        for (let i = 0; !fs.existsSync(portFile) && i < 100; i++) await delay(100);
        const port = Number(fs.readFileSync(portFile, 'utf8').split('\n')[0]);
        const tabs = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
        socket = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
        await new Promise((resolve, reject) => { socket.addEventListener('open', resolve, {once:true}); socket.addEventListener('error', reject, {once:true}); });
        socket.addEventListener('message', event => {
            const data = JSON.parse(event.data), entry = pending.get(data.id);
            if (!entry) return;
            clearTimeout(entry.timer); pending.delete(data.id);
            data.error ? entry.reject(Error(data.error.message)) : entry.resolve(data.result);
        });
        await send('Page.enable');
        for (const variant of ['en-files', 'en-users', 'en-upload', 'ar-files']) {
            await send('Page.navigate', {url: pathToFileURL(path.join(root, `${variant}.html`)).href});
            for (let i = 0; i < 50; i++) {
                if (await evaluate('document.readyState === "complete" && !!document.querySelector(".drive-workspace")')) break;
                await delay(50);
            }
            for (const width of [1440, 1024, 768, 390, 320]) {
                await send('Emulation.setDeviceMetricsOverride', {width, height: 1000, deviceScaleFactor: 1, mobile: false});
                const result = await evaluate(`(() => ({width: innerWidth, scroll: document.documentElement.scrollWidth, sidebar: Math.round(document.querySelector('.drive-sidebar').getBoundingClientRect().width),
                    overflowing: [...document.querySelectorAll('header, .drive-main, .user-card, .compact-summary')].filter(e => e.getBoundingClientRect().right > innerWidth + 1 || e.getBoundingClientRect().left < -1).map(e => e.className || e.tagName)}))()`);
                assert.ok(result.scroll <= width + 1, `${variant} ${width}px overflow: ${JSON.stringify(result)}`);
                assert.deepEqual(result.overflowing, [], `${variant} ${width}px clipped content`);
                if (width === 1440) assert.ok(result.sidebar <= 190, 'Desktop sidebar is compact');
                const shot = await send('Page.captureScreenshot', {format:'png'});
                fs.writeFileSync(path.join(root, `${variant}-${width}.png`), Buffer.from(shot.data, 'base64'));
                console.log(`PASS: ${variant} ${width}px; no horizontal overflow`);
            }
        }
    } catch (error) { console.error(error); process.exitCode = 1; }
    finally { if (socket) socket.close(); chrome.kill(); }
})();
