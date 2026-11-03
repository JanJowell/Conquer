import test from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { mkdtemp, readFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve, sep } from 'node:path';
import { spawn, execFileSync } from 'node:child_process';
import { createServer } from 'node:http';
import { setTimeout as delay } from 'node:timers/promises';

const root = resolve(import.meta.dirname, '../..');
const chrome = [process.env.CHROME_PATH, 'C:/Program Files/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', '/usr/bin/chromium', '/usr/bin/google-chrome']
    .find(path => path && existsSync(path));

test('submitted event summaries are readable responsive and keyboard expandable without scripts', { skip: !chrome, timeout: 60000 }, async () => {
    const html = execFileSync('php', ['tests/Js/fixtures/submitted-event-setup.php'], { cwd: root, encoding: 'utf8' });
    const profile = await mkdtemp(join(tmpdir(), 'racetech-submitted-summary-'));
    let browser, socket;
    const pending = new Map();
    let id = 0;
    const server = createServer((request, response) => {
        if (request.url === '/') { response.setHeader('Content-Type', 'text/html'); response.end(html); return; }
        const cssFile = readFileSync(join(root, 'public/build/manifest.json'), 'utf8');
        const css = JSON.parse(cssFile)['resources/css/app.css'].file;
        if (request.url === '/build/' + css) {
            response.setHeader('Content-Type', 'text/css'); response.end(readFileSync(join(root, 'public/build', css))); return;
        }
        response.writeHead(404).end();
    });
    const send = (method, params = {}) => new Promise((resolve, reject) => {
        const next = ++id;
        const timeout = setTimeout(() => { pending.delete(next); reject(new Error('Browser command timed out')); }, 10000);
        pending.set(next, { resolve, reject, timeout });
        socket.send(JSON.stringify({ id: next, method, params }));
    });
    try {
        await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
        browser = spawn(chrome, ['--headless=new', '--no-first-run', '--no-default-browser-check', '--disable-background-networking',
            '--remote-debugging-port=0', '--user-data-dir=' + profile, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
        let port;
        for (let attempt = 0; attempt < 100; attempt++) {
            try { port = (await readFile(join(profile, 'DevToolsActivePort'), 'utf8')).split('\n')[0]; break; }
            catch { await delay(100); }
        }
        assert.ok(port, 'Browser must start');
        const pages = await (await fetch('http://127.0.0.1:' + port + '/json/list')).json();
        socket = new WebSocket(pages.find(page => page.type === 'page').webSocketDebuggerUrl);
        await new Promise((resolve, reject) => { socket.addEventListener('open', resolve, { once: true }); socket.addEventListener('error', reject, { once: true }); });
        socket.addEventListener('message', ({ data }) => {
            const message = JSON.parse(data);
            const request = pending.get(message.id);
            if (!request) return;
            clearTimeout(request.timeout); pending.delete(message.id);
            message.error ? request.reject(new Error(message.error.message)) : request.resolve(message.result);
        });
        const evaluate = async expression => {
            const result = await send('Runtime.evaluate', { expression, returnByValue: true });
            if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
            return result.result.value;
        };
        await send('Emulation.setScriptExecutionDisabled', { value: true });
        await send('Emulation.setDeviceMetricsOverride', { width: 1280, height: 900, deviceScaleFactor: 1, mobile: false });
        await send('Page.navigate', { url: 'http://127.0.0.1:' + server.address().port });
        let loaded = false;
        for (let attempt = 0; attempt < 100; attempt++) {
            if (await evaluate("document.readyState === 'complete' && Boolean(document.querySelector('[data-submitted-setup]'))")) { loaded = true; break; }
            await delay(50);
        }
        assert.ok(loaded, 'Summary must render');
        assert.equal(await evaluate("document.querySelector('pre, script')"), null);
        assert.equal(await evaluate("document.querySelector('[data-complete-setup]').open"), false);
        await evaluate("document.querySelector('[data-complete-setup] > summary').focus()");
        assert.equal(await evaluate("document.activeElement === document.querySelector('[data-complete-setup] > summary')"), true);
        await send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13,
            text: '\r', unmodifiedText: '\r' });
        await send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13 });
        assert.equal(await evaluate("document.querySelector('[data-complete-setup]').open"), true);
        assert.equal(await evaluate("getComputedStyle(document.querySelector('[data-submitted-group=event] dl')).gridTemplateColumns.split(' ').length"), 2);
        assert.equal(await evaluate("document.querySelectorAll('[data-submitted-item]:not([open])').length"), 3);
        await evaluate("document.querySelector('[data-submitted-group=categories] summary').click()");
        assert.equal(await evaluate("document.querySelector('[data-submitted-group=categories] [data-submitted-item]').open"), true);
        assert.match(await evaluate("document.querySelector('[data-submitted-group=categories]').textContent"), /PHP 950\.00/);
        assert.match(await evaluate("document.querySelector('[data-submitted-group=categories]').textContent"), /2 route points; 1 checkpoint/);
        assert.equal(await evaluate("document.querySelector('[data-submitted-group=categories] dd details').open"), false);
        await evaluate("document.querySelector('[data-submitted-group=categories] dd details > summary').click()");
        assert.equal(await evaluate("document.querySelector('[data-submitted-group=categories] dd details').open"), true);
        await evaluate("document.querySelector('[data-submitted-group=paymentMethods] summary').click()");
        assert.match(await evaluate("document.querySelector('[data-submitted-group=paymentMethods]').textContent"), /09170000000/);
        for (const width of [375, 320]) {
            await send('Emulation.setDeviceMetricsOverride', { width, height: 800, deviceScaleFactor: 1, mobile: false });
            assert.equal(await evaluate("getComputedStyle(document.querySelector('[data-submitted-group=event] dl')).gridTemplateColumns.split(' ').length"), 1);
            assert.equal(await evaluate("document.documentElement.scrollWidth <= window.innerWidth"), true, 'Long details must not overflow the phone viewport');
        }
    } finally {
        for (const request of pending.values()) clearTimeout(request.timeout);
        socket?.close();
        if (browser && browser.exitCode === null) {
            const closed = new Promise(resolve => browser.once('exit', resolve));
            browser.kill(); await Promise.race([closed, delay(5000)]);
        }
        await new Promise(resolve => server.close(resolve));
        const resolved = resolve(profile);
        assert.ok(resolved.startsWith(resolve(tmpdir()) + sep) && /^racetech-submitted-summary-/.test(resolved.split(sep).at(-1)));
        await rm(resolved, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
    }
});
